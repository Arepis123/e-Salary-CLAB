<?php

namespace App\Livewire\Admin;

use App\Models\PayrollSubmission;
use Flux\Flux;
use Livewire\Component;
use Livewire\WithFileUploads;

class SalaryDetail extends Component
{
    use WithFileUploads;

    public PayrollSubmission $submission;
    public $workers = [];
    public $stats = [];
    public $previousSubmission = null;
    public $previousWorkers = [];
    public $previousOtStats = [];

    // Review modal properties
    public $showReviewModal = false;
    public $reviewFinalAmount = '';
    public $reviewNotes = '';
    public $breakdownFile;
    public $isReviewing = false;

    // Re-upload modal properties
    public $showReuploadModal = false;
    public $newBreakdownFile;
    public $isReuploading = false;

    public function mount($id)
    {
        $this->submission = PayrollSubmission::with(['user', 'payment', 'workers.worker'])
            ->findOrFail($id);

        $this->loadWorkers();
        $this->calculateStats();
        $this->loadPreviousMonthOT();
    }

    protected function loadWorkers()
    {
        $this->workers = $this->submission->workers()
            ->with('worker')
            ->get();
    }

    protected function calculateStats()
    {
        $this->stats = [
            'total_workers' => $this->workers->count(),
            'total_regular_hours' => $this->workers->sum('regular_hours'),
            'total_ot_hours' => $this->workers->sum(function ($worker) {
                return $worker->ot_normal_hours + $worker->ot_rest_hours + $worker->ot_public_hours;
            }),
            'total_basic_salary' => $this->workers->sum('basic_salary'),
            'total_ot_pay' => $this->workers->sum('total_ot_pay'),
            'total_gross_salary' => $this->workers->sum('gross_salary'),
            'total_deductions' => $this->workers->sum('total_deductions'),
            'total_net_salary' => $this->workers->sum('net_salary'),
            'total_employer_contribution' => $this->workers->sum('total_employer_contribution'),
            'total_payment' => $this->workers->sum('total_payment'),
        ];
    }

    protected function loadPreviousMonthOT()
    {
        // Calculate previous month/year
        $currentMonth = $this->submission->month;
        $currentYear = $this->submission->year;

        $previousMonth = $currentMonth - 1;
        $previousYear = $currentYear;

        if ($previousMonth < 1) {
            $previousMonth = 12;
            $previousYear = $currentYear - 1;
        }

        // Find previous month's submission for the same contractor
        $this->previousSubmission = PayrollSubmission::with(['workers.worker'])
            ->where('contractor_clab_no', $this->submission->contractor_clab_no)
            ->where('month', $previousMonth)
            ->where('year', $previousYear)
            ->first();

        if ($this->previousSubmission) {
            $this->previousWorkers = $this->previousSubmission->workers;

            $this->previousOtStats = [
                'total_ot_hours' => $this->previousWorkers->sum(function ($worker) {
                    return $worker->ot_normal_hours + $worker->ot_rest_hours + $worker->ot_public_hours;
                }),
                'total_ot_pay' => $this->previousWorkers->sum('total_ot_pay'),
                'total_weekday_ot_hours' => $this->previousWorkers->sum('ot_normal_hours'),
                'total_weekday_ot_pay' => $this->previousWorkers->sum('ot_normal_pay'),
                'total_rest_ot_hours' => $this->previousWorkers->sum('ot_rest_hours'),
                'total_rest_ot_pay' => $this->previousWorkers->sum('ot_rest_pay'),
                'total_public_ot_hours' => $this->previousWorkers->sum('ot_public_hours'),
                'total_public_ot_pay' => $this->previousWorkers->sum('ot_public_pay'),
            ];
        } else {
            $this->previousOtStats = [
                'total_ot_hours' => 0,
                'total_ot_pay' => 0,
                'total_weekday_ot_hours' => 0,
                'total_weekday_ot_pay' => 0,
                'total_rest_ot_hours' => 0,
                'total_rest_ot_pay' => 0,
                'total_public_ot_hours' => 0,
                'total_public_ot_pay' => 0,
            ];
        }
    }

    public function downloadReceipt()
    {
        // TODO: Implement download receipt functionality
        Flux::toast(variant: 'info', text: 'Download receipt functionality coming soon!');
    }

    public function printPayslip()
    {
        // TODO: Implement print payslip functionality
        Flux::toast(variant: 'info', text: 'Print payslip functionality coming soon!');
    }

    public function markAsPaid()
    {
        // TODO: Implement mark as paid functionality (for manual payments)
        Flux::toast(variant: 'success', text: 'Manual payment marking functionality coming soon!');
    }

    public function sendReminder()
    {
        // TODO: Implement send reminder functionality
        Flux::toast(variant: 'success', text: 'Payment reminder sent to contractor!');
    }

