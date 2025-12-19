<?php

namespace App\Livewire\Client;

use App\Models\PayrollSubmission;
use App\Services\PayrollService;
use App\Services\ContractWorkerService;
use Livewire\Component;
use Livewire\WithFileUploads;

class TimesheetEdit extends Component
{
    use WithFileUploads;

    protected PayrollService $payrollService;
    protected ContractWorkerService $contractWorkerService;

    public $submissionId;
    public $workers = [];
    public $selectedWorkers = [];
    public $period;
    public $currentSubmission;
    public $errorMessage = '';

    // Worker breakdown file upload
    public $workerBreakdownFile;
    public $existingBreakdownFileName;

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

    public function boot(PayrollService $payrollService, ContractWorkerService $contractWorkerService)
    {
        $this->payrollService = $payrollService;
        $this->contractWorkerService = $contractWorkerService;
    }

    public function mount($id)
    {
        $this->submissionId = $id;
        $this->loadData();
    }

    public function loadData()
    {
        $clabNo = auth()->user()->contractor_clab_no;

        if (!$clabNo) {
            $this->errorMessage = 'No contractor CLAB number assigned to your account. Please contact administrator.';
            return;
        }

        // Load the draft submission with transactions
        $submission = PayrollSubmission::with('workers.transactions')
            ->where('id', $this->submissionId)
            ->where('contractor_clab_no', $clabNo)
            ->where('status', 'draft')
            ->firstOrFail();

        $this->currentSubmission = $submission;

        // Get period info from the draft submission (not current month)
        $targetDate = \Carbon\Carbon::create($submission->year, $submission->month, 1);
        $this->period = [
            'month' => $submission->month,
            'year' => $submission->year,
            'month_name' => $targetDate->format('F'),
            'deadline' => $targetDate->copy()->endOfMonth(),
            'days_until_deadline' => now()->diffInDays($targetDate->copy()->endOfMonth(), false),
        ];

        // Get CLAB number for contract checks
        $clabNo = auth()->user()->contractor_clab_no;

        // Prepare workers data with transactions
        $this->workers = $submission->workers->map(function($draftWorker, $index) use ($clabNo, $submission) {
            // Get worker model to check contract status
            $worker = \App\Models\Worker::find($draftWorker->worker_id);
            $contract = \App\Models\ContractWorker::where('con_wkr_id', $draftWorker->worker_id)
                ->where('con_ctr_clab_no', $clabNo)
                ->orderBy('con_end', 'desc')
                ->first();

            // Check if contract was active during the DRAFT SUBMISSION'S period (not today)
            $payrollPeriodDate = \Carbon\Carbon::create($submission->year, $submission->month, 1);
            $hasActiveContract = $contract &&
                                 $contract->con_end >= $payrollPeriodDate->startOfMonth()->toDateString() &&
                                 $contract->con_start <= $payrollPeriodDate->endOfMonth()->toDateString();

            // Get previous month's OT
            $previousMonth = $submission->month - 1;
            $previousYear = $submission->year;
            if ($previousMonth < 1) {
                $previousMonth = 12;
                $previousYear--;
            }

            $previousMonthPayroll = \App\Models\PayrollWorker::where('worker_id', $draftWorker->worker_id)
                ->whereHas('payrollSubmission', function($q) use ($previousMonth, $previousYear) {
                    $q->where('month', $previousMonth)
                      ->where('year', $previousYear)
                      ->where('status', '!=', 'draft');
                })
                ->first();

            $previousMonthOT = $previousMonthPayroll ? $previousMonthPayroll->total_ot_pay : 0;

            return [
                'index' => $index,
                'worker_id' => $draftWorker->worker_id,
                'worker_name' => $draftWorker->worker_name,
                'worker_passport' => $draftWorker->worker_passport,
                'basic_salary' => $draftWorker->basic_salary,
                'ot_normal_hours' => $draftWorker->ot_normal_hours,
                'ot_rest_hours' => $draftWorker->ot_rest_hours,
                'ot_public_hours' => $draftWorker->ot_public_hours,
                'previous_month_ot' => $previousMonthOT,
                'contract_ended' => !$hasActiveContract,
                'ot_payment_only' => !$hasActiveContract,
                'transactions' => $draftWorker->transactions->map(function($txn) {
                    return [
                        'type' => $txn->type,
                        'amount' => $txn->amount,
                        'remarks' => $txn->remarks
                    ];
                })->toArray(),
                'included' => true,
            ];
        })->values()->toArray();

        // Initialize selected workers (all selected by default)
        $this->selectedWorkers = collect($this->workers)->pluck('worker_id')->toArray();
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

    public function openTransactionModal($workerIndex)
    {
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

        // Update the worker's transactions array directly
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

        // Close modal
        $this->closeTransactionModal();
        \Flux::toast(
            variant: 'success',
            heading: 'Transactions Saved',
            text: "Successfully saved transactions for {$workerName}. Total: Advance RM " . number_format($totalAdvancePayment, 2) . ", Deduction RM " . number_format($totalDeduction, 2)
        );
    }

    public function openOTModal($workerIndex)
    {
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
            text: "Successfully saved OT hours for {$workerName}. Total OT Pay: RM " . number_format($totalOTPay, 2)
        );
    }

