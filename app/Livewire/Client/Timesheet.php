<?php

namespace App\Livewire\Client;

use App\Models\MonthlyOTEntry;
use App\Models\PayrollSubmission;
use App\Services\ContractWorkerService;
use App\Services\PayrollService;
use App\Traits\LogsActivity;
use Livewire\Component;

class Timesheet extends Component
{
    use LogsActivity;

    protected PayrollService $payrollService;

    protected ContractWorkerService $contractWorkerService;

    public $workers = [];

    public $selectedWorkers = [];

    public $period;

    public $currentSubmission;

    public $stats;

    public $recentSubmissions;

    public $errorMessage = '';

    // Allow viewing specific month/year from query parameters
    public $targetMonth;

    public $targetYear;

    // Blocking logic for outstanding payments/drafts
    public $isBlocked = false;

    public $blockReasons = [];

    public $outstandingDrafts = [];

    public $overduePayments = [];

    public $missingSubmissions = [];

    public $totalOutstandingCount = 0;

    // Transaction management
    public $showTransactionModal = false;

    public $currentWorkerIndex = null;

    public $transactions = [];

    public $newTransactionCategory = 'deduction';

    public $newTransactionType = 'advance_payment';

    public $newTransactionAmount = '';

    public $newTransactionRemarks = '';

    // OT management
    public $showOTModal = false;

    public $otNormalHours = 0;

    public $otRestHours = 0;

    public $otPublicHours = 0;

    // Calculation info modal
    public $showCalculationModal = false;

    // Disclaimer modal
    public $showDisclaimerModal = false;

    public $pendingDraftSubmissionId = null;

    public function boot(PayrollService $payrollService, ContractWorkerService $contractWorkerService)
    {
        $this->payrollService = $payrollService;
        $this->contractWorkerService = $contractWorkerService;
    }

    public function mount()
    {
        // Check if month/year parameters are passed from query string
        $this->targetMonth = request()->query('month');
        $this->targetYear = request()->query('year');

        $this->loadData();
    }

