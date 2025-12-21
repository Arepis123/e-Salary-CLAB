<?php

namespace App\Livewire\Admin;

use App\Models\PayrollSubmission;
use App\Mail\PayrollApproved;
use Flux\Flux;
use Illuminate\Support\Facades\Mail;
use Livewire\Component;
use Livewire\WithFileUploads;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

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
        try {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            // Set document properties
            $spreadsheet->getProperties()
                ->setTitle('Worker Payroll List - ' . $this->submission->month_year)
                ->setSubject('Worker Payroll Details');

            // Title row
            $sheet->setCellValue('A1', 'PAYROLL SUBMISSION - ' . strtoupper($this->submission->month_year));
            $sheet->mergeCells('A1:L1');
            $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
            $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            // Submission info
            $sheet->setCellValue('A2', 'Contractor: ' . $this->submission->user->name);
            $sheet->setCellValue('A3', 'CLAB No: ' . $this->submission->contractor_clab_no);
            $sheet->setCellValue('A4', 'Status: ' . strtoupper($this->submission->status));
            $sheet->setCellValue('A5', 'Total Workers: ' . $this->stats['total_workers']);

            // Headers (row 7)
            $headers = [
                'A7' => 'No',
                'B7' => 'Worker ID',
                'C7' => 'Worker Name',
                'D7' => 'Passport',
                'E7' => 'Basic Salary (RM)',
                'F7' => 'OT Normal (hrs)',
                'G7' => 'OT Rest (hrs)',
                'H7' => 'OT Public (hrs)',
                'I7' => 'Advance Payment (RM)',
                'J7' => 'Other Deduction (RM)',
                'K7' => 'NPL (days)',
                'L7' => 'Allowance (RM)',
            ];

            foreach ($headers as $cell => $value) {
                $sheet->setCellValue($cell, $value);
            }

            // Style headers
            $sheet->getStyle('A7:L7')->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '4F46E5']
                ],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                'borders' => [
                    'allBorders' => ['borderStyle' => Border::BORDER_THIN]
                ]
            ]);

            // Data rows
            $row = 8;
            $no = 1;
            foreach ($this->workers as $worker) {
                $sheet->setCellValue('A' . $row, $no++);
                $sheet->setCellValue('B' . $row, $worker->worker_id);
                $sheet->setCellValue('C' . $row, $worker->worker_name);
                $sheet->setCellValue('D' . $row, $worker->worker_passport);
                $sheet->setCellValue('E' . $row, $worker->basic_salary);
                $sheet->setCellValue('F' . $row, $worker->ot_normal_hours ?? 0);
                $sheet->setCellValue('G' . $row, $worker->ot_rest_hours ?? 0);
                $sheet->setCellValue('H' . $row, $worker->ot_public_hours ?? 0);
                $sheet->setCellValue('I' . $row, $worker->advance_payment ?? 0);
                $sheet->setCellValue('J' . $row, $worker->other_deduction ?? 0);
                $sheet->setCellValue('K' . $row, $worker->npl_days ?? 0);
                $sheet->setCellValue('L' . $row, $worker->allowance ?? 0);

                // Format currency columns
                foreach (['E', 'I', 'J', 'L'] as $col) {
                    $sheet->getStyle($col . $row)->getNumberFormat()
                        ->setFormatCode('#,##0.00');
                }

                // Format hours columns
                foreach (['F', 'G', 'H'] as $col) {
                    $sheet->getStyle($col . $row)->getNumberFormat()
                        ->setFormatCode('0.00');
                }

                // Format NPL days column
                $sheet->getStyle('K' . $row)->getNumberFormat()
                    ->setFormatCode('0.0');

                $row++;
            }

            // Total row
            $totalRow = $row;
            $sheet->setCellValue('A' . $totalRow, 'TOTAL');
            $sheet->mergeCells('A' . $totalRow . ':D' . $totalRow);
            $sheet->setCellValue('E' . $totalRow, '=SUM(E8:E' . ($totalRow - 1) . ')');
            $sheet->setCellValue('F' . $totalRow, '=SUM(F8:F' . ($totalRow - 1) . ')');
            $sheet->setCellValue('G' . $totalRow, '=SUM(G8:G' . ($totalRow - 1) . ')');
            $sheet->setCellValue('H' . $totalRow, '=SUM(H8:H' . ($totalRow - 1) . ')');
            $sheet->setCellValue('I' . $totalRow, '=SUM(I8:I' . ($totalRow - 1) . ')');
            $sheet->setCellValue('J' . $totalRow, '=SUM(J8:J' . ($totalRow - 1) . ')');
            $sheet->setCellValue('K' . $totalRow, '=SUM(K8:K' . ($totalRow - 1) . ')');
            $sheet->setCellValue('L' . $totalRow, '=SUM(L8:L' . ($totalRow - 1) . ')');

            // Style total row
            $sheet->getStyle('A' . $totalRow . ':L' . $totalRow)->applyFromArray([
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E5E7EB']
                ],
                'borders' => [
                    'allBorders' => ['borderStyle' => Border::BORDER_THIN]
                ]
            ]);

            // Format currency in total row
            foreach (['E', 'I', 'J', 'L'] as $col) {
                $sheet->getStyle($col . $totalRow)->getNumberFormat()
                    ->setFormatCode('#,##0.00');
            }

            // Format hours in total row
            foreach (['F', 'G', 'H'] as $col) {
                $sheet->getStyle($col . $totalRow)->getNumberFormat()
                    ->setFormatCode('0.00');
            }

            // Format NPL days in total row
            $sheet->getStyle('K' . $totalRow)->getNumberFormat()
                ->setFormatCode('0.0');

            // Auto-size columns
            foreach (range('A', 'L') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }

            // Freeze panes at header row
            $sheet->freezePane('A8');

            // Generate filename
            $monthName = strtoupper(date('M', mktime(0, 0, 0, $this->submission->month, 1)));
            $fileName = sprintf(
                'Worker_List_%s_%s_%s.xlsx',
                $this->submission->contractor_clab_no,
                $monthName,
                $this->submission->year
            );

            // Create file
            $writer = new Xlsx($spreadsheet);
            $tempFile = tempnam(sys_get_temp_dir(), $fileName);
            $writer->save($tempFile);

            return response()->download($tempFile, $fileName)->deleteFileAfterSend(true);

        } catch (\Exception $e) {
            Flux::toast(
                variant: 'danger',
                heading: 'Export Failed',
                text: 'Failed to export worker list: ' . $e->getMessage()
            );
        }
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

            // Check if submission is overdue and apply penalty immediately
            $this->submission->refresh();
            if ($this->submission->isOverdue() && !$this->submission->has_penalty) {
                $this->submission->updatePenalty();
                $this->submission->refresh();

                Flux::toast(
                    variant: 'warning',
                    heading: 'Late Submission - Penalty Applied',
                    text: 'This is a late submission. 8% penalty (RM ' . number_format($this->submission->penalty_amount, 2) . ') has been automatically applied.'
                );
            } else {
                Flux::toast(
                    variant: 'success',
                    heading: 'Submission Approved',
                    text: 'Submission has been approved with final amount RM ' . number_format($this->reviewFinalAmount, 2)
                );
            }

            // Send email notification to client
            try {
                Mail::to($this->submission->user->email)
                    ->send(new PayrollApproved(
                        $this->submission,
                        $this->reviewFinalAmount,
                        $this->reviewNotes
                    ));
            } catch (\Exception $e) {
                \Log::error('Failed to send payroll approval email', [
                    'submission_id' => $this->submission->id,
                    'error' => $e->getMessage()
                ]);
                // Don't show error to admin - email failure shouldn't block the approval
            }

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