    public function toggleAllWorkers()
    {
        if (count($this->selectedWorkers) === count($this->workers)) {
            $this->selectedWorkers = [];
        } else {
            $this->selectedWorkers = collect($this->workers)->pluck('worker_id')->toArray();
        }
    }

    public function updateDraft()
    {
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

        // Proceed with submission
        return $this->saveSubmission('submit');
    }

    public function cancelSubmission()
    {
        $this->showDisclaimerModal = false;
    }

    private function saveSubmission($action)
    {
        // Increase timeout for large payroll submissions
        set_time_limit(env('PHP_MAX_EXECUTION_TIME', 300));

        $clabNo = auth()->user()->contractor_clab_no;

        if (!$clabNo) {
            $this->errorMessage = 'No contractor CLAB number assigned.';
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
                if (!($worker['contract_ended'] ?? false) && $worker['basic_salary'] < 1700) {
                    throw new \Exception("Worker {$worker['worker_name']} must have a basic salary of at least RM 1,700.");
                }
                // Workers with ended contracts must have exactly RM 0 basic salary
                if (($worker['contract_ended'] ?? false) && $worker['basic_salary'] != 0) {
                    throw new \Exception("Worker {$worker['worker_name']} has an ended contract and cannot receive basic salary.");
                }
                // Workers with ended contracts must have OT to be included (no service charge for nothing)
                if (($worker['contract_ended'] ?? false) && in_array($worker['worker_id'], $this->selectedWorkers)) {
                    $totalOT = ($worker['ot_normal_hours'] ?? 0) + ($worker['ot_rest_hours'] ?? 0) + ($worker['ot_public_hours'] ?? 0);
                    if ($totalOT <= 0) {
                        throw new \Exception("Worker {$worker['worker_name']} has ended contract with no overtime. Please unselect this worker as there's nothing to pay.");
                    }
                }
            }
        } catch (\Exception $e) {
            $this->errorMessage = 'Validation failed: ' . $e->getMessage();
            return;
        }

        // Filter only selected workers
        $selectedWorkersData = collect($this->workers)->filter(function($worker) {
            return in_array($worker['worker_id'], $this->selectedWorkers);
        })->toArray();

        if (empty($selectedWorkersData)) {
            $this->errorMessage = 'Please select at least one worker to submit payroll.';
            return;
        }