    public function loadData()
    {
        $clabNo = auth()->user()->contractor_clab_no;

        if (! $clabNo) {
            $this->errorMessage = 'No contractor CLAB number assigned to your account. Please contact administrator.';
            \Flux::toast(
                variant: 'danger',
                heading: 'Configuration Error',
                text: 'No contractor CLAB number assigned to your account. Please contact administrator.'
            );

            return;
        }

        // SECURITY: Validate access to requested month/year
        if ($this->targetMonth && $this->targetYear) {
            $allowedPeriod = $this->validatePeriodAccess($clabNo, $this->targetMonth, $this->targetYear);
            if (! $allowedPeriod) {
                // Redirect to the oldest outstanding period or current month
                $this->checkOutstandingIssues($clabNo);
                if ($this->isBlocked && count($this->blockReasons) > 0) {
                    // Redirect to oldest period
                    $this->redirect(route('timesheet', [
                        'month' => $this->blockReasons[0]['redirect_month'],
                        'year' => $this->blockReasons[0]['redirect_year'],
                    ]));

                    return;
                } else {
                    // No outstanding issues, redirect to current month
                    $this->redirect(route('timesheet'));

                    return;
                }
            }
        }

        // Check for outstanding drafts and unpaid invoices (only block for current month)
        // Don't block if viewing a past month to allow catching up
        $isViewingPastMonth = $this->targetMonth && $this->targetYear;
        if (! $isViewingPastMonth) {
            $this->checkOutstandingIssues($clabNo);
        }

        // Determine which month/year to load (specific period or current month)
        if ($this->targetMonth && $this->targetYear) {
            // Load specific period from query parameters
            $targetDate = \Carbon\Carbon::create($this->targetYear, $this->targetMonth, 1);
            $currentMonth = $this->targetMonth;
            $currentYear = $this->targetYear;

            // Get period info for the target month
            $this->period = [
                'month' => $currentMonth,
                'year' => $currentYear,
                'month_name' => $targetDate->format('F'),
                'deadline' => $targetDate->copy()->endOfMonth(),
                'days_until_deadline' => now()->diffInDays($targetDate->copy()->endOfMonth(), false),
            ];
        } else {
            // Get current payroll period info
            $this->period = $this->payrollService->getCurrentPayrollPeriod();
            $currentMonth = now()->month;
            $currentYear = now()->year;
        }

        // Get active contracted workers for the target period
        if ($this->targetMonth && $this->targetYear) {
            // Get workers who had active contracts during the target month
            $targetDate = \Carbon\Carbon::create($this->targetYear, $this->targetMonth, 1);
            $activeWorkers = $this->contractWorkerService->getContractedWorkers($clabNo)
                ->filter(function ($worker) use ($targetDate) {
                    $contract = $worker->contracts()
                        ->where('con_end', '>=', $targetDate->startOfMonth()->toDateString())
                        ->where('con_start', '<=', $targetDate->endOfMonth()->toDateString())
                        ->first();

                    return $contract !== null;
                });

            // Also include workers with pending OT from previous month
            $workersWithPendingOT = $this->contractWorkerService->getWorkersWithPendingOT(
                $clabNo,
                $this->targetMonth,
                $this->targetYear
            );

            // Merge active workers with workers who have pending OT (remove duplicates)
            $activeWorkers = $activeWorkers->merge($workersWithPendingOT)->unique('wkr_id')->values();
        } else {
            // Get currently active contracted workers
            $activeWorkers = $this->contractWorkerService->getActiveContractedWorkers($clabNo);

            // Also include workers with pending OT from previous month (even if contract ended)
            $workersWithPendingOT = $this->contractWorkerService->getWorkersWithPendingOT(
                $clabNo,
                $currentMonth,
                $currentYear
            );

            // Merge active workers with workers who have pending OT (remove duplicates)
            $activeWorkers = $activeWorkers->merge($workersWithPendingOT)->unique('wkr_id')->values();
        }

        // Get ALL submissions for this month to find all submitted workers

        $allSubmissionsThisMonth = PayrollSubmission::where('contractor_clab_no', $clabNo)
            ->where('month', $currentMonth)
            ->where('year', $currentYear)
            ->with('workers')
            ->get();

        // Get IDs of workers already submitted
        $submittedWorkerIds = $allSubmissionsThisMonth->flatMap(function ($submission) {
            return $submission->workers->pluck('worker_id');
        })->unique()->toArray();

        // Filter out workers who have already been submitted
        $remainingWorkers = $activeWorkers->filter(function ($worker) use ($submittedWorkerIds) {
            return ! in_array($worker->wkr_id, $submittedWorkerIds);
        });

        // Determine current submission status
        $currentStatus = 'draft';
        if ($allSubmissionsThisMonth->count() > 0) {
            $hasDraft = $allSubmissionsThisMonth->contains('status', 'draft');
            if ($hasDraft) {
                $currentStatus = 'draft';
            } else {
                $statuses = $allSubmissionsThisMonth->pluck('status')->unique();
                if ($statuses->contains('overdue')) {
                    $currentStatus = 'overdue';
                } elseif ($statuses->contains('pending_payment')) {
                    $currentStatus = 'pending_payment';
                } elseif ($statuses->contains('paid')) {
                    $currentStatus = 'paid';
                }
            }
        }

        $this->currentSubmission = (object) [
            'month' => $currentMonth,
            'year' => $currentYear,
            'status' => $currentStatus,
            'workers' => collect([]),
        ];

        // Get OT entry period (previous month for this payroll)
        $otEntryMonth = $currentMonth - 1;
        $otEntryYear = $currentYear;
        if ($otEntryMonth < 1) {
            $otEntryMonth = 12;
            $otEntryYear--;
        }

        // Check if we're past the OT entry window (after 15th of current month)
        // OT entry window is 1st-15th for previous month's OT
        $today = now();
        $isAfterOTWindow = $today->day > 15;

        // Check if there are submitted OT entries for this period
        $monthlyOTEntries = MonthlyOTEntry::with('transactions')
            ->where('contractor_clab_no', $clabNo)
            ->where('entry_month', $otEntryMonth)
            ->where('entry_year', $otEntryYear)
            ->whereIn('status', ['submitted', 'locked'])
            ->get()
            ->keyBy('worker_id');

        // Prepare workers data
        $this->workers = $remainingWorkers->map(function ($worker, $index) use ($currentMonth, $currentYear, $monthlyOTEntries, $isAfterOTWindow) {
            // Check if worker had an active contract during the payroll period
            // Use the payroll period date, not today's date
            $payrollPeriodDate = \Carbon\Carbon::create($currentYear, $currentMonth, 1);
            $hasActiveContract = $worker->contract_info &&
                                 $worker->contract_info->con_end >= $payrollPeriodDate->startOfMonth()->toDateString() &&
                                 $worker->contract_info->con_start <= $payrollPeriodDate->endOfMonth()->toDateString();

            // Calculate pro-rated basic salary based on contract dates
            $basicSalary = 0;
            $isProRated = false;
            $daysWorked = null;
            $totalDaysInMonth = null;
            $proratingNotes = null;

            if ($hasActiveContract && $worker->contract_info) {
                $proratingService = app(\App\Services\SalaryProratingService::class);
                $proratingResult = $proratingService->calculateProratedSalary(
                    $worker->contract_info->con_start,
                    $worker->contract_info->con_end,
                    $currentMonth,
                    $currentYear,
                    $worker->basic_salary ?? 1700
                );

                $basicSalary = $proratingResult['pro_rated_salary'];
                $isProRated = $proratingResult['is_pro_rated'];
                $daysWorked = $proratingResult['days_worked'];
                $totalDaysInMonth = $proratingResult['total_days'];
                $proratingNotes = $proratingResult['notes'];
            }

            // Check if this worker has monthly OT entry
            $monthlyOTEntry = $monthlyOTEntries->get($worker->wkr_id);
            $hasMonthlyOTEntry = $monthlyOTEntry !== null;

            // Determine if OT/transactions should be locked
            // Lock if: (1) we're after the OT window (after 15th), OR (2) monthly entry exists
            $shouldLockOT = $isAfterOTWindow || $hasMonthlyOTEntry;

            // If monthly OT entry exists, use those hours
            // If after OT window and no monthly entry, use zeros
            // Otherwise, use zeros (for new submissions, default is zero)
            if ($hasMonthlyOTEntry) {
                $otNormalHours = $monthlyOTEntry->ot_normal_hours;
                $otRestHours = $monthlyOTEntry->ot_rest_hours;
                $otPublicHours = $monthlyOTEntry->ot_public_hours;
            } else {
                // No monthly entry: use zeros (either missed window or before window)
                $otNormalHours = 0;
                $otRestHours = 0;
                $otPublicHours = 0;
            }

            // Load transactions based on window and monthly entry status
            $transactions = [];
            if ($hasMonthlyOTEntry && $monthlyOTEntry->transactions) {
                // Use transactions from monthly entry (locked)
                $transactions = $monthlyOTEntry->transactions->map(function ($txn) {
                    return [
                        'type' => $txn->type,
                        'amount' => $txn->amount,
                        'remarks' => $txn->remarks,
                        'locked' => true, // Flag to make read-only
                    ];
                })->toArray();
            }
            // After Dec 15 with no monthly entry: no transactions (missed the window)
            // Before Dec 15 on new submission: no transactions (default empty)

            return [
                'index' => $index,
                'worker_id' => $worker->wkr_id,
                'worker_name' => $worker->name,
                'worker_passport' => $worker->ic_number,
                'basic_salary' => $basicSalary,
                'is_pro_rated' => $isProRated,
                'days_worked' => $daysWorked,
                'total_days_in_month' => $totalDaysInMonth,
                'prorating_notes' => $proratingNotes,
                'ot_normal_hours' => $otNormalHours,
                'ot_rest_hours' => $otRestHours,
                'ot_public_hours' => $otPublicHours,
                'ot_from_monthly_entry' => $shouldLockOT, // Flag to make OT fields read-only
                'advance_payment' => 0,
                'deduction' => 0,
                'transactions' => $transactions,
                'transactions_from_monthly_entry' => $shouldLockOT, // Flag to indicate transactions are locked
                'included' => true,
                'ot_payment_only' => ! $hasActiveContract, // Flag for OT-only payment
                'contract_ended' => ! $hasActiveContract,
            ];
        })->values()->toArray();

        // Initialize selected workers (all selected by default)
        $this->selectedWorkers = collect($this->workers)->pluck('worker_id')->toArray();

        // Update penalties
        $this->payrollService->updateOverduePenalties($clabNo);

        // Get recent submissions
        $this->recentSubmissions = $this->payrollService->getContractorSubmissions($clabNo)->take(5);

        // Get statistics
        $this->stats = $this->payrollService->getContractorStatistics($clabNo, $remainingWorkers->count());
    }