    public function viewPaymentProof()
    {
        // TODO: Implement view payment proof functionality
        Flux::toast(variant: 'info', text: 'Payment proof viewing functionality coming soon!');
    }

    public function exportWorkerList()
    {
        // TODO: Implement export worker list functionality
        Flux::toast(variant: 'info', text: 'Export functionality coming soon!');
    }

    public function openReviewModal()
    {
        if (!$this->submission->canBeReviewed()) {
            Flux::toast(variant: 'warning', text: 'Cannot review this submission.');
            return;
        }

        // Do NOT pre-fill amount - admin must enter from external system
        $this->reviewFinalAmount = '';
        $this->reviewNotes = $this->submission->admin_notes ?? '';
        $this->showReviewModal = true;
    }

    public function closeReviewModal()
    {
        $this->showReviewModal = false;
        $this->resetValidation();
    }

    public function approveSubmission()
    {
        $this->validate([
            'reviewFinalAmount' => 'required|numeric|min:0.01',
            'breakdownFile' => 'required|file|mimes:xlsx,xls,pdf|max:10240', // 10MB max
            'reviewNotes' => 'nullable|string|max:1000',
        ]);

        try {
            $this->isReviewing = true;

            // Generate custom filename: worker_breakdown_CLAB000000_DEC_2025.xlsx
            $extension = $this->breakdownFile->getClientOriginalExtension();
            $monthName = strtoupper(date('M', mktime(0, 0, 0, $this->submission->month, 1)));
            $customFileName = sprintf(
                'worker_breakdown_%s_%s_%s.%s',
                $this->submission->contractor_clab_no,
                $monthName,
                $this->submission->year,
                $extension
            );

            // Ensure directory exists
            $directory = 'payroll-breakdowns/' . $this->submission->year . '/' . $this->submission->month;
            $fullDirectoryPath = storage_path('app/' . $directory);

            if (!file_exists($fullDirectoryPath)) {
                mkdir($fullDirectoryPath, 0755, true);
            }

            // Store breakdown file with custom name
            $filePath = $this->breakdownFile->storeAs($directory, $customFileName, 'local');

            // Update submission with admin review
            $this->submission->update([
                'status' => 'approved',
                'admin_reviewed_by' => auth()->id(),
                'admin_reviewed_at' => now(),
                'admin_final_amount' => $this->reviewFinalAmount,
                'admin_notes' => $this->reviewNotes,
                'breakdown_file_path' => $filePath,
                'breakdown_file_name' => $customFileName,
            ]);

            Flux::toast(
                variant: 'success',
                heading: 'Submission Approved',
                text: 'Submission has been approved with final amount RM ' . number_format($this->reviewFinalAmount, 2)
            );

            $this->closeReviewModal();
            $this->mount($this->submission->id); // Refresh data

        } catch (\Exception $e) {
            Flux::toast(
                variant: 'danger',
                heading: 'Error',
                text: 'Failed to approve submission: ' . $e->getMessage()
            );
        } finally {
            $this->isReviewing = false;
        }
    }