        try {
            // Update existing draft
            $submission = PayrollSubmission::where('id', $this->submissionId)
                ->where('contractor_clab_no', $clabNo)
                ->where('status', 'draft')
                ->firstOrFail();

            // Delete existing workers and transactions
            foreach ($submission->workers as $worker) {
                $worker->transactions()->delete();
            }
            $submission->workers()->delete();

            // Save ALL workers (not just selected ones) to preserve them in the draft
            // But only calculate totals for selected workers
            $totalAmount = 0;
            $previousMonth = now()->month - 1;
            $previousYear = now()->year;
            if ($previousMonth < 1) {
                $previousMonth = 12;
                $previousYear--;
            }
            $previousSubmission = $this->payrollService->getSubmissionForMonth($clabNo, $previousMonth, $previousYear);
            $previousMonthOtMap = [];
            if ($previousSubmission) {
                foreach ($previousSubmission->workers as $prevWorker) {
                    $previousMonthOtMap[$prevWorker->worker_id] = $prevWorker->total_ot_pay;
                }
            }

            // Save ALL workers to preserve them in the draft, but only count selected ones in total
            foreach ($this->workers as $workerData) {
                $payrollWorker = new \App\Models\PayrollWorker($workerData);
                $payrollWorker->payroll_submission_id = $submission->id;
                $previousMonthOt = $previousMonthOtMap[$workerData['worker_id']] ?? 0;

                // Save worker first (without final calculations)
                $payrollWorker->save();

                // Save transactions BEFORE calculating salary
                if (isset($workerData['transactions']) && is_array($workerData['transactions'])) {
                    foreach ($workerData['transactions'] as $transaction) {
                        $payrollWorker->transactions()->create([
                            'type' => $transaction['type'],
                            'amount' => $transaction['amount'],
                            'remarks' => $transaction['remarks'],
                        ]);
                    }
                }

                // NOW calculate salary with transactions in database
                $payrollWorker->calculateSalary($previousMonthOt);
                $payrollWorker->save();

                // Only add to total if worker is selected
                if (in_array($workerData['worker_id'], $this->selectedWorkers)) {
                    $totalAmount += $payrollWorker->total_payment;
                }
            }

            // Calculate service charge, SST, and grand total (only for selected active workers)
            $selectedWorkerCount = count($this->selectedWorkers);
            // Only charge service fee for active workers (exclude workers with ended contracts)
            $activeSelectedWorkers = collect($this->workers)
                ->filter(function($worker) {
                    return in_array($worker['worker_id'], $this->selectedWorkers) && !($worker['contract_ended'] ?? false);
                })
                ->count();
            $serviceCharge = $activeSelectedWorkers * 200; // RM200 per active worker only
            $sst = $serviceCharge * 0.08; // 8% SST on service charge
            $grandTotal = $totalAmount + $serviceCharge + $sst;

            $submission->update([
                'total_workers' => $selectedWorkerCount,
                'total_amount' => $totalAmount,
                'service_charge' => $serviceCharge,
                'sst' => $sst,
                'grand_total' => $grandTotal,
                'total_with_penalty' => $grandTotal,
            ]);

            if ($action === 'submit') {
                // Convert draft to submitted (pending admin review)
                $submission->update([
                    'status' => 'submitted',
                    'submitted_at' => now(),
                ]);
                return redirect()->route('timesheet')
                    ->with('success', "Draft submitted successfully for {$submission->month_year}. {$selectedWorkerCount} worker(s) included. Awaiting admin review and approval.");
            } else {
                // Keep as draft
                \Flux::toast(
                    variant: 'success',
                    heading: 'Draft Updated',
                    text: "Successfully updated draft with {$selectedWorkerCount} worker(s) included."
                );
                $this->loadData(); // Reload data
            }
        } catch (\Exception $e) {
            \Flux::toast(
                variant: 'danger',
                heading: 'Error',
                text: 'Failed to save timesheet: ' . $e->getMessage()
            );
        }
    }

    public function render()
    {
        return view('livewire.client.timesheet-edit')->layout('components.layouts.app', ['title' => __('Edit Draft Submission')]);
    }
}