    public function updated($propertyName)
    {
        // Auto-convert empty OT hours to 0
        if (preg_match('/^workers\.(\d+)\.(ot_normal_hours|ot_rest_hours|ot_public_hours)$/', $propertyName, $matches)) {
            $index = $matches[1];
            $field = $matches[2];

            if ($this->workers[$index][$field] === '' || $this->workers[$index][$field] === null) {
                $this->workers[$index][$field] = 0;
            }
        }

        // When transaction category changes, update type to first valid option
        if ($propertyName === 'newTransactionCategory') {
            if ($this->newTransactionCategory === 'deduction') {
                $this->newTransactionType = 'advance_payment';
            } else {
                $this->newTransactionType = 'allowance';
            }
        }
    }

    public function toggleWorker($workerId)
    {
        if (in_array($workerId, $this->selectedWorkers)) {
            $this->selectedWorkers = array_values(array_diff($this->selectedWorkers, [$workerId]));
        } else {
            $this->selectedWorkers[] = $workerId;
        }
    }

    public function toggleAllWorkers()
    {
        if (count($this->selectedWorkers) === count($this->workers)) {
            // Unselect all
            $this->selectedWorkers = [];
        } else {
            // Select all
            $this->selectedWorkers = collect($this->workers)->pluck('worker_id')->toArray();
        }

        // Force Livewire to re-render by resetting the array
        $this->selectedWorkers = array_values($this->selectedWorkers);
    }

    public function openTransactionModal($workerIndex)
    {
        // Check if transactions are locked (from monthly entry)
        if ($this->workers[$workerIndex]['transactions_from_monthly_entry'] ?? false) {
            \Flux::toast(
                variant: 'warning',
                heading: 'Transactions Locked',
                text: 'These transactions were submitted during the OT entry window (1st-15th) and cannot be modified. They will be automatically included in this payroll.'
            );

            return;
        }

        $this->currentWorkerIndex = $workerIndex;
        $this->transactions = $this->workers[$workerIndex]['transactions'] ?? [];
        $this->showTransactionModal = true;
        $this->resetNewTransaction();
    }