    public function downloadBreakdown()
    {
        // Refresh submission to get latest data
        $this->submission->refresh();

        if (!$this->submission->hasBreakdownFile()) {
            Flux::toast(variant: 'warning', text: 'No breakdown file available.');
            \Log::warning('Download attempted but no file path in database', [
                'submission_id' => $this->submission->id,
            ]);
            return;
        }

        // Get the correct path using Storage disk (accounts for 'private' subdirectory)
        $filePath = \Storage::disk('local')->path($this->submission->breakdown_file_path);

        // Debug logging
        \Log::info('Attempting to download breakdown file', [
            'submission_id' => $this->submission->id,
            'db_file_path' => $this->submission->breakdown_file_path,
            'full_path' => $filePath,
            'file_exists' => file_exists($filePath),
        ]);

        // Check if the physical file actually exists
        if (!file_exists($filePath)) {
            Flux::toast(
                variant: 'danger',
                heading: 'File Not Found',
                text: 'The breakdown file is missing from storage. Please use the Replace button to upload a new file.'
            );

            // Log the missing file for admin awareness
            \Log::warning('Breakdown file missing from storage', [
                'submission_id' => $this->submission->id,
                'db_file_path' => $this->submission->breakdown_file_path,
                'expected_location' => $filePath,
                'file_name' => $this->submission->breakdown_file_name,
            ]);

            return;
        }

        try {
            \Log::info('Download successful', [
                'submission_id' => $this->submission->id,
                'file_name' => $this->submission->breakdown_file_name,
            ]);

            return response()->download($filePath, $this->submission->breakdown_file_name);
        } catch (\Exception $e) {
            Flux::toast(
                variant: 'danger',
                heading: 'Download Failed',
                text: 'Unable to download the file: ' . $e->getMessage()
            );

            \Log::error('Breakdown file download failed', [
                'submission_id' => $this->submission->id,
                'file_path' => $filePath,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return;
        }
    }

    public function openReuploadModal()
    {
        $this->newBreakdownFile = null;
        $this->showReuploadModal = true;
    }

    public function closeReuploadModal()
    {
        $this->showReuploadModal = false;
        $this->newBreakdownFile = null;
        $this->resetValidation();
    }

    public function reuploadBreakdown()
    {
        $this->validate([
            'newBreakdownFile' => 'required|file|mimes:xlsx,xls,pdf|max:10240', // 10MB max
        ]);

        try {
            $this->isReuploading = true;

            // Log file details before upload
            \Log::info('Starting breakdown file re-upload', [
                'submission_id' => $this->submission->id,
                'file_original_name' => $this->newBreakdownFile->getClientOriginalName(),
                'file_size' => $this->newBreakdownFile->getSize(),
                'file_mime' => $this->newBreakdownFile->getMimeType(),
                'temp_path' => $this->newBreakdownFile->getRealPath(),
                'temp_file_exists' => file_exists($this->newBreakdownFile->getRealPath()),
            ]);

            // Delete old file if it exists
            if ($this->submission->breakdown_file_path && \Storage::disk('local')->exists($this->submission->breakdown_file_path)) {
                \Storage::disk('local')->delete($this->submission->breakdown_file_path);
                \Log::info('Deleted old breakdown file', ['old_path' => $this->submission->breakdown_file_path]);
            }

            // Generate custom filename: worker_breakdown_CLAB000000_DEC_2025.xlsx
            $extension = $this->newBreakdownFile->getClientOriginalExtension();
            $monthName = strtoupper(date('M', mktime(0, 0, 0, $this->submission->month, 1)));
            $customFileName = sprintf(
                'worker_breakdown_%s_%s_%s.%s',
                $this->submission->contractor_clab_no,
                $monthName,
                $this->submission->year,
                $extension
            );

            // Ensure directory exists
            $directory = 'payroll-breakdowns/' . $this->submission->year . '/' . $this->submission->month;
            $fullDirectoryPath = storage_path('app/' . $directory);

            if (!file_exists($fullDirectoryPath)) {
                mkdir($fullDirectoryPath, 0755, true);
                \Log::info('Created directory', ['path' => $fullDirectoryPath]);
            }

            // Log before storing
            \Log::info('About to store file', [
                'directory' => $directory,
                'filename' => $customFileName,
                'full_path' => $fullDirectoryPath . '/' . $customFileName,
            ]);

            // Store new breakdown file with custom name - use storeAs directly
            $filePath = $this->newBreakdownFile->storeAs($directory, $customFileName, 'local');

            \Log::info('storeAs() returned', [
                'returned_path' => $filePath,
                'expected_path' => $directory . '/' . $customFileName,
            ]);

            // Check if file actually exists on filesystem using Storage disk path
            $fullFilePath = \Storage::disk('local')->path($filePath);
            $fileExists = file_exists($fullFilePath);

            \Log::info('File existence check after upload', [
                'file_path' => $filePath,
                'full_file_path' => $fullFilePath,
                'file_exists' => $fileExists,
                'directory_exists' => file_exists($fullDirectoryPath),
                'directory_writable' => is_writable($fullDirectoryPath),
            ]);

            // Verify the file was actually stored
            if (!$fileExists) {
                throw new \Exception('File upload verification failed. The file was not properly stored at: ' . $fullFilePath);
            }

            // Update submission with new file
            $this->submission->update([
                'breakdown_file_path' => $filePath,
                'breakdown_file_name' => $customFileName,
            ]);

            // Refresh the submission model from database
            $this->submission->refresh();

            // Log successful upload
            \Log::info('Breakdown file re-uploaded successfully', [
                'submission_id' => $this->submission->id,
                'file_path' => $filePath,
                'file_name' => $customFileName,
                'verified_exists' => file_exists($fullFilePath),
            ]);

            $this->closeReuploadModal();

            Flux::toast(
                variant: 'success',
                heading: 'File Replaced',
                text: 'Breakdown file has been successfully re-uploaded and is ready for download.'
            );

        } catch (\Exception $e) {
            \Log::error('Breakdown file re-upload failed', [
                'submission_id' => $this->submission->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            Flux::toast(
                variant: 'danger',
                heading: 'Upload Failed',
                text: 'Failed to re-upload breakdown file: ' . $e->getMessage()
            );
        } finally {
            $this->isReuploading = false;
        }
    }

    public function render()
    {
        return view('livewire.admin.salary-detail');
    }
}
