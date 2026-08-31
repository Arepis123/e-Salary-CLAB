<?php

namespace App\Livewire\Admin;

use App\Exceptions\BreakdownFileParseException;
use App\Mail\PayrollApproved;
use App\Mail\PayslipReady;
use App\Models\MonthlyOTEntry;
use App\Models\PayrollSubmission;
use App\Models\SalaryDeductionForm;
use App\Services\BreakdownFileParser;
use App\Services\SalaryDeductionFormService;
use App\Services\TimesheetDriftService;
use App\Traits\LogsActivity;
use Flux\Flux;
use Illuminate\Support\Facades\Mail;
use Livewire\Component;
use Livewire\WithFileUploads;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class SalaryDetail extends Component
{
    use LogsActivity;
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

    public $calculatedBreakdown = null; // Store parsed Excel breakdown

    // Set by the admin to confirm they have seen a difference between the
    // uploaded file, the contractor's submission and the amount being saved.
    public $varianceAcknowledged = false;

    // The same confirmation for the edit modal.
    public $editVarianceAcknowledged = false;

    // Re-upload modal properties
    public $showReuploadModal = false;

    public $newBreakdownFile;

    public $isReuploading = false;

    // Edit amount modal properties
    public $showEditAmountModal = false;

    public $editPayrollAmount = '';

    public $editAmountNotes = '';

    // Payslip upload properties
    public $showUploadPayslipModal = false;

    public $payslipFile;

    public $isUpdatingAmount = false;

    // Manual (off-platform) payment recording properties
    public $showManualPaymentModal = false;

    public $manualPaymentDate = '';

    public $manualPaymentReference = '';

    public $manualPaymentBank = '';

    public $manualPaymentAmount = '';

    public $manualPaymentNotes = '';

    public $manualPaymentProof;

    public $isRecordingPayment = false;

    /**
     * The signed Salary Deduction Form the contractor uploaded for the OT
     * entry period this payroll was raised from, plus how many workers
     * actually needed one. Null when nothing has been uploaded.
     */
    public ?array $deductionForm = null;

    public int $deductionWorkersCount = 0;

    /**
     * The entry period the deduction form belongs to (the month before
     * payroll), resolved once in mount() so the reminder, the history lookup
     * and the on-behalf upload all agree on which period they are acting on.
     */
    public int $deductionEntryMonth = 0;

    public int $deductionEntryYear = 0;

    public bool $showDeductionEmailModal = false;

    public bool $showDeductionHistoryModal = false;

    public bool $showDeductionUploadModal = false;

    public string $deductionEmailSubject = '';

    public string $deductionEmailMessage = '';

    public bool $isSendingDeductionEmail = false;

    /** Previously sent reminders, newest first. Loaded when the modal opens. */
    public array $deductionEmailHistory = [];

    public $adminDeductionFormFile;

    public function mount($id)
    {
        $this->submission = PayrollSubmission::with(['user', 'payment', 'workers.worker'])
            ->findOrFail($id);

        $this->loadWorkers();
        $this->calculateStats();
        $this->loadPreviousMonthOT();
        $this->loadDeductionForm();
    }

    /**
     * Resolve the signed Salary Deduction Form for this submission.
     *
     * The form belongs to the OT entry period (the month before payroll), so
     * the mapping is borrowed from TimesheetDriftService rather than repeating
     * the year-rollover arithmetic here.
     */
    protected function loadDeductionForm(): void
    {
        [$entryMonth, $entryYear] = app(TimesheetDriftService::class)
            ->otEntryPeriod($this->submission->month, $this->submission->year);

        $this->deductionEntryMonth = $entryMonth;
        $this->deductionEntryYear = $entryYear;

        // How many workers had a deduction keyed in for that period — the same
        // source the client's form was generated from, so admins can tell an
        // outstanding form from one that was never needed.
        $this->deductionWorkersCount = MonthlyOTEntry::where('contractor_clab_no', $this->submission->contractor_clab_no)
            ->where('entry_month', $entryMonth)
            ->where('entry_year', $entryYear)
            ->whereHas('transactions', fn ($query) => $query
                ->whereIn('type', SalaryDeductionFormService::DEDUCTION_TYPES)
                ->where('amount', '>', 0))
            ->count();

        $record = SalaryDeductionForm::forPeriod(
            $this->submission->contractor_clab_no,
            $entryMonth,
            $entryYear
        );

        $this->deductionForm = $record ? [
            'id' => $record->id,
            'file_name' => $record->file_name,
            'file_size' => $record->file_size_for_humans,
            'workers_count' => $record->workers_count,
            'uploaded_at' => optional($record->uploaded_at)->format('d M Y, H:i'),
            'uploaded_by' => $record->uploadedBy?->name,
            'entry_period' => $record->entry_period,
        ] : null;
    }

    /**
     * How notification logs for the deduction form are tagged, so the History
     * modal can tell a form reminder apart from the payment reminders that
     * also reference this submission.
     *
     * A plain string rather than a model class: the reminder exists precisely
     * when there is no SalaryDeductionForm row to point at.
     */
    public const DEDUCTION_REMINDER_REFERENCE = 'salary_deduction_form';

    /**
     * Open the reminder composer, pre-filled but editable — the admin sees
     * exactly what the contractor will receive before anything is sent.
     */
    public function openDeductionEmailModal(): void
    {
        $period = \Carbon\Carbon::create($this->deductionEntryYear, $this->deductionEntryMonth, 1)->format('F Y');

        $this->deductionEmailSubject = 'Signed Salary Deduction Form outstanding — '.$period;

        $this->deductionEmailMessage = implode("\n\n", [
            'Dear '.($this->submission->user?->name ?: 'Sir/Madam').',',
            'Our records show that the signed Salary Deduction Form for '.$period.' has not yet been '
                .'uploaded. '.$this->deductionWorkersCount.' '.\Str::plural('worker', $this->deductionWorkersCount)
                .' had deductions recorded for that period, so a signed declaration is required.',
            'Please log in to the eSalary CLAB system, download the pre-filled form from the OT & '
                .'Transaction Entry page, have it signed, and upload the signed copy.',
            'Thank you.',
        ]);

        $this->showDeductionEmailModal = true;
    }

    /**
     * Send the reminder to the contractor's account holder.
     */
    public function sendDeductionFormReminder(): void
    {
        $this->validate([
            'deductionEmailSubject' => 'required|string|max:255',
            'deductionEmailMessage' => 'required|string|max:5000',
        ], [], [
            'deductionEmailSubject' => 'subject',
            'deductionEmailMessage' => 'message',
        ]);

        $recipient = $this->submission->user;

        if (! $recipient) {
            Flux::toast(
                variant: 'danger',
                heading: 'No Recipient',
                text: 'This submission has no user account to email.'
            );

            return;
        }

        $this->isSendingDeductionEmail = true;

        try {
            $log = app(\App\Services\NotificationService::class)->sendCustom(
                recipient: $recipient,
                subject: $this->deductionEmailSubject,
                body: $this->deductionEmailMessage,
                referenceType: self::DEDUCTION_REMINDER_REFERENCE,
                referenceId: $this->submission->id,
            );

            // sendCustom() records a failure on the log rather than throwing,
            // so the outcome has to be read back off it.
            if ($log->status === 'failed') {
                Flux::toast(
                    variant: 'danger',
                    heading: 'Not Sent',
                    text: 'The email could not be sent: '.($log->error_message ?: 'unknown error').'.'
                );

                return;
            }

            $this->showDeductionEmailModal = false;

            $this->logActivity(
                module: 'ot_entry',
                action: 'sent_deduction_form_reminder',
                description: 'Sent Salary Deduction Form reminder to '.$recipient->email,
                subject: $this->submission,
                properties: [
                    'submission_id' => $this->submission->id,
                    'entry_period' => $this->deductionEntryMonth.'/'.$this->deductionEntryYear,
                    'notification_log_id' => $log->id,
                ]
            );

            Flux::toast(
                variant: 'success',
                heading: 'Reminder Sent',
                text: 'Emailed '.$recipient->email.'.'
            );
        } finally {
            $this->isSendingDeductionEmail = false;
        }
    }

    /**
     * Open the log of reminders already sent for this form.
     */
    public function openDeductionHistoryModal(): void
    {
        $this->deductionEmailHistory = \App\Models\NotificationLog::with('sender')
            ->where('reference_type', self::DEDUCTION_REMINDER_REFERENCE)
            ->where('reference_id', $this->submission->id)
            ->latest('id')
            ->get()
            ->map(fn ($log) => [
                'id' => $log->id,
                'subject' => $log->subject,
                'recipient' => $log->recipient_email,
                'status' => $log->status,
                'error' => $log->error_message,
                'sent_at' => optional($log->sent_at ?? $log->created_at)->format('d M Y, H:i'),
                'sent_by' => $log->sender?->name,
                'opened_at' => optional($log->opened_at)->format('d M Y, H:i'),
                'bounced_at' => optional($log->bounced_at)->format('d M Y, H:i'),
            ])
            ->all();

        $this->showDeductionHistoryModal = true;
    }

    /**
     * Store the signed form on the contractor's behalf — for the cases where
     * they hand it in by email or on paper. Deliberately mirrors
     * OTEntry::uploadDeductionForm() so both routes produce the same record.
     */
    public function uploadDeductionFormOnBehalf(): void
    {
        $this->validate([
            'adminDeductionFormFile' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ], [
            'adminDeductionFormFile.required' => 'Please choose the signed form to upload.',
            'adminDeductionFormFile.mimes' => 'The signed form must be a PDF or an image (JPG/PNG).',
            'adminDeductionFormFile.max' => 'The signed form may not be larger than 10 MB.',
        ]);

        $clabNo = $this->submission->contractor_clab_no;
        $month = $this->deductionEntryMonth;
        $year = $this->deductionEntryYear;

        try {
            $existing = SalaryDeductionForm::forPeriod($clabNo, $month, $year);

            $directory = 'salary-deduction-forms/'.$clabNo.'/'.$year.'-'.str_pad((string) $month, 2, '0', STR_PAD_LEFT);

            $fileName = app(SalaryDeductionFormService::class)->fileName(
                $clabNo,
                $month,
                $year,
                $this->adminDeductionFormFile->getClientOriginalExtension() ?: $this->adminDeductionFormFile->extension()
            );

            $storedPath = $this->adminDeductionFormFile->storeAs($directory, $fileName, 'local');

            // Drop the superseded file only after the new one is safely stored,
            // and never when re-uploading has just written over it in place.
            if ($existing && $existing->file_path !== $storedPath
                && \Storage::disk('local')->exists($existing->file_path)) {
                \Storage::disk('local')->delete($existing->file_path);
            }

            SalaryDeductionForm::updateOrCreate(
                [
                    'contractor_clab_no' => $clabNo,
                    'entry_month' => $month,
                    'entry_year' => $year,
                ],
                [
                    'file_path' => $storedPath,
                    'file_name' => $fileName,
                    'file_size' => $this->adminDeductionFormFile->getSize(),
                    'mime_type' => $this->adminDeductionFormFile->getMimeType(),
                    'workers_count' => $this->deductionWorkersCount,
                    'uploaded_by' => auth()->id(),
                    'uploaded_at' => now(),
                ]
            );

            $this->reset('adminDeductionFormFile');
            $this->showDeductionUploadModal = false;
            $this->loadDeductionForm();

            $this->logActivity(
                module: 'ot_entry',
                action: 'uploaded_deduction_form_on_behalf',
                description: 'Uploaded signed Salary Deduction Form on behalf of '.$clabNo,
                subject: $this->submission,
                properties: [
                    'submission_id' => $this->submission->id,
                    'entry_period' => $month.'/'.$year,
                    'file_name' => $fileName,
                ]
            );

            Flux::toast(
                variant: 'success',
                heading: 'Uploaded',
                text: 'Signed form recorded for '.\Carbon\Carbon::create($year, $month, 1)->format('F Y').'.'
            );
        } catch (\Exception $e) {
            Flux::toast(
                variant: 'danger',
                heading: 'Upload Failed',
                text: 'Could not upload the signed form: '.$e->getMessage()
            );
        }
    }

    protected function loadWorkers()
    {
        // A submission is a snapshot of a payroll that was already run, so it
        // lists every worker it was raised for. Filtering on the worker's
        // *current* status used to drop people whose contract ended after the
        // fact, silently removing their salary from the client breakdown of an
        // already-approved month.
        $this->workers = $this->submission->workers()
            ->with(['worker', 'transactions.nplDetails'])
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
        // Only allow receipt download for paid invoices
        if ($this->submission->status !== 'paid') {
            Flux::toast(variant: 'warning', text: 'Receipt is only available for paid invoices.');

            return;
        }

        // Generate tax invoice number if not already generated
        if (! $this->submission->hasTaxInvoice()) {
            $this->submission->generateTaxInvoiceNumber();
            $this->submission->refresh();
        }

        $contractor = $this->submission->user;
        $contractorRecord = \App\Models\Contractor::find($this->submission->contractor_clab_no);

        $contractorState = null;
        if ($contractorRecord && $contractorRecord->ctr_state) {
            $state = \DB::connection('worker_db')
                ->table('mst_states')
                ->where('state_id', $contractorRecord->ctr_state)
                ->value('state_name');
            $contractorState = $state ?? null;
        }

        $pdf = \PDF::loadView('admin.tax-invoice-pdf', [
            'invoice' => $this->submission,
            'contractor' => $contractor,
            'contractorRecord' => $contractorRecord,
            'contractorState' => $contractorState,
        ])->setPaper('a4', 'portrait');

        $filename = 'Official-Receipt-'.$this->submission->tax_invoice_number.'-'.$this->submission->month_year.'.pdf';

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, $filename);
    }

    public function openManualPaymentModal()
    {
        // Guard: only unpaid submissions that have passed admin review can be marked paid
        if ($this->submission->status === 'paid' || ($this->submission->payment && $this->submission->payment->status === 'completed')) {
            Flux::toast(variant: 'warning', text: 'This payroll has already been paid.');

            return;
        }

        if (! $this->submission->canCreatePayment()) {
            Flux::toast(variant: 'warning', text: 'This submission must be approved by admin before a payment can be recorded.');

            return;
        }

        // Make sure penalty (if overdue) is reflected before pre-filling the amount
        $this->submission->updatePenalty();
        $this->submission->refresh();

        $this->manualPaymentDate = now()->format('Y-m-d');
        $this->manualPaymentReference = '';
        $this->manualPaymentBank = '';
        $this->manualPaymentAmount = number_format($this->submission->total_due, 2, '.', '');
        $this->manualPaymentNotes = '';
        $this->manualPaymentProof = null;
        $this->resetValidation();
        $this->showManualPaymentModal = true;
    }

    public function closeManualPaymentModal()
    {
        $this->showManualPaymentModal = false;
        $this->manualPaymentProof = null;
        $this->resetValidation();
    }

    /**
     * Record a payment that was made off-platform (e.g. a direct bank transfer
     * into the company account, not collected through Billplz FPX).
     *
     * This creates a completed PayrollPayment labelled honestly as a bank
     * transfer so reconciliation can tell it apart from FPX settlements, then
     * flips the submission to paid so the official receipt can be generated.
     */
    public function recordManualPayment()
    {
        // Re-check state in case it changed while the modal was open
        if ($this->submission->status === 'paid' || ($this->submission->payment && $this->submission->payment->status === 'completed')) {
            Flux::toast(variant: 'warning', text: 'This payroll has already been paid.');
            $this->closeManualPaymentModal();

            return;
        }

        $this->validate([
            'manualPaymentDate' => 'required|date|before_or_equal:today',
            'manualPaymentReference' => 'required|string|max:191',
            'manualPaymentBank' => 'required|string|max:191',
            'manualPaymentAmount' => 'required|numeric|min:0.01',
            'manualPaymentNotes' => 'nullable|string|max:1000',
            'manualPaymentProof' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240', // 10MB max
        ], [
            'manualPaymentReference.required' => 'Enter the bank transaction / reference number.',
            'manualPaymentBank.required' => 'Enter the bank the transfer came from.',
            'manualPaymentDate.before_or_equal' => 'Payment date cannot be in the future.',
            'manualPaymentProof.required' => 'Upload the proof of payment.',
        ]);

        try {
            $this->isRecordingPayment = true;

            // Store proof of payment (bank-in slip) if provided
            $proofPath = null;
            $proofName = null;
            if ($this->manualPaymentProof) {
                $extension = $this->manualPaymentProof->getClientOriginalExtension();
                $proofName = sprintf(
                    'payment_proof_%s_%s.%s',
                    $this->submission->id,
                    now()->format('YmdHis'),
                    $extension
                );
                $proofPath = $this->manualPaymentProof->storeAs('payment-proofs', $proofName, 'local');
            }

            $completedAt = \Carbon\Carbon::parse($this->manualPaymentDate)->setTime(
                now()->hour,
                now()->minute,
                now()->second
            );

            // Create the completed payment record, honestly labelled as a bank transfer
            $payment = \App\Models\PayrollPayment::create([
                'payroll_submission_id' => $this->submission->id,
                'payment_method' => 'bank_transfer',
                'payment_type' => 'manual',
                'bank_name' => $this->manualPaymentBank,
                'transaction_id' => $this->manualPaymentReference,
                'amount' => $this->manualPaymentAmount,
                'status' => 'completed',
                'completed_at' => $completedAt,
                'payment_proof_path' => $proofPath,
                'payment_proof_name' => $proofName,
                'recorded_by' => auth()->id(),
                'payment_response' => json_encode([
                    'source' => 'manual_admin_entry',
                    'recorded_by' => auth()->user()->name,
                    'recorded_by_id' => auth()->id(),
                    'recorded_at' => now()->toDateTimeString(),
                    'payment_date' => $this->manualPaymentDate,
                    'bank_name' => $this->manualPaymentBank,
                    'reference' => $this->manualPaymentReference,
                    'notes' => $this->manualPaymentNotes,
                ]),
            ]);

            // Flip the submission to paid using the actual date money was received
            $this->submission->update([
                'status' => 'paid',
                'paid_at' => $completedAt,
            ]);

            // Generate the official receipt number now that it's paid
            if (! $this->submission->hasTaxInvoice()) {
                $this->submission->generateTaxInvoiceNumber();
            }

            // Audit trail
            $this->logPaymentActivity(
                action: 'recorded_manually',
                description: 'Recorded manual bank transfer of RM '.number_format((float) $this->manualPaymentAmount, 2)." for payroll {$this->submission->month_year} (Ref: {$this->manualPaymentReference}, Bank: {$this->manualPaymentBank})",
                payment: $payment,
                properties: [
                    'submission_id' => $this->submission->id,
                    'amount' => $this->manualPaymentAmount,
                    'period' => $this->submission->month_year,
                    'payment_method' => 'bank_transfer',
                    'reference' => $this->manualPaymentReference,
                    'bank_name' => $this->manualPaymentBank,
                    'payment_date' => $this->manualPaymentDate,
                    'has_proof' => (bool) $proofPath,
                ]
            );

            $this->closeManualPaymentModal();
            $this->mount($this->submission->id); // Refresh data

            Flux::toast(
                variant: 'success',
                heading: 'Payment Recorded',
                text: 'Bank transfer of RM '.number_format((float) $this->manualPaymentAmount, 2).' recorded. The official receipt is now available.'
            );
        } catch (\Exception $e) {
            Flux::toast(
                variant: 'danger',
                heading: 'Failed to Record Payment',
                text: 'Could not record the payment: '.$e->getMessage()
            );

            \Log::error('Manual payment recording failed', [
                'submission_id' => $this->submission->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        } finally {
            $this->isRecordingPayment = false;
        }
    }

    public function downloadPaymentProof()
    {
        $payment = $this->submission->payments()
            ->whereNotNull('payment_proof_path')
            ->latest()
            ->first();

        if (! $payment || ! $payment->payment_proof_path) {
            Flux::toast(variant: 'warning', text: 'No payment proof available.');

            return;
        }

        $filePath = \Storage::disk('local')->path($payment->payment_proof_path);

        if (! file_exists($filePath)) {
            Flux::toast(variant: 'danger', heading: 'File Not Found', text: 'The payment proof file is missing from storage.');

            return;
        }

        return response()->download($filePath, $payment->payment_proof_name ?? basename($filePath));
    }

    public function sendReminder()
    {
        // TODO: Implement send reminder functionality
        Flux::toast(variant: 'success', text: 'Payment reminder sent to contractor!');
    }

    public function exportWorkerList()
    {
        try {
            $spreadsheet = new Spreadsheet;
            $sheet = $spreadsheet->getActiveSheet();

            // Set document properties
            $spreadsheet->getProperties()
                ->setTitle('Worker Payroll List - '.$this->submission->month_year)
                ->setSubject('Worker Payroll Details');

            // Title row
            $sheet->setCellValue('A1', 'PAYROLL SUBMISSION - '.strtoupper($this->submission->month_year));
            $sheet->mergeCells('A1:R1');
            $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
            $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            // Submission info
            $sheet->setCellValue('A2', 'Contractor: '.$this->submission->user->name);
            $sheet->setCellValue('A3', 'CLAB No: '.$this->submission->contractor_clab_no);
            $sheet->setCellValue('A4', 'Submission ID: #PAY'.str_pad($this->submission->id, 6, '0', STR_PAD_LEFT));

            // Payment Status - show PAID if status is 'paid', otherwise show awaiting payment
            $paymentStatus = ($this->submission->status === 'paid') ? 'PAID' : 'AWAITING PAYMENT';
            $sheet->setCellValue('A5', 'Payment Status: '.$paymentStatus);

            // Transaction ID (if payment exists and is completed)
            if ($this->submission->payment && $this->submission->payment->status === 'completed' && $this->submission->payment->transaction_id) {
                $sheet->setCellValue('A6', 'Transaction ID: '.$this->submission->payment->transaction_id);
            }

            $sheet->setCellValue('A7', 'Total Workers: '.$this->stats['total_workers']);

            // Headers (row 9)
            $headers = [
                'A9' => 'No',
                'B9' => 'Worker ID',
                'C9' => 'Worker Name',
                'D9' => 'Passport',
                'E9' => 'SOCSO No.',
                'F9' => 'KWSP No.',
                'G9' => 'Basic Salary (RM)',
                'H9' => 'OT Normal (hrs)',
                'I9' => 'OT Rest (hrs)',
                'J9' => 'OT Public (hrs)',
                'K9' => 'Advance Payment (RM)',
                'L9' => 'Other Deduction (RM)',
                'M9' => 'NPL (days)',
                'N9' => 'Allowance (RM)',
                'O9' => 'Backpay (RM)',
                'P9' => 'Accommodation (RM)',
                'Q9' => 'Medical Claim (RM)',
                'R9' => 'Transaction Details',
            ];

            foreach ($headers as $cell => $value) {
                $sheet->setCellValue($cell, $value);
            }

            // Style headers
            $sheet->getStyle('A9:R9')->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '4F46E5'],
                ],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                'borders' => [
                    'allBorders' => ['borderStyle' => Border::BORDER_THIN],
                ],
            ]);

            // Data rows
            $row = 10;
            $no = 1;
            foreach ($this->workers as $worker) {
                // Aggregate transaction amounts by type
                $txnTotals = [
                    'advance_payment' => 0,
                    'deduction' => 0,
                    'npl' => 0,
                    'allowance' => 0,
                    'backpay' => 0,
                    'accommodation' => 0,
                    'medical_claim' => 0,
                ];
                $transactionDetails = [];
                foreach ($worker->transactions as $txn) {
                    if (array_key_exists($txn->type, $txnTotals)) {
                        $txnTotals[$txn->type] += $txn->amount;
                    }
                    // Build human-readable detail line
                    if ($txn->type === 'allowance') {
                        $transactionDetails[] = "+RM {$txn->amount} (Allowance".($txn->remarks ? ': '.$txn->remarks : '').')';
                    } elseif ($txn->type === 'backpay') {
                        $transactionDetails[] = "+RM {$txn->amount} (Backpay".($txn->remarks ? ': '.$txn->remarks : '').')';
                    } elseif ($txn->type === 'medical_claim') {
                        $transactionDetails[] = "+RM {$txn->amount} (Medical Claim".($txn->remarks ? ': '.$txn->remarks : '').')';
                    } elseif ($txn->type === 'accommodation') {
                        $transactionDetails[] = "-RM {$txn->amount} (Accommodation".($txn->remarks ? ': '.$txn->remarks : '').')';
                    } elseif ($txn->type === 'npl') {
                        $transactionDetails[] = "{$txn->amount} days (NPL".($txn->remarks ? ': '.$txn->remarks : '').')';
                    } elseif ($txn->type === 'advance_payment') {
                        $transactionDetails[] = "-RM {$txn->amount} (Advance".($txn->remarks ? ': '.$txn->remarks : '').')';
                    } elseif ($txn->type === 'deduction') {
                        $label = $txn->description ?? 'Deduction';
                        $transactionDetails[] = "-RM {$txn->amount} ({$label}".($txn->remarks ? ' - '.$txn->remarks : '').')';
                    }
                }

                $sheet->setCellValue('A'.$row, $no++);
                $sheet->setCellValue('B'.$row, $worker->worker_id);
                $sheet->setCellValue('C'.$row, $worker->worker_name);
                $sheet->setCellValue('D'.$row, $worker->worker_passport);
                $sheet->setCellValue('E'.$row, $worker->worker?->wkr_sosco_id ?? '');
                $sheet->setCellValue('F'.$row, $worker->worker?->wkr_kwsp ?? '');
                $sheet->setCellValue('G'.$row, $worker->basic_salary);
                $sheet->setCellValue('H'.$row, $worker->ot_normal_hours ?? 0);
                $sheet->setCellValue('I'.$row, $worker->ot_rest_hours ?? 0);
                $sheet->setCellValue('J'.$row, $worker->ot_public_hours ?? 0);
                $sheet->setCellValue('K'.$row, $txnTotals['advance_payment']);
                $sheet->setCellValue('L'.$row, $txnTotals['deduction']);
                $sheet->setCellValue('M'.$row, $txnTotals['npl']);
                $sheet->setCellValue('N'.$row, $txnTotals['allowance']);
                $sheet->setCellValue('O'.$row, $txnTotals['backpay']);
                $sheet->setCellValue('P'.$row, $txnTotals['accommodation']);
                $sheet->setCellValue('Q'.$row, $txnTotals['medical_claim']);
                $sheet->setCellValue('R'.$row, implode("\n", $transactionDetails));
                $sheet->getStyle('R'.$row)->getAlignment()->setWrapText(true);
                $sheet->getStyle('R'.$row)->getAlignment()->setVertical(Alignment::VERTICAL_TOP);

                // Format currency columns
                foreach (['G', 'K', 'L', 'N', 'O', 'P', 'Q'] as $col) {
                    $sheet->getStyle($col.$row)->getNumberFormat()
                        ->setFormatCode('#,##0.00');
                }

                // Format hours columns
                foreach (['H', 'I', 'J'] as $col) {
                    $sheet->getStyle($col.$row)->getNumberFormat()
                        ->setFormatCode('0.00');
                }

                // Format NPL days column
                $sheet->getStyle('M'.$row)->getNumberFormat()
                    ->setFormatCode('0.0');

                $row++;
            }

            // Total row
            $totalRow = $row;
            $sheet->setCellValue('A'.$totalRow, 'TOTAL');
            $sheet->mergeCells('A'.$totalRow.':F'.$totalRow);
            $sheet->setCellValue('G'.$totalRow, '=SUM(G10:G'.($totalRow - 1).')');
            $sheet->setCellValue('H'.$totalRow, '=SUM(H10:H'.($totalRow - 1).')');
            $sheet->setCellValue('I'.$totalRow, '=SUM(I10:I'.($totalRow - 1).')');
            $sheet->setCellValue('J'.$totalRow, '=SUM(J10:J'.($totalRow - 1).')');
            $sheet->setCellValue('K'.$totalRow, '=SUM(K10:K'.($totalRow - 1).')');
            $sheet->setCellValue('L'.$totalRow, '=SUM(L10:L'.($totalRow - 1).')');
            $sheet->setCellValue('M'.$totalRow, '=SUM(M10:M'.($totalRow - 1).')');
            $sheet->setCellValue('N'.$totalRow, '=SUM(N10:N'.($totalRow - 1).')');
            $sheet->setCellValue('O'.$totalRow, '=SUM(O10:O'.($totalRow - 1).')');
            $sheet->setCellValue('P'.$totalRow, '=SUM(P10:P'.($totalRow - 1).')');
            $sheet->setCellValue('Q'.$totalRow, '=SUM(Q10:Q'.($totalRow - 1).')');
            $sheet->setCellValue('R'.$totalRow, '');

            // Style total row
            $sheet->getStyle('A'.$totalRow.':R'.$totalRow)->applyFromArray([
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E5E7EB'],
                ],
                'borders' => [
                    'allBorders' => ['borderStyle' => Border::BORDER_THIN],
                ],
            ]);

            // Format currency in total row
            foreach (['G', 'K', 'L', 'N', 'O', 'P', 'Q'] as $col) {
                $sheet->getStyle($col.$totalRow)->getNumberFormat()
                    ->setFormatCode('#,##0.00');
            }

            // Format hours in total row
            foreach (['H', 'I', 'J'] as $col) {
                $sheet->getStyle($col.$totalRow)->getNumberFormat()
                    ->setFormatCode('0.00');
            }

            // Format NPL days in total row
            $sheet->getStyle('M'.$totalRow)->getNumberFormat()
                ->setFormatCode('0.0');

            // Auto-size columns
            foreach (range('A', 'R') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }

            // Set a minimum width for Transaction Details column to show wrapped text properly
            $sheet->getColumnDimension('R')->setWidth(50);

            // Freeze panes at header row
            $sheet->freezePane('A10');

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
                text: 'Failed to export worker list: '.$e->getMessage()
            );
        }
    }

    public function openReviewModal()
    {
        if (! $this->submission->canBeReviewed()) {
            Flux::toast(variant: 'warning', text: 'Cannot review this submission.');

            return;
        }

        // Reset form
        $this->reviewFinalAmount = '';
        $this->reviewNotes = $this->submission->admin_notes ?? '';
        $this->calculatedBreakdown = null;
        $this->varianceAcknowledged = false;
        $this->showReviewModal = true;
    }

    /**
     * Differences the admin should see before approving.
     *
     * Two can arise, and they mean different things:
     *  - the uploaded file's total against what the contractor submitted, which
     *    says the two sources disagree about the payroll; and
     *  - the amount being saved against the file's total, which is the admin
     *    overriding the file by hand.
     *
     * Either is legitimate, neither should pass unnoticed.
     */
    public function reviewVariance(): array
    {
        return $this->varianceAgainst(
            $this->calculatedBreakdown !== null ? (float) ($this->calculatedBreakdown['total'] ?? 0) : null,
            $this->reviewFinalAmount
        );
    }

    /**
     * The same comparison for the edit modal.
     *
     * An edit does not have to carry a new file, so the figure the amount is
     * judged against falls back to the itemisation already stored — and, where
     * even that is missing, there is nothing to compare and only the
     * contractor's submission is left.
     */
    public function editVariance(): array
    {
        $fileTotal = match (true) {
            $this->calculatedBreakdown !== null => (float) ($this->calculatedBreakdown['total'] ?? 0),
            $this->submission->hasBreakdownItemisation() => (float) ($this->submission->admin_breakdown['total'] ?? 0),
            default => null,
        };

        // An untouched amount field means the stored amount stands.
        $entered = $this->editPayrollAmount === '' || $this->editPayrollAmount === null
            ? (string) $this->submission->admin_final_amount
            : $this->editPayrollAmount;

        return $this->varianceAgainst($fileTotal, $entered);
    }

    /**
     * Compare a file total and an entered amount against the contractor's
     * submission. Shared by both modals so they cannot drift apart.
     */
    protected function varianceAgainst(?float $fileTotal, $enteredAmount): array
    {
        $submitted = round((float) $this->clientBreakdown['computed_payroll'], 2);

        // Only meaningful once there is a file total to compare with.
        $parsed = $fileTotal !== null;
        $fileTotal = round((float) $fileTotal, 2);

        $entered = is_numeric($enteredAmount) ? round((float) $enteredAmount, 2) : $fileTotal;

        $againstSubmission = $parsed ? round($fileTotal - $submitted, 2) : 0.0;
        $againstFile = $parsed ? round($entered - $fileTotal, 2) : 0.0;

        return [
            'parsed' => $parsed,
            'file_total' => $fileTotal,
            'submitted' => $submitted,
            'entered' => $entered,
            'against_submission' => $againstSubmission,
            'against_file' => $againstFile,
            'has_difference' => $parsed && ($againstSubmission != 0.0 || $againstFile != 0.0),
        ];
    }

    /**
     * A hand-edited amount has to be confirmed again.
     */
    public function updatedReviewFinalAmount()
    {
        $this->varianceAcknowledged = false;
    }

    public function updatedEditPayrollAmount()
    {
        $this->editVarianceAcknowledged = false;
    }

    /**
     * Parse uploaded Excel file and extract payroll amount automatically
     * Reads totals from last row: Gross Salary + EPF + SOCSO + EIS + HRDF
     */
    public function updatedBreakdownFile()
    {
        // Validate file first
        $this->validate([
            'breakdownFile' => 'required|file|mimes:xlsx,xls|max:10240',
        ]);

        try {
            $breakdown = app(BreakdownFileParser::class)
                ->parse($this->breakdownFile->getRealPath());

            $this->calculatedBreakdown = $breakdown;

            // Auto-fill the amount
            $this->reviewFinalAmount = number_format($breakdown['total'], 2, '.', '');

            // A fresh file has to be confirmed on its own merits.
            $this->varianceAcknowledged = false;

            $variance = $this->reviewVariance();

            if ($variance['against_submission'] != 0.0) {
                Flux::toast(
                    variant: 'warning',
                    heading: 'Excel Parsed &mdash; Difference Found',
                    text: 'File total RM '.number_format($variance['file_total'], 2)
                        .' differs from the contractor\'s submission by RM '
                        .number_format(abs($variance['against_submission']), 2)
                        .'. Review the comparison before approving.'
                );
            } else {
                Flux::toast(
                    variant: 'success',
                    heading: 'Excel Parsed Successfully',
                    text: 'Total payroll amount: RM '.number_format($breakdown['total'], 2)
                );
            }

        } catch (BreakdownFileParseException $e) {
            Flux::toast(
                variant: 'danger',
                heading: $e->missingColumns === [] ? 'Header Row Not Found' : 'Invalid Excel Format',
                text: $e->missingColumns === []
                    ? $e->getMessage()
                    : $e->getMessage().'. Found columns: '.implode(', ', $e->foundColumns)
            );

            \Log::warning('Excel parsing failed - invalid format', [
                'submission_id' => $this->submission->id,
                'missing' => $e->missingColumns,
                'found' => $e->foundColumns,
            ]);

            $this->breakdownFile = null;
            $this->calculatedBreakdown = null;
        } catch (\Exception $e) {
            Flux::toast(
                variant: 'danger',
                heading: 'Excel Parsing Failed',
                text: 'Unable to read Excel file: '.$e->getMessage()
            );

            \Log::error('Excel parsing failed during review', [
                'submission_id' => $this->submission->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->breakdownFile = null;
            $this->calculatedBreakdown = null;
        }
    }

    public function closeReviewModal()
    {
        $this->showReviewModal = false;
        $this->resetValidation();
    }

    /**
     * Build the stored name for a breakdown file: Breakdown_<Client>_<Month>_<Year>.
     *
     * The trading name is what identifies the file at a glance, so the legal
     * suffix (Sdn. Bhd., Enterprise and friends) is dropped from it.
     */
    protected function breakdownFileName(string $extension): string
    {
        $clientName = \App\Models\Contractor::find($this->submission->contractor_clab_no)?->ctr_comp_name
            ?: $this->submission->user?->name
            ?: $this->submission->contractor_clab_no;

        $shortName = \App\Models\Contractor::fileNameSlug($clientName);

        if ($shortName === '') {
            $shortName = $this->submission->contractor_clab_no;
        }

        $monthName = date('M', mktime(0, 0, 0, $this->submission->month, 1));

        return sprintf(
            'Breakdown_%s_%s_%s.%s',
            $shortName,
            $monthName,
            $this->submission->year,
            $extension
        );
    }

    public function approveSubmission()
    {
        $this->validate([
            'reviewFinalAmount' => 'required|numeric|min:0.01',
            'breakdownFile' => 'required|file|mimes:xlsx,xls,pdf|max:10240', // 10MB max
            'reviewNotes' => 'nullable|string|max:1000',
        ]);

        // The figures disagree somewhere. Approving is still the admin's call,
        // but it has to be a deliberate one.
        $variance = $this->reviewVariance();

        if ($variance['has_difference'] && ! $this->varianceAcknowledged) {
            $this->addError('varianceAcknowledged', 'Tick the box to confirm you want to approve despite the difference.');

            Flux::toast(
                variant: 'warning',
                heading: 'Difference Not Confirmed',
                text: 'The figures do not match. Check the comparison and confirm before approving.'
            );

            return;
        }

        try {
            $this->isReviewing = true;

            // Generate custom filename: Breakdown_Miqabina_December_2025.xlsx
            $customFileName = $this->breakdownFileName(
                $this->breakdownFile->getClientOriginalExtension()
            );

            // Ensure directory exists
            $directory = 'payroll-breakdowns/'.$this->submission->year.'/'.$this->submission->month;
            $fullDirectoryPath = storage_path('app/'.$directory);

            if (! file_exists($fullDirectoryPath)) {
                mkdir($fullDirectoryPath, 0755, true);
            }

            // Store breakdown file with custom name
            $filePath = $this->breakdownFile->storeAs($directory, $customFileName, 'local');

            // Leave a trace of what was approved over, so the decision is
            // readable later without re-deriving the figures.
            $notes = $this->reviewNotes;

            if ($variance['has_difference']) {
                $notes = trim($notes."\n\nApproved with a known difference: file total RM "
                    .number_format($variance['file_total'], 2).' vs submission RM '
                    .number_format($variance['submitted'], 2)
                    .($variance['against_file'] != 0.0
                        ? '; amount saved RM '.number_format($variance['entered'], 2)
                        : '').'.');
            }

            // Update submission with admin review
            $this->submission->update([
                'status' => 'approved',
                'admin_reviewed_by' => auth()->id(),
                'admin_reviewed_at' => now(),
                'admin_final_amount' => $this->reviewFinalAmount,
                'admin_breakdown' => $this->calculatedBreakdown,
                'admin_notes' => $notes,
                'breakdown_file_path' => $filePath,
                'breakdown_file_name' => $customFileName,
            ]);

            // Check if submission is overdue and apply penalty immediately
            $this->submission->refresh();
            if ($this->submission->isOverdue() && ! $this->submission->has_penalty) {
                $this->submission->updatePenalty();
                $this->submission->refresh();

                Flux::toast(
                    variant: 'warning',
                    heading: 'Late Submission - Penalty Applied',
                    text: 'This is a late submission. 8% penalty (RM '.number_format($this->submission->penalty_amount, 2).') has been automatically applied.'
                );
            } else {
                Flux::toast(
                    variant: 'success',
                    heading: 'Submission Approved',
                    text: 'Submission has been approved with final amount RM '.number_format($this->reviewFinalAmount, 2)
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
                    'error' => $e->getMessage(),
                ]);
                // Don't show error to admin - email failure shouldn't block the approval
            }

            $this->closeReviewModal();
            $this->mount($this->submission->id); // Refresh data

        } catch (\Exception $e) {
            Flux::toast(
                variant: 'danger',
                heading: 'Error',
                text: 'Failed to approve submission: '.$e->getMessage()
            );
        } finally {
            $this->isReviewing = false;
        }
    }

    public function downloadBreakdown()
    {
        // Refresh submission to get latest data
        $this->submission->refresh();

        if (! $this->submission->hasBreakdownFile()) {
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
        if (! file_exists($filePath)) {
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
                text: 'Unable to download the file: '.$e->getMessage()
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

    public function openUploadPayslipModal()
    {
        $this->payslipFile = null;
        $this->showUploadPayslipModal = true;
    }

    public function closeUploadPayslipModal()
    {
        $this->showUploadPayslipModal = false;
        $this->payslipFile = null;
    }

    public function uploadPayslip()
    {
        $this->validate([
            'payslipFile' => 'required|file|mimes:zip,rar,pdf|max:10240', // 10MB max
        ]);

        try {
            // Store the file in private storage
            $fileName = 'payslip_'.$this->submission->id.'_'.now()->format('YmdHis').'.zip';
            $filePath = $this->payslipFile->storeAs('payslips', $fileName, 'local');

            // Update submission with file info
            $this->submission->update([
                'payslip_file_path' => $filePath,
                'payslip_file_name' => $this->payslipFile->getClientOriginalName(),
            ]);

            $this->closeUploadPayslipModal();

            // Send email notification to contractor
            if ($this->submission->user && $this->submission->user->email) {
                Mail::to($this->submission->user->email)->send(new PayslipReady($this->submission));
            }

            Flux::toast(
                variant: 'success',
                heading: 'Success',
                text: 'Payslip file uploaded successfully. Email notification sent to contractor.'
            );

            // Refresh submission to reflect changes
            $this->submission->refresh();
        } catch (\Exception $e) {
            Flux::toast(
                variant: 'danger',
                heading: 'Upload Failed',
                text: 'Failed to upload payslip file: '.$e->getMessage()
            );

            \Log::error('Payslip upload failed', [
                'submission_id' => $this->submission->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function downloadPayslip()
    {
        $this->submission->refresh();

        if (! $this->submission->hasPayslipFile()) {
            Flux::toast(variant: 'warning', text: 'No payslip file available.');

            return;
        }

        $filePath = \Storage::disk('local')->path($this->submission->payslip_file_path);

        if (! file_exists($filePath)) {
            Flux::toast(
                variant: 'danger',
                heading: 'File Not Found',
                text: 'The payslip file is missing from storage.'
            );

            \Log::warning('Payslip file missing from storage', [
                'submission_id' => $this->submission->id,
                'db_file_path' => $this->submission->payslip_file_path,
            ]);

            return;
        }

        try {
            return response()->download($filePath, $this->submission->payslip_file_name);
        } catch (\Exception $e) {
            Flux::toast(
                variant: 'danger',
                heading: 'Download Failed',
                text: 'Unable to download the file: '.$e->getMessage()
            );

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

            // Generate custom filename: Breakdown_Miqabina_December_2025.xlsx
            $customFileName = $this->breakdownFileName(
                $this->newBreakdownFile->getClientOriginalExtension()
            );

            // Ensure directory exists
            $directory = 'payroll-breakdowns/'.$this->submission->year.'/'.$this->submission->month;
            $fullDirectoryPath = storage_path('app/'.$directory);

            if (! file_exists($fullDirectoryPath)) {
                mkdir($fullDirectoryPath, 0755, true);
                \Log::info('Created directory', ['path' => $fullDirectoryPath]);
            }

            // Log before storing
            \Log::info('About to store file', [
                'directory' => $directory,
                'filename' => $customFileName,
                'full_path' => $fullDirectoryPath.'/'.$customFileName,
            ]);

            // Store new breakdown file with custom name - use storeAs directly
            $filePath = $this->newBreakdownFile->storeAs($directory, $customFileName, 'local');

            \Log::info('storeAs() returned', [
                'returned_path' => $filePath,
                'expected_path' => $directory.'/'.$customFileName,
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
            if (! $fileExists) {
                throw new \Exception('File upload verification failed. The file was not properly stored at: '.$fullFilePath);
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
                text: 'Failed to re-upload breakdown file: '.$e->getMessage()
            );
        } finally {
            $this->isReuploading = false;
        }
    }

    public function openEditAmountModal()
    {
        // Reset form fields (don't pre-fill amount - admin chooses what to edit)
        $this->editPayrollAmount = '';
        $this->newBreakdownFile = null;
        $this->editAmountNotes = '';
        $this->calculatedBreakdown = null;
        $this->editVarianceAcknowledged = false;
        $this->showEditAmountModal = true;
    }

    /**
     * Parse uploaded Excel file in Edit modal and extract payroll amount automatically
     * Reads totals from last row: Gross Salary + EPF + SOCSO + EIS + HRDF
     */
    public function updatedNewBreakdownFile()
    {
        // Validate file first
        $this->validate([
            'newBreakdownFile' => 'required|file|mimes:xlsx,xls|max:10240',
        ]);

        try {
            $breakdown = app(BreakdownFileParser::class)
                ->parse($this->newBreakdownFile->getRealPath());

            $this->calculatedBreakdown = $breakdown;

            // Auto-fill the amount
            $this->editPayrollAmount = number_format($breakdown['total'], 2, '.', '');

            // A fresh file has to be confirmed on its own merits.
            $this->editVarianceAcknowledged = false;

            $variance = $this->editVariance();

            if ($variance['against_submission'] != 0.0) {
                Flux::toast(
                    variant: 'warning',
                    heading: 'Excel Parsed - Difference Found',
                    text: 'File total RM '.number_format($variance['file_total'], 2)
                        .' differs from the contractor\x27s submission by RM '
                        .number_format(abs($variance['against_submission']), 2)
                        .'. Review the comparison before saving.'
                );
            } else {
                Flux::toast(
                    variant: 'success',
                    heading: 'Excel Parsed Successfully',
                    text: 'Total payroll amount: RM '.number_format($breakdown['total'], 2)
                );
            }

        } catch (BreakdownFileParseException $e) {
            Flux::toast(
                variant: 'danger',
                heading: $e->missingColumns === [] ? 'Header Row Not Found' : 'Invalid Excel Format',
                text: $e->missingColumns === []
                    ? $e->getMessage()
                    : $e->getMessage().'. Found columns: '.implode(', ', $e->foundColumns)
            );

            \Log::warning('Excel parsing failed - invalid format', [
                'submission_id' => $this->submission->id,
                'missing' => $e->missingColumns,
                'found' => $e->foundColumns,
            ]);

            $this->newBreakdownFile = null;
            $this->calculatedBreakdown = null;
        } catch (\Exception $e) {
            Flux::toast(
                variant: 'danger',
                heading: 'Excel Parsing Failed',
                text: 'Unable to read Excel file: '.$e->getMessage()
            );

            \Log::error('Excel parsing failed during edit', [
                'submission_id' => $this->submission->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->newBreakdownFile = null;
            $this->calculatedBreakdown = null;
        }
    }

    public function closeEditAmountModal()
    {
        $this->showEditAmountModal = false;
        $this->editPayrollAmount = '';
        $this->newBreakdownFile = null;
        $this->editAmountNotes = '';
        $this->resetValidation();
    }

    public function updatePayrollAmount()
    {
        // Validate that at least one change is being made
        $this->validate([
            'editPayrollAmount' => 'nullable|numeric|min:0.01',
            'newBreakdownFile' => 'nullable|file|mimes:xlsx,xls,pdf|max:10240',
            'editAmountNotes' => 'required|string|min:5|max:500',
        ], [
            'editAmountNotes.required' => 'Please provide a reason for your changes.',
            'editAmountNotes.min' => 'Reason must be at least 5 characters.',
        ]);

        // Ensure at least one field is being updated
        if (empty($this->editPayrollAmount) && empty($this->newBreakdownFile)) {
            Flux::toast(
                variant: 'warning',
                text: 'Please update the amount or upload a new file.'
            );

            return;
        }

        // Same rule as the review: an edit that leaves the figures disagreeing
        // has to be confirmed. This one can cancel a live bill, so it matters
        // at least as much.
        $variance = $this->editVariance();

        if ($variance['has_difference'] && ! $this->editVarianceAcknowledged) {
            $this->addError('editVarianceAcknowledged', 'Tick the box to confirm you want to save despite the difference.');

            Flux::toast(
                variant: 'warning',
                heading: 'Difference Not Confirmed',
                text: 'The figures do not match. Check the comparison and confirm before saving.'
            );

            return;
        }

        try {
            $this->isUpdatingAmount = true;

            // Check if there's a pending Billplz payment that needs to be cancelled
            $hasPendingPayment = in_array($this->submission->status, ['pending_payment', 'overdue']);
            $billplzCancelled = false;

            if ($hasPendingPayment && ! empty($this->editPayrollAmount)) {
                // Get the active payment record
                $payment = $this->submission->payment;

                if ($payment && $payment->billplz_bill_id && $payment->status === 'pending') {
                    // Cancel the Billplz bill
                    try {
                        $apiKey = config('services.billplz.api_key');
                        $billId = $payment->billplz_bill_id;

                        $response = \Http::withBasicAuth($apiKey, '')
                            ->delete(config('services.billplz.url').'bills/'.$billId);

                        if ($response->successful()) {
                            // Mark payment as cancelled
                            $payment->update([
                                'status' => 'cancelled',
                                'payment_response' => json_encode([
                                    'cancelled_at' => now(),
                                    'cancelled_by' => auth()->user()->name,
                                    'reason' => 'Amount amended by admin',
                                ]),
                            ]);

                            // Update submission status back to approved
                            $this->submission->update(['status' => 'approved']);

                            $billplzCancelled = true;

                            \Log::info('Billplz bill cancelled due to amount amendment', [
                                'submission_id' => $this->submission->id,
                                'bill_id' => $billId,
                                'old_amount' => $this->submission->admin_final_amount,
                                'new_amount' => $this->editPayrollAmount,
                            ]);
                        } else {
                            throw new \Exception('Billplz API returned error: '.$response->body());
                        }
                    } catch (\Exception $e) {
                        \Log::error('Failed to cancel Billplz bill', [
                            'submission_id' => $this->submission->id,
                            'bill_id' => $payment->billplz_bill_id ?? null,
                            'error' => $e->getMessage(),
                        ]);

                        Flux::toast(
                            variant: 'warning',
                            heading: 'Billplz Cancellation Failed',
                            text: 'Could not cancel old payment bill. Please cancel manually in Billplz dashboard.'
                        );
                    }
                }
            }

            $changes = [];
            $updateData = [];

            // Handle amount update
            if (! empty($this->editPayrollAmount)) {
                $oldAmount = $this->submission->admin_final_amount;
                $newAmount = $this->editPayrollAmount;

                $updateData['admin_final_amount'] = $newAmount;
                $changes[] = 'Amount: RM '.number_format($oldAmount, 2).' → RM '.number_format($newAmount, 2);
            }

            // Handle file upload
            if ($this->newBreakdownFile) {
                // Delete old file if it exists
                if ($this->submission->breakdown_file_path && \Storage::disk('local')->exists($this->submission->breakdown_file_path)) {
                    \Storage::disk('local')->delete($this->submission->breakdown_file_path);
                }

                // Generate custom filename: Breakdown_Miqabina_December_2025.xlsx
                $customFileName = $this->breakdownFileName(
                    $this->newBreakdownFile->getClientOriginalExtension()
                );

                // Store file
                $directory = 'payroll-breakdowns/'.$this->submission->year.'/'.$this->submission->month;
                $fullDirectoryPath = storage_path('app/'.$directory);

                if (! file_exists($fullDirectoryPath)) {
                    mkdir($fullDirectoryPath, 0755, true);
                }

                $filePath = $this->newBreakdownFile->storeAs($directory, $customFileName, 'local');

                $updateData['breakdown_file_path'] = $filePath;
                $updateData['breakdown_file_name'] = $customFileName;

                // Keep the stored itemisation in step with the file it came
                // from; a file we could not parse clears the stale one.
                $updateData['admin_breakdown'] = $this->calculatedBreakdown;

                $changes[] = 'File: '.$customFileName;
            }

            // Leave a trace of what was saved over, so the decision is readable
            // later without re-deriving the figures.
            if ($variance['has_difference']) {
                $changes[] = 'Saved with a known difference: file total RM '
                    .number_format($variance['file_total'], 2).' vs submission RM '
                    .number_format($variance['submitted'], 2)
                    .($variance['against_file'] != 0.0
                        ? '; amount saved RM '.number_format($variance['entered'], 2)
                        : '');
            }

            // Append update notes
            $existingNotes = $this->submission->admin_notes ?? '';
            $updateNote = "\n\n[".now()->format('Y-m-d H:i:s').'] Updated by '.auth()->user()->name.":\n".implode("\n", $changes)."\nReason: ".$this->editAmountNotes;
            $updateData['admin_notes'] = $existingNotes.$updateNote;

            // Update submission
            $this->submission->update($updateData);

            // Log the change for audit trail
            \Log::info('Payroll submission updated by admin', [
                'submission_id' => $this->submission->id,
                'contractor_clab_no' => $this->submission->contractor_clab_no,
                'changes' => $changes,
                'updated_by' => auth()->user()->name,
                'reason' => $this->editAmountNotes,
            ]);

            $this->closeEditAmountModal();
            $this->mount($this->submission->id); // Refresh data

            if ($billplzCancelled) {
                Flux::toast(
                    variant: 'success',
                    heading: 'Submission Updated & Payment Cancelled',
                    text: 'Changes saved: '.implode(', ', $changes).'. Old Billplz bill cancelled - client must create new payment.'
                );
            } else {
                Flux::toast(
                    variant: 'success',
                    heading: 'Submission Updated',
                    text: 'Changes saved: '.implode(', ', $changes)
                );
            }

        } catch (\Exception $e) {
            Flux::toast(
                variant: 'danger',
                heading: 'Update Failed',
                text: 'Failed to update submission: '.$e->getMessage()
            );

            \Log::error('Payroll submission update failed', [
                'submission_id' => $this->submission->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        } finally {
            $this->isUpdatingAmount = false;
        }
    }

    /**
     * Build the client payment breakdown.
     *
     * The arithmetic lives in ClientBreakdownBuilder so the client-facing
     * pages bill from exactly the same figures this screen reviews.
     */
    public function getClientBreakdownProperty(): array
    {
        return (new \App\Services\ClientBreakdownBuilder)->build($this->submission, collect($this->workers));
    }

    public function render()
    {
        // Refresh submission from database to get latest payment data
        $this->submission->refresh();

        return view('livewire.admin.salary-detail', [
            'clientBreakdown' => $this->clientBreakdown,
        ]);
    }
}