    public function closeTransactionModal()
    {
        $this->showTransactionModal = false;
        $this->currentWorkerIndex = null;
        $this->transactions = [];
        $this->resetNewTransaction();
    }

    public function resetNewTransaction()
    {
        $this->newTransactionCategory = 'deduction';
        $this->newTransactionType = 'advance_payment';
        $this->newTransactionAmount = '';
        $this->newTransactionRemarks = '';
        $this->resetValidation(['newTransactionAmount', 'newTransactionRemarks']);
    }

    public function addTransaction()
    {
        // Validate the new transaction
        $validated = $this->validate([
            'newTransactionType' => 'required|in:advance_payment,deduction,npl,allowance',
            'newTransactionAmount' => 'required|numeric|min:0.01',
            'newTransactionRemarks' => 'required|string|min:3',
        ], [
            'newTransactionAmount.required' => $this->newTransactionType === 'npl' ? 'Days are required' : 'Amount is required',
            'newTransactionAmount.min' => $this->newTransactionType === 'npl' ? 'Days must be greater than 0' : 'Amount must be greater than 0',
            'newTransactionRemarks.required' => 'Remarks are required',
            'newTransactionRemarks.min' => 'Remarks must be at least 3 characters',
        ]);

        // Create new transaction array
        $newTransaction = [
            'type' => $validated['newTransactionType'],
            'amount' => floatval($validated['newTransactionAmount']),
            'remarks' => $validated['newTransactionRemarks'],
        ];

        // CRITICAL: Update the worker's transactions array directly
        if ($this->currentWorkerIndex !== null) {
            $currentTransactions = $this->workers[$this->currentWorkerIndex]['transactions'] ?? [];
            $currentTransactions[] = $newTransaction;

            // Force Livewire reactivity by reassigning the entire workers array
            $workers = $this->workers;
            $workers[$this->currentWorkerIndex]['transactions'] = $currentTransactions;
            $this->workers = $workers;

            // Also update the modal's local transactions array
            $this->transactions = $currentTransactions;
        }

        // Log for debugging
        \Log::info('Transaction added', [
            'new_transaction' => $newTransaction,
            'worker_transactions' => $this->workers[$this->currentWorkerIndex]['transactions'] ?? [],
            'modal_transactions' => $this->transactions,
            'count' => count($this->transactions),
        ]);

        // Reset the form
        $this->resetNewTransaction();
    }

    public function removeTransaction($index)
    {
        if ($this->currentWorkerIndex !== null) {
            $currentTransactions = $this->workers[$this->currentWorkerIndex]['transactions'] ?? [];
            unset($currentTransactions[$index]);
            $currentTransactions = array_values($currentTransactions);

            // Force Livewire reactivity by reassigning the entire workers array
            $workers = $this->workers;
            $workers[$this->currentWorkerIndex]['transactions'] = $currentTransactions;
            $this->workers = $workers;

            // Update modal's local transactions
            $this->transactions = $currentTransactions;
        }
    }

    public function saveTransactions()
    {
        if ($this->currentWorkerIndex === null) {
            return;
        }

        // Get worker name before closing
        $workerName = $this->workers[$this->currentWorkerIndex]['worker_name'];

        // Save transactions to the worker - force array re-indexing
        $this->workers[$this->currentWorkerIndex]['transactions'] = array_values($this->transactions);

        // Calculate totals
        $totalAdvancePayment = collect($this->transactions)
            ->where('type', 'advance_payment')
            ->sum('amount');

        $totalDeduction = collect($this->transactions)
            ->where('type', 'deduction')
            ->sum('amount');

        // Update worker totals
        $this->workers[$this->currentWorkerIndex]['advance_payment'] = $totalAdvancePayment;
        $this->workers[$this->currentWorkerIndex]['deduction'] = $totalDeduction;

        // Close modal
        $this->closeTransactionModal();
        \Flux::toast(
            variant: 'success',
            heading: 'Transactions Saved',
            text: "Successfully saved transactions for {$workerName}. Total: Advance RM ".number_format($totalAdvancePayment, 2).', Deduction RM '.number_format($totalDeduction, 2)
        );
    }

    public function openOTModal($workerIndex)
    {
        // Check if OT is locked (from monthly entry)
        if ($this->workers[$workerIndex]['ot_from_monthly_entry'] ?? false) {
            \Flux::toast(
                variant: 'warning',
                heading: 'OT Hours Locked',
                text: 'These overtime hours were submitted during the OT entry window (1st-15th) and cannot be modified. They will be automatically included in this payroll.'
            );

            return;
        }

        $this->currentWorkerIndex = $workerIndex;
        $this->showOTModal = true;

        // Load existing OT hours
        $this->otNormalHours = $this->workers[$workerIndex]['ot_normal_hours'] ?? 0;
        $this->otRestHours = $this->workers[$workerIndex]['ot_rest_hours'] ?? 0;
        $this->otPublicHours = $this->workers[$workerIndex]['ot_public_hours'] ?? 0;
    }

    public function closeOTModal()
    {
        $this->showOTModal = false;
        $this->currentWorkerIndex = null;
        $this->otNormalHours = 0;
        $this->otRestHours = 0;
        $this->otPublicHours = 0;
    }

    public function openCalculationModal()
    {
        $this->showCalculationModal = true;
    }

    public function closeCalculationModal()
    {
        $this->showCalculationModal = false;
    }

    public function saveOT()
    {
        if ($this->currentWorkerIndex === null) {
            return;
        }

        // Get worker name before closing
        $workerName = $this->workers[$this->currentWorkerIndex]['worker_name'];

        // Save OT hours to the worker
        $this->workers[$this->currentWorkerIndex]['ot_normal_hours'] = $this->otNormalHours ?? 0;
        $this->workers[$this->currentWorkerIndex]['ot_rest_hours'] = $this->otRestHours ?? 0;
        $this->workers[$this->currentWorkerIndex]['ot_public_hours'] = $this->otPublicHours ?? 0;

        // Calculate total OT pay
        $totalOTPay = ($this->otNormalHours * 12.26) + ($this->otRestHours * 16.34) + ($this->otPublicHours * 24.51);

        // Close modal
        $this->closeOTModal();
        \Flux::toast(
            variant: 'success',
            heading: 'OT Hours Saved',
            text: "Successfully saved OT hours for {$workerName}. Total OT Pay: RM ".number_format($totalOTPay, 2)
        );
    }

    public function saveDraft()
    {
        \Log::info('saveDraft called', [
            'workers_count' => count($this->workers),
            'selected_workers' => $this->selectedWorkers,
            'workers_data' => $this->workers,
        ]);

        return $this->saveSubmission('draft');
    }

    public function submitForPayment()
    {
        // Show disclaimer modal instead of directly submitting
        $this->showDisclaimerModal = true;
    }

    public function confirmSubmission()
    {
        // Close the modal
        $this->showDisclaimerModal = false;

        // If we're submitting a draft, process it
        if ($this->pendingDraftSubmissionId !== null) {
            $submissionId = $this->pendingDraftSubmissionId;
            $this->pendingDraftSubmissionId = null;

            return $this->processDraftSubmission($submissionId);
        }

        // Otherwise, proceed with normal submission
        return $this->saveSubmission('submit');
    }

    public function cancelSubmission()
    {
        $this->showDisclaimerModal = false;
        $this->pendingDraftSubmissionId = null;
    }

    public function submitDraftForPayment($submissionId)
    {
        // Store the submission ID and show disclaimer modal
        $this->pendingDraftSubmissionId = $submissionId;
        $this->showDisclaimerModal = true;
    }

    private function processDraftSubmission($submissionId)
    {
        $clabNo = auth()->user()->contractor_clab_no;

        if (! $clabNo) {
            \Flux::toast(
                variant: 'danger',
                heading: 'Error',
                text: 'No contractor CLAB number assigned.'
            );

            return;
        }

        try {
            // Find the draft submission
            $submission = PayrollSubmission::where('id', $submissionId)
                ->where('contractor_clab_no', $clabNo)
                ->where('status', 'draft')
                ->firstOrFail();

            // Update status to submitted (pending admin review)
            $submission->update([
                'status' => 'submitted',
                'submitted_at' => now(),
            ]);

            \Flux::toast(
                variant: 'success',
                heading: 'Draft Submitted',
                text: "Draft submitted successfully for {$submission->month_year}. Awaiting admin review and approval."
            );

            // Log activity
            $this->logTimesheetActivity(
                action: 'submitted',
                description: "Submitted payroll timesheet for {$submission->month_year} with {$submission->total_workers} workers (Total: RM ".number_format($submission->client_total, 2).')',
                timesheet: $submission,
                properties: [
                    'period' => $submission->month_year,
                    'workers_count' => $submission->total_workers,
                    'payroll_amount' => $submission->admin_final_amount,
                    'total_amount' => $submission->client_total,
                ]
            );

            // Reload data
            $this->loadData();
        } catch (\Exception $e) {
            \Flux::toast(
                variant: 'danger',
                heading: 'Error',
                text: 'Failed to submit draft: '.$e->getMessage()
            );
        }
    }

    private function saveSubmission($action)
    {
        // Increase timeout for large payroll submissions
        set_time_limit(env('PHP_MAX_EXECUTION_TIME', 300));

        \Log::info('saveSubmission called', ['action' => $action]);

        $clabNo = auth()->user()->contractor_clab_no;

        if (! $clabNo) {
            \Flux::toast(
                variant: 'danger',
                heading: 'Error',
                text: 'No contractor CLAB number assigned.'
            );
            \Log::error('No CLAB number');

            return;
        }

        // Validate
        try {
            $this->validate([
                'workers.*.worker_id' => 'required',
                'workers.*.worker_name' => 'required|string',
                'workers.*.worker_passport' => 'required|string',
                'workers.*.basic_salary' => 'required|numeric|min:0',
                'workers.*.ot_normal_hours' => 'nullable|numeric|min:0',
                'workers.*.ot_rest_hours' => 'nullable|numeric|min:0',
                'workers.*.ot_public_hours' => 'nullable|numeric|min:0',
            ]);

            // Additional validation: workers with active contracts must have minimum RM 1,700
            foreach ($this->workers as $index => $worker) {
                if (! ($worker['contract_ended'] ?? false) && $worker['basic_salary'] < 1700) {
                    throw new \Exception("Worker {$worker['worker_name']} must have a basic salary of at least RM 1,700.");
                }
                // Workers with ended contracts must have exactly RM 0 basic salary
                if (($worker['contract_ended'] ?? false) && $worker['basic_salary'] != 0) {
                    throw new \Exception("Worker {$worker['worker_name']} has an ended contract and cannot receive basic salary.");
                }
                // Workers with ended contracts must have OT to be included (no service charge for nothing)
                // Only validate if worker is selected
                if (($worker['contract_ended'] ?? false) && in_array($worker['worker_id'], $this->selectedWorkers)) {
                    $totalOT = ($worker['ot_normal_hours'] ?? 0) + ($worker['ot_rest_hours'] ?? 0) + ($worker['ot_public_hours'] ?? 0);
                    if ($totalOT <= 0) {
                        throw new \Exception("Worker {$worker['worker_name']} has ended contract with no overtime. Please unselect this worker as there's nothing to pay.");
                    }
                }
            }
        } catch (\Exception $e) {
            \Log::error('Validation failed', ['error' => $e->getMessage()]);
            \Flux::toast(
                variant: 'danger',
                heading: 'Validation Error',
                text: $e->getMessage()
            );

            return;
        }

        // Filter only selected workers
        $selectedWorkersData = collect($this->workers)->filter(function ($worker) {
            return in_array($worker['worker_id'], $this->selectedWorkers);
        })->toArray();

        \Log::info('Selected workers', ['count' => count($selectedWorkersData), 'data' => $selectedWorkersData]);

        if (empty($selectedWorkersData)) {
            \Flux::toast(
                variant: 'warning',
                heading: 'No Workers Selected',
                text: 'Please select at least one worker to submit payroll.'
            );
            \Log::error('No workers selected');

            return;
        }

        try {
            // Get the month and year from the period being viewed
            $month = $this->period['month'];
            $year = $this->period['year'];

            if ($action === 'draft') {
                \Log::info('Calling savePayrollDraft', ['month' => $month, 'year' => $year]);
                $submission = $this->payrollService->savePayrollDraft($clabNo, $selectedWorkersData, $month, $year);
                $workerCount = count($selectedWorkersData);
                \Flux::toast(
                    variant: 'success',
                    heading: 'Draft Saved',
                    text: "Draft saved successfully with {$workerCount} worker(s) included."
                );
                \Log::info('Draft saved', ['submission_id' => $submission->id]);

                // Log activity
                $this->logTimesheetActivity(
                    action: 'draft_saved',
                    description: "Saved payroll timesheet draft for {$submission->month_year} with {$workerCount} workers",
                    timesheet: $submission,
                    properties: [
                        'period' => $submission->month_year,
                        'workers_count' => $workerCount,
                    ]
                );
            } else {
                \Log::info('Calling savePayrollSubmission', ['month' => $month, 'year' => $year]);
                $submission = $this->payrollService->savePayrollSubmission($clabNo, $selectedWorkersData, $month, $year);
                $workerCount = count($selectedWorkersData);

                \Log::info('Submission saved', ['submission_id' => $submission->id]);

                // Log activity
                $this->logTimesheetActivity(
                    action: 'submitted',
                    description: "Submitted payroll timesheet for {$submission->month_year} with {$workerCount} workers (Total: RM ".number_format($submission->client_total, 2).')',
                    timesheet: $submission,
                    properties: [
                        'period' => $submission->month_year,
                        'workers_count' => $workerCount,
                        'payroll_amount' => $submission->admin_final_amount,
                        'total_amount' => $submission->client_total,
                    ]
                );

                // Redirect to invoices page with highlight parameter
                return redirect()->route('invoices', ['highlight' => $submission->id])
                    ->with('success', "Timesheet submitted successfully for {$submission->month_year}. {$workerCount} worker(s) included. Total amount: RM ".number_format($submission->client_total, 2));
            }

            // Reload data
            $this->loadData();
        } catch (\Exception $e) {
            \Log::error('Failed to save', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            \Flux::toast(
                variant: 'danger',
                heading: 'Error',
                text: 'Failed to save timesheet: '.$e->getMessage()
            );
        }
    }

    protected function validatePeriodAccess($clabNo, $month, $year)
    {
        $currentMonth = now()->month;
        $currentYear = now()->year;
        $currentDate = now();

        // Always allow current month
        if ($month == $currentMonth && $year == $currentYear) {
            return true;
        }

        // Collect all outstanding periods
        $outstandingPeriods = collect();

        // 1. Check for draft submissions
        $drafts = PayrollSubmission::where('contractor_clab_no', $clabNo)
            ->where('status', 'draft')
            ->where(function ($query) use ($currentMonth, $currentYear) {
                $query->where('year', '<', $currentYear)
                    ->orWhere(function ($q) use ($currentMonth, $currentYear) {
                        $q->where('year', '=', $currentYear)
                            ->where('month', '<', $currentMonth);
                    });
            })
            ->get();

        foreach ($drafts as $draft) {
            $outstandingPeriods->push([
                'month' => $draft->month,
                'year' => $draft->year,
                'sort_key' => $draft->year * 100 + $draft->month,
            ]);
        }

        // 2. Check for overdue payments (using overdue scope for correct deadline timing)
        $overdue = PayrollSubmission::where('contractor_clab_no', $clabNo)
            ->overdue()
            ->where(function ($query) use ($currentMonth, $currentYear) {
                $query->where('year', '<', $currentYear)
                    ->orWhere(function ($q) use ($currentMonth, $currentYear) {
                        $q->where('year', '=', $currentYear)
                            ->where('month', '<', $currentMonth);
                    });
            })
            ->get();

        foreach ($overdue as $payment) {
            $outstandingPeriods->push([
                'month' => $payment->month,
                'year' => $payment->year,
                'sort_key' => $payment->year * 100 + $payment->month,
            ]);
        }

        // 3. Check for missing submissions
        for ($i = 1; $i <= 6; $i++) {
            $checkDate = $currentDate->copy()->subMonths($i);
            $checkMonth = $checkDate->month;
            $checkYear = $checkDate->year;

            $activeWorkerIds = \App\Models\ContractWorker::where('con_ctr_clab_no', $clabNo)
                ->where('con_end', '>=', $checkDate->startOfMonth()->toDateString())
                ->where('con_start', '<=', $checkDate->endOfMonth()->toDateString())
                ->pluck('con_wkr_id')
                ->unique();

            if ($activeWorkerIds->isEmpty()) {
                continue;
            }

            // Get ALL submissions for that period (to find submitted worker IDs)
            $allSubmissionsForPeriod = PayrollSubmission::where('contractor_clab_no', $clabNo)
                ->where('month', $checkMonth)
                ->where('year', $checkYear)
                ->with('workers')
                ->get();

            // Get IDs of workers already submitted
            $submittedWorkerIds = $allSubmissionsForPeriod->flatMap(function ($submission) {
                return $submission->workers->pluck('worker_id');
            })->unique()->toArray();

            // Find workers that were active but not submitted
            $unsubmittedWorkerIds = $activeWorkerIds->diff($submittedWorkerIds);

            // If there are unsubmitted workers, add this period to outstanding
            if ($unsubmittedWorkerIds->count() > 0) {
                $outstandingPeriods->push([
                    'month' => $checkMonth,
                    'year' => $checkYear,
                    'sort_key' => $checkYear * 100 + $checkMonth,
                ]);
            }
        }

        // If no outstanding periods, allow any past month
        if ($outstandingPeriods->isEmpty()) {
            return true;
        }

        // Sort and get the oldest outstanding period
        $outstandingPeriods = $outstandingPeriods->sortBy('sort_key')->values();
        $oldest = $outstandingPeriods->first();

        // Only allow access to the oldest outstanding period
        return $month == $oldest['month'] && $year == $oldest['year'];
    }

    protected function checkOutstandingIssues($clabNo)
    {
        $currentMonth = now()->month;
        $currentYear = now()->year;
        $currentDate = now();

        // Reset state
        $this->isBlocked = false;
        $this->blockReasons = [];
        $this->outstandingDrafts = [];
        $this->overduePayments = [];
        $this->missingSubmissions = [];

        // Collect ALL outstanding periods (drafts, overdue, missing) in chronological order
        $outstandingPeriods = collect();

        // 1. Check for draft submissions (excluding current month)
        $drafts = PayrollSubmission::where('contractor_clab_no', $clabNo)
            ->where('status', 'draft')
            ->where(function ($query) use ($currentMonth, $currentYear) {
                $query->where('year', '<', $currentYear)
                    ->orWhere(function ($q) use ($currentMonth, $currentYear) {
                        $q->where('year', '=', $currentYear)
                            ->where('month', '<', $currentMonth);
                    });
            })
            ->get();

        foreach ($drafts as $draft) {
            $outstandingPeriods->push([
                'type' => 'draft',
                'month' => $draft->month,
                'year' => $draft->year,
                'month_year' => $draft->month_year,
                'data' => $draft,
                'sort_key' => $draft->year * 100 + $draft->month,
            ]);
        }

        // 2. Check for overdue payments (using overdue scope for correct deadline timing, excluding current month)
        $overdue = PayrollSubmission::where('contractor_clab_no', $clabNo)
            ->overdue()
            ->where(function ($query) use ($currentMonth, $currentYear) {
                $query->where('year', '<', $currentYear)
                    ->orWhere(function ($q) use ($currentMonth, $currentYear) {
                        $q->where('year', '=', $currentYear)
                            ->where('month', '<', $currentMonth);
                    });
            })
            ->get();

        foreach ($overdue as $payment) {
            $outstandingPeriods->push([
                'type' => 'overdue',
                'month' => $payment->month,
                'year' => $payment->year,
                'month_year' => $payment->month_year,
                'data' => $payment,
                'sort_key' => $payment->year * 100 + $payment->month,
            ]);
        }

        // 3. Check for missing submissions (excluding current month)
        for ($i = 1; $i <= 6; $i++) {
            $checkDate = $currentDate->copy()->subMonths($i);
            $month = $checkDate->month;
            $year = $checkDate->year;

            // Get active workers for that month
            $activeWorkerIds = \App\Models\ContractWorker::where('con_ctr_clab_no', $clabNo)
                ->where('con_end', '>=', $checkDate->startOfMonth()->toDateString())
                ->where('con_start', '<=', $checkDate->endOfMonth()->toDateString())
                ->pluck('con_wkr_id')
                ->unique();

            if ($activeWorkerIds->isEmpty()) {
                continue;
            }

            // Get ALL submissions for that period (to find submitted worker IDs)
            $allSubmissionsForPeriod = PayrollSubmission::where('contractor_clab_no', $clabNo)
                ->where('month', $month)
                ->where('year', $year)
                ->with('workers')
                ->get();

            // Get IDs of workers already submitted
            $submittedWorkerIds = $allSubmissionsForPeriod->flatMap(function ($submission) {
                return $submission->workers->pluck('worker_id');
            })->unique()->toArray();

            // Find workers that were active but not submitted
            $unsubmittedWorkerIds = $activeWorkerIds->diff($submittedWorkerIds);

            // If there are unsubmitted workers, add this period to outstanding
            if ($unsubmittedWorkerIds->count() > 0) {
                $outstandingPeriods->push([
                    'type' => 'missing',
                    'month' => $month,
                    'year' => $year,
                    'month_year' => $checkDate->format('F Y'),
                    'total_workers' => $unsubmittedWorkerIds->count(),
                    'sort_key' => $year * 100 + $month,
                ]);
            }
        }

        // Sort by oldest first (ascending)
        $outstandingPeriods = $outstandingPeriods->sortBy('sort_key')->values();

        // Store total count
        $this->totalOutstandingCount = $outstandingPeriods->count();

        // If there are outstanding periods, redirect to the OLDEST one
        if ($outstandingPeriods->count() > 0) {
            $oldest = $outstandingPeriods->first();

            // Store all outstanding for display
            $this->outstandingDrafts = $outstandingPeriods->where('type', 'draft')->pluck('data');
            $this->overduePayments = $outstandingPeriods->where('type', 'overdue')->pluck('data');
            $this->missingSubmissions = $outstandingPeriods->where('type', 'missing')->all();

            // If NOT viewing the oldest period, redirect to it
            if ($this->targetMonth != $oldest['month'] || $this->targetYear != $oldest['year']) {
                $this->isBlocked = true;

                // Determine redirect URL based on status
                $redirectUrl = route('timesheet', ['month' => $oldest['month'], 'year' => $oldest['year']]);
                $actionText = 'Go to '.$oldest['month_year'].' Payroll';

                if ($oldest['type'] === 'overdue') {
                    // For overdue payments, redirect to invoices page
                    $redirectUrl = route('invoices.client');
                    $actionText = 'Pay '.$oldest['month_year'].' Invoice';
                } elseif ($oldest['type'] === 'draft' && isset($oldest['data'])) {
                    // For drafts, redirect to edit page
                    $redirectUrl = route('timesheet.edit', $oldest['data']->id);
                    $actionText = 'Complete '.$oldest['month_year'].' Draft';
                }

                $this->blockReasons[] = [
                    'type' => $oldest['type'],
                    'message' => 'Please complete payroll submissions in chronological order. The next period to complete is '.$oldest['month_year'].'.',
                    'redirect_month' => $oldest['month'],
                    'redirect_year' => $oldest['year'],
                    'redirect_url' => $redirectUrl,
                    'action_text' => $actionText,
                ];
            }
        }
    }

    public function render()
    {
        return view('livewire.client.timesheet')->layout('components.layouts.app', ['title' => __('Timesheet Management')]);
    }
}
