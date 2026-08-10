<?php

namespace App\Livewire\Client;

use App\Services\NplCalculatorService;
use App\Services\OTEntryService;
use App\Services\OutstandingPayrollService;
use App\Traits\LogsActivity;
use Flux;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

#[Title('OT & Transaction Entry')]
class OTEntry extends Component
{
    use LogsActivity, WithFileUploads;

    public $period;

    public $entries = [];

    public $isWithinWindow = false;

    public $isManuallyOpened = false;

    public $hasSubmitted = false;

    public $submissionStatus = [];

    // Transaction management
    public $showTransactionModal = false;

    public $currentWorkerIndex = null;

    public $transactions = [];

    public $newTransactionCategory = 'deduction';

    public $newTransactionType = 'advance_payment';

    public $newTransactionAmount = '';

    public $newTransactionRemarks = '';

    // NPL (No-Pay Leave) multi-month entry.
    // NPL is charged against the month the leave was taken, at that month's own
    // daily rate, so one transaction can span several months.

    /** @var array<int, string> selected month keys, "YYYY-MM" */
    public array $nplSelectedMonths = [];

    /** @var array<string, string> month key => NPL days entered */
    public array $nplDaysByMonth = [];

    public float $nplMonthlySalary = 0.0;

    // Import management
    public $showImportModal = false;

    public $importFile;

    public $importData = [];

    public $importErrors = [];

    public $showImportPreview = false;

    public $importMode = 'add'; // 'add' or 'override'

    public string $autoSaveStatus = ''; // '', 'saved', 'error'

    public bool $isLoading = true;

    // Sequential payroll blocking â€” OT entry is hidden until outstanding (unpaid)
    // payroll is settled, mirroring the Timesheet page.
    public bool $isBlocked = false;

    public array $blockReasons = [];

    public int $totalOutstandingCount = 0;

    protected $otEntryService;

    protected NplCalculatorService $nplCalculator;

    public function boot(OTEntryService $otEntryService, NplCalculatorService $nplCalculator)
    {
        $this->otEntryService = $otEntryService;
        $this->nplCalculator = $nplCalculator;
    }

    public function mount()
    {
        // Initialize safe defaults â€” initializeData() runs via wire:init
        $this->period = [
            'entry_month_name' => now()->subMonth()->format('F Y'),
            'submission_month_name' => now()->format('F Y'),
            'window_end' => now()->endOfMonth(),
            'days_remaining' => 0,
        ];
    }

    public function initializeData(): void
    {
        $clabNo = auth()->user()->contractor_clab_no;

        if (! $clabNo) {
            Flux::toast(
                variant: 'danger',
                heading: 'Error',
                text: 'No contractor CLAB number assigned to your account.'
            );
            $this->isLoading = false;

            return;
        }

        // Block OT entry while the contractor has outstanding (unpaid) payroll.
        // They must settle the oldest period first, same rule as the Timesheet page.
        $service = app(OutstandingPayrollService::class);
        $outstandingPeriods = $service->getOutstandingPeriods($clabNo);
        $this->totalOutstandingCount = $service->countOutstandingMonths($outstandingPeriods);

        if ($outstandingPeriods->isNotEmpty()) {
            $this->isBlocked = true;
            $this->blockReasons[] = $service->buildBlockReason($outstandingPeriods->first());
            $this->isLoading = false;

            return;
        }

        // Get entry period information
        $this->period = $this->otEntryService->getEntryPeriod();

        // Use contractor-specific window check instead of default
        $windowStatus = $this->otEntryService->getContractorWindowStatus($clabNo);
        $this->isWithinWindow = $windowStatus['is_open'];
        $this->isManuallyOpened = $windowStatus['is_manually_controlled'];

        // Get or create entries for this contractor
        $this->loadEntries();

        // Check submission status
        $this->hasSubmitted = $this->otEntryService->hasSubmittedEntries($clabNo);
        $this->submissionStatus = $this->otEntryService->getSubmissionStatus($clabNo);

        $this->isLoading = false;
    }

    public function loadEntries()
    {
        $clabNo = auth()->user()->contractor_clab_no;
        $entriesCollection = $this->otEntryService->getOrCreateEntriesForContractor($clabNo);

        // The service returns a plain Support collection when it merges freshly
        // created entries, so only eager-load when Eloquent can do it. Without
        // this the NPL details still resolve, just one query at a time.
        if ($entriesCollection instanceof \Illuminate\Database\Eloquent\Collection) {
            $entriesCollection->load('transactions.nplDetails');
        } else {
            $entriesCollection->each->load('transactions.nplDetails');
        }

        // Convert to array for Livewire with transactions
        $this->entries = $entriesCollection->map(function ($entry) {
            return [
                'id' => $entry->id,
                'worker_id' => $entry->worker_id,
                'worker_name' => $entry->worker_name,
                'worker_passport' => $entry->worker_passport,
                'ot_normal_hours' => $entry->ot_normal_hours,
                'ot_rest_hours' => $entry->ot_rest_hours,
                'ot_public_hours' => $entry->ot_public_hours,
                'status' => $entry->status,
                'is_locked' => $entry->isLocked(),
                'transactions' => $entry->transactions->map(function ($txn) {
                    return [
                        'id' => $txn->id,
                        'type' => $txn->type,
                        'amount' => $txn->amount,
                        'remarks' => $txn->remarks,
                        // Per-month NPL breakdown; empty for every other type and
                        // for legacy NPL rows saved before the per-month rule.
                        'npl_details' => $txn->type === 'npl'
                            ? $txn->nplDetails->map(fn ($detail) => [
                                'npl_year' => $detail->npl_year,
                                'npl_month' => $detail->npl_month,
                                'month_label' => $detail->month_label,
                                'days_in_month' => $detail->days_in_month,
                                'npl_days' => (float) $detail->npl_days,
                                'monthly_salary' => (float) $detail->monthly_salary,
                                'daily_rate' => (float) $detail->daily_rate,
                                'amount' => (float) $detail->amount,
                            ])->toArray()
                            : [],
                    ];
                })->toArray(),
            ];
        })->toArray();
    }

    public function saveDraft()
    {
        $clabNo = auth()->user()->contractor_clab_no;

        // Re-check window status
        if (! $this->otEntryService->isContractorWindowOpen($clabNo)) {
            Flux::toast(
                variant: 'danger',
                heading: 'Window Closed',
                text: 'OT entry window is closed for your contractor. Please contact administrator if you need to make changes.'
            );

            return;
        }

        try {
            $clabNo = auth()->user()->contractor_clab_no;

            foreach ($this->entries as $entry) {
                // Skip if locked
                if ($entry['is_locked']) {
                    continue;
                }

                // Validate OT hours
                $this->validate([
                    'entries.*.ot_normal_hours' => 'nullable|numeric|min:0|max:744',
                    'entries.*.ot_rest_hours' => 'nullable|numeric|min:0|max:744',
                    'entries.*.ot_public_hours' => 'nullable|numeric|min:0|max:744',
                ]);

                // Save entry
                $savedEntry = $this->otEntryService->saveEntry([
                    'contractor_clab_no' => $clabNo,
                    'worker_id' => $entry['worker_id'],
                    'worker_name' => $entry['worker_name'],
                    'worker_passport' => $entry['worker_passport'],
                    'ot_normal_hours' => $entry['ot_normal_hours'] ?? 0,
                    'ot_rest_hours' => $entry['ot_rest_hours'] ?? 0,
                    'ot_public_hours' => $entry['ot_public_hours'] ?? 0,
                ]);

                // Save transactions
                if (isset($entry['transactions']) && is_array($entry['transactions'])) {
                    // Delete existing transactions (and their NPL breakdowns)
                    $this->nplCalculator->deleteTransactionsWithDetails($savedEntry->transactions());

                    // Create new transactions
                    foreach ($entry['transactions'] as $txn) {
                        $savedTxn = $savedEntry->transactions()->create([
                            'type' => $txn['type'],
                            'amount' => $txn['amount'],
                            'remarks' => $txn['remarks'],
                        ]);

                        $this->nplCalculator->syncDetails($savedTxn, $txn['npl_details'] ?? []);
                    }
                }
            }

            Flux::toast(
                variant: 'success',
                heading: 'Saved',
                text: 'OT entries saved as draft successfully.'
            );

            // Log activity
            $this->logOTActivity(
                action: 'saved_draft',
                description: 'Saved OT entries as draft for '.$this->period['entry_month_name'],
                properties: [
                    'entry_period' => $this->period['entry_month_name'],
                    'workers_count' => count($this->entries),
                ]
            );

            // Reload entries
            $this->loadEntries();

        } catch (\Exception $e) {
            Flux::toast(
                variant: 'danger',
                heading: 'Error',
                text: 'Failed to save OT entries: '.$e->getMessage()
            );
        }
    }

    public function submitEntries()
    {
        $clabNo = auth()->user()->contractor_clab_no;

        // Re-check window status
        if (! $this->otEntryService->isContractorWindowOpen($clabNo)) {
            Flux::toast(
                variant: 'danger',
                heading: 'Window Closed',
                text: 'OT entry window is closed for your contractor. Please contact administrator if you need to make changes.'
            );

            return;
        }

        try {
            // First save as draft
            $this->saveDraft();

            // Then submit
            $clabNo = auth()->user()->contractor_clab_no;
            $this->otEntryService->submitEntries($clabNo);

            Flux::toast(
                variant: 'success',
                heading: 'Submitted',
                text: 'OT entries submitted successfully. They are now locked and will be used in your next payroll.'
            );

            // Log activity
            $this->logOTActivity(
                action: 'submitted',
                description: 'Submitted OT entries for '.$this->period['entry_month_name'],
                properties: [
                    'entry_period' => $this->period['entry_month_name'],
                    'workers_count' => count($this->entries),
                ]
            );

            // Reload
            $this->mount();

        } catch (\Exception $e) {
            Flux::toast(
                variant: 'danger',
                heading: 'Error',
                text: 'Failed to submit OT entries: '.$e->getMessage()
            );
        }
    }

    public function updated($propertyName)
    {
        // When transaction category changes, update type to first valid option
        if ($propertyName === 'newTransactionCategory') {
            if ($this->newTransactionCategory === 'deduction') {
                $this->newTransactionType = 'advance_payment';
            } else {
                $this->newTransactionType = 'allowance';
            }

            return;
        }

        // Auto-save when an OT hours field loses focus (wire:model.blur)
        if (preg_match('/^entries\.(\d+)\.ot_(normal|rest|public)_hours$/', $propertyName, $matches)) {
            $this->autoSaveDraft((int) $matches[1]);
        }
    }

    /**
     * Silently save a single entry when its OT hours are changed.
     * Also persists that entry's transactions so nothing is lost on page refresh.
     */
    public function autoSaveDraft(int $index): void
    {
        $clabNo = auth()->user()->contractor_clab_no;

        if (! $this->otEntryService->isContractorWindowOpen($clabNo)) {
            return;
        }

        $entry = $this->entries[$index] ?? null;

        if (! $entry || $entry['is_locked']) {
            return;
        }

        try {
            $savedEntry = $this->otEntryService->saveEntry([
                'contractor_clab_no' => $clabNo,
                'worker_id' => $entry['worker_id'],
                'worker_name' => $entry['worker_name'],
                'worker_passport' => $entry['worker_passport'],
                'ot_normal_hours' => $entry['ot_normal_hours'] ?? 0,
                'ot_rest_hours' => $entry['ot_rest_hours'] ?? 0,
                'ot_public_hours' => $entry['ot_public_hours'] ?? 0,
            ]);

            // Always sync transactions (handles deletions, including removing all)
            $this->nplCalculator->deleteTransactionsWithDetails($savedEntry->transactions());
            foreach ($entry['transactions'] ?? [] as $txn) {
                $savedTxn = $savedEntry->transactions()->create([
                    'type' => $txn['type'],
                    'amount' => $txn['amount'],
                    'remarks' => $txn['remarks'],
                ]);

                $this->nplCalculator->syncDetails($savedTxn, $txn['npl_details'] ?? []);
            }

            $this->autoSaveStatus = 'saved';
        } catch (\Exception $e) {
            $this->autoSaveStatus = 'error';
        }
    }

    public function openTransactionModal($workerIndex)
    {
        \Log::info('openTransactionModal called', ['workerIndex' => $workerIndex]);

        $this->currentWorkerIndex = $workerIndex;
        $this->transactions = $this->entries[$workerIndex]['transactions'] ?? [];
        $this->showTransactionModal = true;
        $this->resetNewTransaction();

        \Log::info('Modal state', [
            'showTransactionModal' => $this->showTransactionModal,
            'currentWorkerIndex' => $this->currentWorkerIndex,
            'transactions_count' => count($this->transactions),
        ]);
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
        $this->resetNplForm();
        $this->resetValidation(['newTransactionAmount', 'newTransactionRemarks', 'nplSelectedMonths', 'nplDaysByMonth']);
    }

    /**
     * Clear the NPL month picker and reload the worker's monthly salary.
     */
    protected function resetNplForm(): void
    {
        $this->nplSelectedMonths = [];
        $this->nplDaysByMonth = [];
        $this->nplMonthlySalary = $this->currentWorkerIndex !== null
            ? $this->nplCalculator->resolveMonthlySalary(
                (int) ($this->entries[$this->currentWorkerIndex]['worker_id'] ?? 0),
                auth()->user()->contractor_clab_no
            )
            : NplCalculatorService::DEFAULT_MONTHLY_SALARY;
    }

    /**
     * Months selectable for NPL: the OT entry month plus the previous six.
     *
     * Months already charged by an existing NPL transaction for this worker are
     * flagged so they can be disabled â€” the same month must not be deducted twice.
     */
    public function getNplSelectableMonthsProperty(): array
    {
        $anchor = $this->nplEntryAnchorMonth();
        $used = $this->nplMonthsAlreadyUsed();

        return collect($this->nplCalculator->selectableMonths($anchor))
            ->map(function ($month) use ($used) {
                $month['already_used'] = in_array($month['key'], $used, true);

                return $month;
            })
            ->all();
    }

    /**
     * Live per-month calculation shown before the transaction is added.
     */
    public function getNplPreviewProperty(): array
    {
        return $this->nplCalculator->calculate(
            $this->nplMonthlySalary,
            $this->nplDaysForSelectedMonths()
        );
    }

    /**
     * Select every month that is not already charged.
     */
    public function selectAllNplMonths(): void
    {
        $this->nplSelectedMonths = collect($this->nplSelectableMonths)
            ->reject(fn ($month) => $month['already_used'])
            ->pluck('key')
            ->all();

        $this->updatedNplSelectedMonths();
    }

    public function clearNplMonths(): void
    {
        $this->nplSelectedMonths = [];
        $this->nplDaysByMonth = [];
    }

    /**
     * Default a newly ticked month to 1 day and drop days for unticked months.
     */
    public function updatedNplSelectedMonths(): void
    {
        foreach ($this->nplSelectedMonths as $key) {
            if (! isset($this->nplDaysByMonth[$key]) || $this->nplDaysByMonth[$key] === '') {
                $this->nplDaysByMonth[$key] = '1';
            }
        }

        $this->nplDaysByMonth = array_intersect_key(
            $this->nplDaysByMonth,
            array_flip($this->nplSelectedMonths)
        );
    }

    /**
     * The month OT is being entered for; NPL may be charged to it or the six
     * months before it.
     */
    protected function nplEntryAnchorMonth(): \Carbon\Carbon
    {
        $entryMonth = $this->period['entry_month_name'] ?? null;

        try {
            return $entryMonth
                ? \Carbon\Carbon::parse('1 '.$entryMonth)->startOfMonth()
                : now()->startOfMonth();
        } catch (\Exception $e) {
            return now()->startOfMonth();
        }
    }

    /**
     * Month keys already charged by this worker's existing NPL transactions.
     *
     * @return array<int, string>
     */
    protected function nplMonthsAlreadyUsed(): array
    {
        if ($this->currentWorkerIndex === null) {
            return [];
        }

        return collect($this->entries[$this->currentWorkerIndex]['transactions'] ?? [])
            ->where('type', 'npl')
            ->flatMap(fn ($txn) => collect($txn['npl_details'] ?? [])
                ->map(fn ($detail) => sprintf('%04d-%02d', $detail['npl_year'], $detail['npl_month'])))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Days entered against each currently selected month.
     *
     * @return array<string, float>
     */
    protected function nplDaysForSelectedMonths(): array
    {
        $days = [];

        foreach ($this->nplSelectedMonths as $key) {
            $days[$key] = (float) ($this->nplDaysByMonth[$key] ?? 0);
        }

        return $days;
    }

    public function addTransaction()
    {
        // NPL is entered as days per month rather than a single ringgit amount,
        // so it validates and builds differently from every other type.
        if ($this->newTransactionType === 'npl') {
            $this->addNplTransaction();

            return;
        }

        // Validate the new transaction
        $validated = $this->validate([
            'newTransactionType' => 'required|in:accommodation,advance_payment,npl,allowance,backpay,medical_claim',
            'newTransactionAmount' => 'required|numeric|min:0.01',
            'newTransactionRemarks' => 'required|string|min:3',
        ], [
            'newTransactionAmount.required' => $this->newTransactionType === 'npl' ? 'Days are required' : 'Amount is required',
            'newTransactionAmount.min' => $this->newTransactionType === 'npl' ? 'Days must be greater than 0' : 'Amount must be greater than 0',
            'newTransactionRemarks.required' => 'Remarks are required',
            'newTransactionRemarks.min' => 'Remarks must be at least 3 characters',
        ]);

        // Enforce RM100 max for accommodation per worker
        if ($validated['newTransactionType'] === 'accommodation') {
            $currentTransactions = $this->entries[$this->currentWorkerIndex]['transactions'] ?? [];
            $existingAccommodation = collect($currentTransactions)
                ->where('type', 'accommodation')
                ->sum('amount');

            if ($existingAccommodation + floatval($validated['newTransactionAmount']) > 100) {
                $remaining = max(0, 100 - $existingAccommodation);
                $this->addError('newTransactionAmount', 'Accommodation cannot exceed RM 100.00 per month. Remaining limit: RM '.number_format($remaining, 2));

                return;
            }
        }

        // Create new transaction array
        $newTransaction = [
            'type' => $validated['newTransactionType'],
            'amount' => floatval($validated['newTransactionAmount']),
            'remarks' => $validated['newTransactionRemarks'],
        ];

        // Update the worker's transactions array
        if ($this->currentWorkerIndex !== null) {
            $currentTransactions = $this->entries[$this->currentWorkerIndex]['transactions'] ?? [];
            $currentTransactions[] = $newTransaction;

            // Force Livewire reactivity
            $entries = $this->entries;
            $entries[$this->currentWorkerIndex]['transactions'] = $currentTransactions;
            $this->entries = $entries;

            // Update modal's local transactions
            $this->transactions = $currentTransactions;

            // Persist immediately so the transaction survives a page refresh even
            // if the modal is closed without clicking "Save Transactions"
            $this->autoSaveDraft($this->currentWorkerIndex);
        }

        // Log activity
        if ($this->currentWorkerIndex !== null) {
            $workerName = $this->entries[$this->currentWorkerIndex]['worker_name'] ?? 'Unknown';
            $this->logOTActivity(
                action: 'transaction_added',
                description: "Added {$validated['newTransactionType']} transaction for {$workerName}",
                properties: [
                    'worker_name' => $workerName,
                    'transaction_type' => $validated['newTransactionType'],
                    'amount' => $validated['newTransactionAmount'],
                ]
            );
        }

        // Reset the form
        $this->resetNewTransaction();
    }

    /**
     * Add a multi-month No-Pay Leave transaction.
     *
     * Each selected month is charged at its own daily rate (monthly salary /
     * actual days in that month) and the per-month breakdown is kept alongside
     * the transaction so payslips and reports can show the working.
     */
    protected function addNplTransaction(): void
    {
        $this->resetValidation();

        $this->validate([
            'nplSelectedMonths' => 'required|array|min:1',
            'newTransactionRemarks' => 'required|string|min:3',
        ], [
            'nplSelectedMonths.required' => 'Select at least one NPL month',
            'nplSelectedMonths.min' => 'Select at least one NPL month',
            'newTransactionRemarks.required' => 'Remarks are required',
            'newTransactionRemarks.min' => 'Remarks must be at least 3 characters',
        ]);

        // Every selected month needs a positive day count.
        foreach ($this->nplSelectedMonths as $key) {
            $days = (float) ($this->nplDaysByMonth[$key] ?? 0);
            $daysInMonth = $this->nplCalculator->daysInMonth(...$this->nplCalculator->parseMonthKey($key));

            if ($days <= 0) {
                $this->addError('nplDaysByMonth.'.$key, 'Enter NPL days greater than 0');

                return;
            }

            if ($days > $daysInMonth) {
                $this->addError('nplDaysByMonth.'.$key, "Cannot exceed {$daysInMonth} days for this month");

                return;
            }
        }

        // A month must not be charged twice for the same worker.
        $alreadyUsed = array_intersect($this->nplSelectedMonths, $this->nplMonthsAlreadyUsed());

        if (! empty($alreadyUsed)) {
            $labels = collect($this->nplSelectableMonths)
                ->whereIn('key', $alreadyUsed)
                ->pluck('label')
                ->implode(', ');

            $this->addError('nplSelectedMonths', "NPL already recorded for {$labels}. Remove the existing transaction first.");

            return;
        }

        $preview = $this->nplPreview;

        if (empty($preview['rows'])) {
            $this->addError('nplSelectedMonths', 'Nothing to deduct â€” enter NPL days.');

            return;
        }

        $newTransaction = [
            'type' => 'npl',
            // `amount` stays the total day count, matching how NPL has always
            // been stored; the ringgit value comes from the breakdown.
            'amount' => $preview['total_days'],
            'remarks' => $this->newTransactionRemarks,
            'npl_details' => $preview['rows'],
        ];

        if ($this->currentWorkerIndex !== null) {
            $currentTransactions = $this->entries[$this->currentWorkerIndex]['transactions'] ?? [];
            $currentTransactions[] = $newTransaction;

            $entries = $this->entries;
            $entries[$this->currentWorkerIndex]['transactions'] = $currentTransactions;
            $this->entries = $entries;

            $this->transactions = $currentTransactions;

            $this->autoSaveDraft($this->currentWorkerIndex);

            $workerName = $this->entries[$this->currentWorkerIndex]['worker_name'] ?? 'Unknown';
            $this->logOTActivity(
                action: 'transaction_added',
                description: "Added npl transaction for {$workerName}",
                properties: [
                    'worker_name' => $workerName,
                    'transaction_type' => 'npl',
                    'total_days' => $preview['total_days'],
                    'total_amount' => $preview['total_amount'],
                    'months' => array_column($preview['rows'], 'month_label'),
                ]
            );
        }

        $this->resetNewTransaction();
    }

    public function removeTransaction($index)
    {
        if ($this->currentWorkerIndex !== null) {
            $currentTransactions = $this->entries[$this->currentWorkerIndex]['transactions'] ?? [];

            // Log before removing
            $removedTransaction = $currentTransactions[$index] ?? null;
            if ($removedTransaction) {
                $workerName = $this->entries[$this->currentWorkerIndex]['worker_name'] ?? 'Unknown';
                $this->logOTActivity(
                    action: 'transaction_removed',
                    description: "Removed {$removedTransaction['type']} transaction for {$workerName}",
                    properties: [
                        'worker_name' => $workerName,
                        'transaction_type' => $removedTransaction['type'],
                        'amount' => $removedTransaction['amount'],
                    ]
                );
            }

            unset($currentTransactions[$index]);
            $currentTransactions = array_values($currentTransactions);

            // Force Livewire reactivity
            $entries = $this->entries;
            $entries[$this->currentWorkerIndex]['transactions'] = $currentTransactions;
            $this->entries = $entries;

            // Update modal's local transactions
            $this->transactions = $currentTransactions;

            // Persist removal to database immediately
            $this->autoSaveDraft($this->currentWorkerIndex);
        }
    }

    public function saveTransactions()
    {
        if ($this->currentWorkerIndex === null) {
            return;
        }

        // Save transactions to the worker
        $this->entries[$this->currentWorkerIndex]['transactions'] = array_values($this->transactions);

        // Persist to database immediately
        $this->autoSaveDraft($this->currentWorkerIndex);

        // Close modal
        $this->closeTransactionModal();
        Flux::toast(
            variant: 'success',
            heading: 'Transactions Saved',
            text: 'Transactions saved successfully for this worker.'
        );
    }

    // Import methods

    /**
     * Column layout of the import template.
     *
     * One row per worker, with a column per transaction type grouped under
     * "Earning" and "Deduction" banners.
     *
     * @var array<string, array{label:string, group:string, width:float}>
     */
    protected const TEMPLATE_COLUMNS = [
        'A' => ['label' => 'Worker Passport', 'group' => 'identity', 'width' => 18.7],
        'B' => ['label' => 'Worker Name', 'group' => 'identity', 'width' => 30],
        'C' => ['label' => 'OT Normal Hours', 'group' => 'identity', 'width' => 18.7],
        'D' => ['label' => 'OT Rest Hours', 'group' => 'identity', 'width' => 16.4],
        'E' => ['label' => 'OT Public Hours', 'group' => 'identity', 'width' => 18.7],
        'F' => ['label' => 'Allowance Transaction Amount (RM)', 'group' => 'earning', 'width' => 20],
        'G' => ['label' => 'Backpay Transaction Amount (RM)', 'group' => 'earning', 'width' => 22.2],
        'H' => ['label' => 'Medical Claim Transaction Amount (RM)', 'group' => 'earning', 'width' => 23.4],
        'I' => ['label' => 'Accommodation Transaction Amount (RM)', 'group' => 'deduction', 'width' => 23.4],
        'J' => ['label' => 'Advance Payment Transaction Amount (RM)', 'group' => 'deduction', 'width' => 23.4],
        'K' => ['label' => 'NPL Month & Year', 'group' => 'deduction', 'width' => 11.7],
        'L' => ['label' => 'NPL Days', 'group' => 'deduction', 'width' => 10.6],
        'M' => ['label' => 'Remarks', 'group' => 'identity', 'width' => 30],
    ];

    /**
     * Header fill colour per column group.
     */
    protected const TEMPLATE_GROUP_FILLS = [
        'identity' => 'D9E1F2',
        'earning' => 'EBF1DE',
        'deduction' => 'F2DBDB',
    ];

    /**
     * Passport used by the worked example in the template. The importer skips
     * this value, so the example row is harmless if left in place.
     */
    protected const TEMPLATE_EXAMPLE_PASSPORT = 'AB012345';

    /**
     * Transaction-amount columns, keyed by the transaction type they create.
     */
    protected const TEMPLATE_AMOUNT_COLUMNS = [
        'F' => 'allowance',
        'G' => 'backpay',
        'H' => 'medical_claim',
        'I' => 'accommodation',
        'J' => 'advance_payment',
    ];

    /**
     * Build the import template, pre-filled with this contractor's workers.
     *
     * Row 1 carries the merged group banners, row 2 the column headers, and
     * row 3 onwards one row per worker with passport and name already filled
     * in — the client only has to enter hours and amounts.
     */
    public function downloadTemplate()
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('OT Entry');

        // --- Row 1: merged group banners -------------------------------------
        $sheet->setCellValue('F1', 'Earning');
        $sheet->mergeCells('F1:H1');
        $sheet->setCellValue('I1', 'Deduction');
        $sheet->mergeCells('I1:L1');

        // Identity/OT/Remarks columns span both header rows.
        foreach (['A', 'B', 'C', 'D', 'E', 'M'] as $col) {
            $sheet->setCellValue($col.'1', self::TEMPLATE_COLUMNS[$col]['label']);
            $sheet->mergeCells($col.'1:'.$col.'2');
        }

        // --- Row 2: per-column headers under the banners ----------------------
        foreach (self::TEMPLATE_COLUMNS as $col => $meta) {
            if (in_array($col, ['A', 'B', 'C', 'D', 'E', 'M'], true)) {
                continue;
            }

            $sheet->setCellValue($col.'2', $meta['label']);
        }

        // --- Header styling ---------------------------------------------------
        foreach (self::TEMPLATE_COLUMNS as $col => $meta) {
            $fill = self::TEMPLATE_GROUP_FILLS[$meta['group']];

            $sheet->getStyle($col.'1:'.$col.'2')->applyFromArray([
                'font' => ['bold' => true],
                'alignment' => [
                    'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                    'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                    'wrapText' => true,
                ],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => $fill],
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                        'color' => ['rgb' => 'B7B7B7'],
                    ],
                ],
            ]);

            $sheet->getColumnDimension($col)->setWidth($meta['width']);
        }

        $sheet->getRowDimension(1)->setRowHeight(20);
        $sheet->getRowDimension(2)->setRowHeight(46);

        // --- Row 3: a worked example covering every column ---------------------
        // The passport is one of the reserved sample values the importer skips,
        // so this row is ignored even if the client forgets to delete it.
        $exampleRow = 3;
        $exampleMonth = $this->nplEntryAnchorMonth()->format('M-Y');

        $example = [
            'A' => self::TEMPLATE_EXAMPLE_PASSPORT,
            'B' => 'JOHN DOE  <-- EXAMPLE ROW, DELETE BEFORE IMPORTING',
            'C' => 10,
            'D' => 8,
            'E' => 4,
            'F' => 100,
            'G' => 200,
            'H' => 50,
            'I' => 80,
            'J' => 230,
            'K' => $exampleMonth,
            'L' => 2,
            'M' => 'Example only - one row per worker, fill just the columns you need',
        ];

        foreach ($example as $col => $value) {
            if (in_array($col, ['A', 'K'], true)) {
                $sheet->setCellValueExplicit(
                    $col.$exampleRow,
                    (string) $value,
                    \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING
                );

                continue;
            }

            $sheet->setCellValue($col.$exampleRow, $value);
        }

        $sheet->getStyle('A'.$exampleRow.':M'.$exampleRow)->applyFromArray([
            'font' => ['italic' => true, 'color' => ['rgb' => '9C6500']],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'FFF2CC'],
            ],
        ]);

        // --- Rows 4+: one pre-filled row per worker ---------------------------
        $row = 4;

        foreach ($this->entries as $entry) {
            $sheet->setCellValueExplicit(
                'A'.$row,
                (string) ($entry['worker_passport'] ?? ''),
                \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING
            );
            $sheet->setCellValue('B'.$row, $entry['worker_name'] ?? '');

            // Passport and name are pre-filled for reference; greying them out
            // signals that they are not meant to be edited.
            $sheet->getStyle('A'.$row.':B'.$row)->applyFromArray([
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'F5F5F5'],
                ],
            ]);

            $row++;
        }

        // Includes the example row, so never below its own row number.
        $lastRow = max($row - 1, $exampleRow);

        // Keep NPL Month as text so Excel does not rewrite "Jul-2025" as a date.
        // Scoped to the filled rows so the sheet's used range stays accurate.
        $sheet->getStyle('K'.$exampleRow.':K'.$lastRow)
            ->getNumberFormat()
            ->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_TEXT);

        // Light grid over the fillable area so the groups stay readable.
        if ($lastRow >= $exampleRow) {
            $sheet->getStyle('A'.$exampleRow.':M'.$lastRow)->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                        'color' => ['rgb' => 'D9D9D9'],
                    ],
                ],
            ]);
        }

        // Headers stay visible while scrolling a long worker list.
        $sheet->freezePane('C3');

        $writer = new Xlsx($spreadsheet);
        $fileName = 'OT_Import_Template_'.date('Y-m-d').'.xlsx';
        $tempFile = tempnam(sys_get_temp_dir(), $fileName);
        $writer->save($tempFile);

        return response()->download($tempFile, $fileName)->deleteFileAfterSend(true);
    }

    public function openImportModal()
    {
        $this->showImportModal = true;
        $this->importFile = null;
        $this->importData = [];
        $this->importErrors = [];
        $this->showImportPreview = false;
        $this->importMode = 'add';
    }

    public function closeImportModal()
    {
        $this->showImportModal = false;
        $this->importFile = null;
        $this->importData = [];
        $this->importErrors = [];
        $this->showImportPreview = false;
        $this->importMode = 'add';
    }

    public function processImport()
    {
        $this->validate([
            'importFile' => 'required|mimes:xlsx,xls,csv|max:2048',
        ]);

        try {
            $spreadsheet = IOFactory::load($this->importFile->getRealPath());
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray();

            $this->importData = [];
            $this->importErrors = [];
            $clabNo = auth()->user()->contractor_clab_no;

            // The current template puts every transaction type on one row per
            // worker. Templates downloaded before that used one row per
            // transaction, so both layouts are accepted.
            $groupedHeaderIndex = $this->detectGroupedTemplate($rows);

            if ($groupedHeaderIndex !== null) {
                $this->parseGroupedImportRows($rows, $groupedHeaderIndex);
            }

            $legacyRows = $groupedHeaderIndex === null ? $rows : [];

            // Process rows and intelligently skip instructions/headers
            $dataStartIndex = 0;
            foreach ($legacyRows as $index => $row) {
                $firstCell = strtoupper(trim($row[0] ?? ''));

                // Skip instruction rows (starts with "INSTRUCTION")
                if (str_starts_with($firstCell, 'INSTRUCTION')) {
                    $dataStartIndex = $index + 1;

                    continue;
                }

                // Skip header rows (contains typical header values)
                if (in_array($firstCell, ['WORKER PASSPORT', 'PASSPORT', 'WORKER_PASSPORT', 'NO', 'NO.'])) {
                    $dataStartIndex = $index + 1;

                    continue;
                }

                // Skip example rows (check for example passport patterns like AB012345, AB012346)
                if (in_array($firstCell, ['AB012345', 'AB012346'])) {
                    $dataStartIndex = $index + 1;

                    continue;
                }

                // If we haven't found data start yet, keep looking
                if ($index < $dataStartIndex) {
                    continue;
                }

                // Skip empty rows
                if (empty(array_filter($row))) {
                    continue;
                }

                $rowNumber = $index + 1; // Excel rows are 1-indexed

                // Get raw values for error messages
                $rawOtNormal = trim($row[2] ?? '');
                $rawOtRest = trim($row[3] ?? '');
                $rawOtPublic = trim($row[4] ?? '');
                $rawTxnAmount = trim($row[6] ?? '');

                // Auto-detect column order: support both (Passport | Name) and (Name | Passport)
                // A passport typically has no spaces and matches alphanumeric patterns (e.g. A12345678)
                $colA = trim($row[0] ?? '');
                $colB = trim($row[1] ?? '');
                $colALooksLikePassport = strlen($colA) > 0 && ! str_contains($colA, ' ');
                $colBLooksLikePassport = strlen($colB) > 0 && ! str_contains($colB, ' ');
                if (! $colALooksLikePassport && $colBLooksLikePassport) {
                    // File has Name | Passport order â€” swap them
                    $passport = $colB;
                    $name = $colA;
                } else {
                    $passport = $colA;
                    $name = $colB;
                }
                $otNormal = $this->sanitizeNumericValue($row[2] ?? '');
                $otRest = $this->sanitizeNumericValue($row[3] ?? '');
                $otPublic = $this->sanitizeNumericValue($row[4] ?? '');
                $txnType = strtolower(trim($row[5] ?? ''));
                $txnAmount = $this->sanitizeNumericValue($row[6] ?? '');
                $txnRemarks = trim($row[7] ?? '');

                // NPL columns: month the leave was taken, and how many days.
                $rawNplMonth = is_string($row[8] ?? null) ? trim($row[8]) : ($row[8] ?? '');
                $rawNplDays = trim((string) ($row[9] ?? ''));
                $nplDays = $this->sanitizeNumericValue($row[9] ?? '');
                [$nplYear, $nplMonth] = $this->nplCalculator->parseNplMonthInput($rawNplMonth);

                $rowHasError = false;

                // Validate required fields
                if (empty($passport)) {
                    $this->importErrors[] = "Row {$rowNumber}: Passport is required";
                    $rowHasError = true;
                }

                if (empty($name)) {
                    $this->importErrors[] = "Row {$rowNumber}: Worker name is required";
                    $rowHasError = true;
                }

                if ($rowHasError) {
                    continue;
                }

                // Check if worker exists in entries
                $workerExists = false;
                foreach ($this->entries as $entry) {
                    if ($entry['worker_passport'] === $passport) {
                        $workerExists = true;
                        break;
                    }
                }

                if (! $workerExists) {
                    $this->importErrors[] = "Row {$rowNumber}: Worker with passport '{$passport}' not found in your worker list";

                    continue;
                }

                // Validate OT Normal Hours
                if (! empty($rawOtNormal)) {
                    if ($otNormal === null) {
                        $this->importErrors[] = "Row {$rowNumber}: Invalid OT Normal Hours value '{$rawOtNormal}'. Must be a valid number";
                        $rowHasError = true;
                    } elseif ($otNormal < 0) {
                        $this->importErrors[] = "Row {$rowNumber}: OT Normal Hours cannot be negative ({$otNormal})";
                        $rowHasError = true;
                    } elseif ($otNormal > 200) {
                        $this->importErrors[] = "Row {$rowNumber}: OT Normal Hours seems too high ({$otNormal}). Maximum allowed is 200 hours";
                        $rowHasError = true;
                    }
                }

                // Validate OT Rest Hours
                if (! empty($rawOtRest)) {
                    if ($otRest === null) {
                        $this->importErrors[] = "Row {$rowNumber}: Invalid OT Rest Hours value '{$rawOtRest}'. Must be a valid number";
                        $rowHasError = true;
                    } elseif ($otRest < 0) {
                        $this->importErrors[] = "Row {$rowNumber}: OT Rest Hours cannot be negative ({$otRest})";
                        $rowHasError = true;
                    } elseif ($otRest > 200) {
                        $this->importErrors[] = "Row {$rowNumber}: OT Rest Hours seems too high ({$otRest}). Maximum allowed is 200 hours";
                        $rowHasError = true;
                    }
                }

                // Validate OT Public Hours
                if (! empty($rawOtPublic)) {
                    if ($otPublic === null) {
                        $this->importErrors[] = "Row {$rowNumber}: Invalid OT Public Hours value '{$rawOtPublic}'. Must be a valid number";
                        $rowHasError = true;
                    } elseif ($otPublic < 0) {
                        $this->importErrors[] = "Row {$rowNumber}: OT Public Hours cannot be negative ({$otPublic})";
                        $rowHasError = true;
                    } elseif ($otPublic > 200) {
                        $this->importErrors[] = "Row {$rowNumber}: OT Public Hours seems too high ({$otPublic}). Maximum allowed is 200 hours";
                        $rowHasError = true;
                    }
                }

                // Validate transaction type if provided
                if (! empty($txnType)) {
                    $validTypes = ['accommodation', 'advance_payment', 'npl', 'allowance', 'backpay', 'medical_claim'];
                    if (! in_array($txnType, $validTypes)) {
                        $this->importErrors[] = "Row {$rowNumber}: Invalid transaction type '{$txnType}'. Must be one of: ".implode(', ', $validTypes);
                        $rowHasError = true;
                    }

                    if ($txnType === 'npl') {
                        // NPL uses the NPL Month / NPL Days columns instead of an amount.
                        $rowHasError = $this->validateImportedNplRow(
                            $rowNumber,
                            $rawNplMonth,
                            $rawNplDays,
                            $nplYear,
                            $nplMonth,
                            $nplDays
                        ) || $rowHasError;
                    } elseif (empty($rawTxnAmount)) {
                        $this->importErrors[] = "Row {$rowNumber}: Transaction amount is required when transaction type is provided";
                        $rowHasError = true;
                    } elseif ($txnAmount === null) {
                        $this->importErrors[] = "Row {$rowNumber}: Invalid transaction amount '{$rawTxnAmount}'. Must be a valid number";
                        $rowHasError = true;
                    } elseif ($txnAmount <= 0) {
                        $this->importErrors[] = "Row {$rowNumber}: Transaction amount must be greater than 0 (got {$txnAmount})";
                        $rowHasError = true;
                    } elseif ($txnType === 'accommodation' && $txnAmount > 100) {
                        $this->importErrors[] = "Row {$rowNumber}: Accommodation cannot exceed RM 100.00 per month (got RM ".number_format($txnAmount, 2).')';
                        $rowHasError = true;
                    } elseif ($txnAmount > 100000) {
                        $this->importErrors[] = "Row {$rowNumber}: Transaction amount seems too high (RM {$txnAmount}). Maximum allowed is RM 100,000";
                        $rowHasError = true;
                    }

                    if (empty($txnRemarks)) {
                        $this->importErrors[] = "Row {$rowNumber}: Transaction remarks is required when transaction type is provided";
                        $rowHasError = true;
                    } elseif (strlen($txnRemarks) < 3) {
                        $this->importErrors[] = "Row {$rowNumber}: Transaction remarks must be at least 3 characters";
                        $rowHasError = true;
                    }
                }

                // Check if row has any meaningful data (OT or transaction)
                $hasOTData = $otNormal !== null || $otRest !== null || $otPublic !== null;
                $hasTransactionData = ! empty($txnType);

                if (! $hasOTData && ! $hasTransactionData) {
                    $this->importErrors[] = "Row {$rowNumber}: No OT hours or transaction data provided for worker '{$name}'";
                    $rowHasError = true;
                }

                if ($rowHasError) {
                    continue;
                }

                // Add to import data
                $this->importData[] = [
                    'passport' => $passport,
                    'name' => $name,
                    'ot_normal' => $otNormal,
                    'ot_rest' => $otRest,
                    'ot_public' => $otPublic,
                    'transaction_type' => $txnType ?: null,
                    // For NPL the "amount" is the day count, matching how NPL
                    // has always been stored.
                    'transaction_amount' => $txnType === 'npl' ? $nplDays : $txnAmount,
                    'transaction_remarks' => $txnRemarks ?: null,
                    'npl_year' => $txnType === 'npl' ? $nplYear : null,
                    'npl_month' => $txnType === 'npl' ? $nplMonth : null,
                    'npl_days' => $txnType === 'npl' ? $nplDays : null,
                    'row_number' => $rowNumber,
                ];
            }

            // One NPL month may only be charged once per worker.
            $this->validateImportedNplDuplicates();

            // Validate aggregate accommodation limit (RM100) per worker across all import rows + existing
            $accommodationByWorker = [];
            foreach ($this->importData as $item) {
                if ($item['transaction_type'] === 'accommodation') {
                    $passport = $item['passport'];
                    if (! isset($accommodationByWorker[$passport])) {
                        $accommodationByWorker[$passport] = ['name' => $item['name'], 'total' => 0];
                    }
                    $accommodationByWorker[$passport]['total'] += $item['transaction_amount'];
                }
            }

            foreach ($accommodationByWorker as $passport => $info) {
                // Include existing accommodation from current entries
                $existingAccommodation = 0;
                foreach ($this->entries as $entry) {
                    if ($entry['worker_passport'] === $passport) {
                        $existingAccommodation = collect($entry['transactions'] ?? [])
                            ->where('type', 'accommodation')
                            ->sum('amount');
                        break;
                    }
                }

                $grandTotal = $existingAccommodation + $info['total'];
                if ($grandTotal > 100) {
                    $this->importErrors[] = "Worker '{$info['name']}' ({$passport}): Total accommodation RM ".number_format($grandTotal, 2).' exceeds RM 100.00 per month limit'.($existingAccommodation > 0 ? ' (existing: RM '.number_format($existingAccommodation, 2).' + import: RM '.number_format($info['total'], 2).')' : '');
                }
            }

            if (empty($this->importData) && empty($this->importErrors)) {
                Flux::toast(
                    variant: 'danger',
                    heading: 'No Data',
                    text: 'No valid data found in the uploaded file.'
                );

                return;
            }

            if (! empty($this->importData)) {
                $this->showImportPreview = true;
            }

            if (! empty($this->importErrors)) {
                // Show specific error details in the toast so user knows exactly what's wrong
                $errorSummary = count($this->importErrors) === 1
                    ? $this->importErrors[0]
                    : count($this->importErrors)." errors found:\n".implode("\n", array_slice($this->importErrors, 0, 3)).(count($this->importErrors) > 3 ? "\n...and ".(count($this->importErrors) - 3).' more. See details below.' : '');

                Flux::toast(
                    variant: 'warning',
                    heading: 'Import Warnings',
                    text: $errorSummary
                );
            }

        } catch (\Exception $e) {
            Flux::toast(
                variant: 'danger',
                heading: 'Import Failed',
                text: 'Failed to process file: '.$e->getMessage()
            );
        }
    }

    /**
     * Detect the grouped (one row per worker) template layout.
     *
     * @return int|null index of the row holding the per-column headers, or null
     *                  when the file uses the older one-row-per-transaction layout
     */
    protected function detectGroupedTemplate(array $rows): ?int
    {
        foreach (array_slice($rows, 0, 6, true) as $index => $row) {
            $cells = array_map(
                fn ($cell) => strtolower(trim((string) $cell)),
                array_slice((array) $row, 0, 13)
            );

            // The legacy layout is unmistakable: it has a Transaction Type column.
            if (in_array('transaction type', $cells, true)) {
                return null;
            }

            foreach ($cells as $cell) {
                if (str_contains($cell, 'allowance transaction amount')) {
                    return $index;
                }
            }
        }

        return null;
    }

    /**
     * Parse the grouped template: one row per worker, one column per
     * transaction type.
     *
     * Each worker row is expanded into the flat importData shape the preview and
     * confirmImport already expect — one entry carrying the OT hours, plus one
     * entry per transaction found on that row.
     */
    protected function parseGroupedImportRows(array $rows, int $headerRowIndex): void
    {
        // $headerRowIndex points at the column-header row (the banner row sits
        // above it), so worker rows begin on the very next index.
        foreach ($rows as $index => $row) {
            if ($index <= $headerRowIndex) {
                continue;
            }

            $row = (array) $row;
            $rowNumber = $index + 1;

            $passport = trim((string) ($row[0] ?? ''));
            $name = trim((string) ($row[1] ?? ''));

            if ($passport === '' && $name === '') {
                continue;
            }

            // Ignore the worked example shipped in the template, so leaving it
            // in place cannot create a phantom worker.
            if (in_array(strtoupper($passport), [self::TEMPLATE_EXAMPLE_PASSPORT, 'AB012346'], true)) {
                continue;
            }

            $otNormal = $this->sanitizeNumericValue($row[2] ?? '');
            $otRest = $this->sanitizeNumericValue($row[3] ?? '');
            $otPublic = $this->sanitizeNumericValue($row[4] ?? '');

            $rawNplMonth = is_string($row[10] ?? null) ? trim($row[10]) : ($row[10] ?? '');
            $rawNplDays = trim((string) ($row[11] ?? ''));
            $sharedRemarks = trim((string) ($row[12] ?? ''));

            $hasOtData = $otNormal !== null || $otRest !== null || $otPublic !== null;
            $hasAmounts = false;

            foreach (array_keys(self::TEMPLATE_AMOUNT_COLUMNS) as $col) {
                $colIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($col) - 1;
                if (trim((string) ($row[$colIndex] ?? '')) !== '') {
                    $hasAmounts = true;
                    break;
                }
            }

            $hasNpl = ($rawNplMonth !== '' && $rawNplMonth !== null) || $rawNplDays !== '';

            // Every worker is pre-filled into the template, so a row left blank
            // simply means "nothing to report" — not an error.
            if (! $hasOtData && ! $hasAmounts && ! $hasNpl) {
                continue;
            }

            $workerExists = collect($this->entries)
                ->contains(fn ($entry) => $entry['worker_passport'] === $passport);

            if (! $workerExists) {
                $this->importErrors[] = "Row {$rowNumber}: Worker with passport '{$passport}' not found in your worker list";

                continue;
            }

            $rowHasError = false;

            foreach ([
                ['OT Normal Hours', $otNormal, trim((string) ($row[2] ?? ''))],
                ['OT Rest Hours', $otRest, trim((string) ($row[3] ?? ''))],
                ['OT Public Hours', $otPublic, trim((string) ($row[4] ?? ''))],
            ] as [$label, $value, $raw]) {
                if ($raw === '') {
                    continue;
                }

                if ($value === null) {
                    $this->importErrors[] = "Row {$rowNumber}: Invalid {$label} value '{$raw}'. Must be a valid number";
                    $rowHasError = true;
                } elseif ($value < 0) {
                    $this->importErrors[] = "Row {$rowNumber}: {$label} cannot be negative ({$value})";
                    $rowHasError = true;
                } elseif ($value > 200) {
                    $this->importErrors[] = "Row {$rowNumber}: {$label} seems too high ({$value}). Maximum allowed is 200 hours";
                    $rowHasError = true;
                }
            }

            // Collect the transaction columns that were filled in.
            $transactions = [];

            foreach (self::TEMPLATE_AMOUNT_COLUMNS as $col => $type) {
                $colIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($col) - 1;
                $raw = trim((string) ($row[$colIndex] ?? ''));

                if ($raw === '') {
                    continue;
                }

                $amount = $this->sanitizeNumericValue($row[$colIndex]);
                $label = self::TEMPLATE_COLUMNS[$col]['label'];

                if ($amount === null) {
                    $this->importErrors[] = "Row {$rowNumber}: Invalid {$label} value '{$raw}'. Must be a valid number";
                    $rowHasError = true;

                    continue;
                }

                if ($amount <= 0) {
                    $this->importErrors[] = "Row {$rowNumber}: {$label} must be greater than 0 (got {$amount})";
                    $rowHasError = true;

                    continue;
                }

                if ($type === 'accommodation' && $amount > 100) {
                    $this->importErrors[] = "Row {$rowNumber}: Accommodation cannot exceed RM 100.00 per month (got RM ".number_format($amount, 2).')';
                    $rowHasError = true;

                    continue;
                }

                if ($amount > 100000) {
                    $this->importErrors[] = "Row {$rowNumber}: {$label} seems too high (RM {$amount}). Maximum allowed is RM 100,000";
                    $rowHasError = true;

                    continue;
                }

                $transactions[] = [
                    'type' => $type,
                    'amount' => $amount,
                    'remarks' => $sharedRemarks !== '' ? $sharedRemarks : 'Imported: '.$this->transactionTypeLabel($type),
                    'npl_year' => null,
                    'npl_month' => null,
                    'npl_days' => null,
                ];
            }

            // NPL needs both of its columns, or neither.
            if ($hasNpl) {
                $nplDays = $this->sanitizeNumericValue($row[11] ?? '');
                [$nplYear, $nplMonth] = $this->nplCalculator->parseNplMonthInput($rawNplMonth);

                if ($this->validateImportedNplRow($rowNumber, $rawNplMonth, $rawNplDays, $nplYear, $nplMonth, $nplDays)) {
                    $rowHasError = true;
                } else {
                    $transactions[] = [
                        'type' => 'npl',
                        'amount' => $nplDays,
                        'remarks' => $sharedRemarks !== '' ? $sharedRemarks : 'Imported: No-Pay Leave',
                        'npl_year' => $nplYear,
                        'npl_month' => $nplMonth,
                        'npl_days' => $nplDays,
                    ];
                }
            }

            if ($rowHasError) {
                continue;
            }

            // OT hours ride on their own entry so confirmImport applies them once.
            if ($hasOtData) {
                $this->importData[] = [
                    'passport' => $passport,
                    'name' => $name,
                    'ot_normal' => $otNormal,
                    'ot_rest' => $otRest,
                    'ot_public' => $otPublic,
                    'transaction_type' => null,
                    'transaction_amount' => null,
                    'transaction_remarks' => null,
                    'npl_year' => null,
                    'npl_month' => null,
                    'npl_days' => null,
                    'row_number' => $rowNumber,
                ];
            }

            foreach ($transactions as $transaction) {
                $this->importData[] = [
                    'passport' => $passport,
                    'name' => $name,
                    'ot_normal' => null,
                    'ot_rest' => null,
                    'ot_public' => null,
                    'transaction_type' => $transaction['type'],
                    'transaction_amount' => $transaction['amount'],
                    'transaction_remarks' => $transaction['remarks'],
                    'npl_year' => $transaction['npl_year'],
                    'npl_month' => $transaction['npl_month'],
                    'npl_days' => $transaction['npl_days'],
                    'row_number' => $rowNumber,
                ];
            }
        }

        // Duplicate NPL months are checked by processImport once both layouts
        // have contributed their rows.
    }

    /**
     * Human-readable name for a transaction type.
     */
    protected function transactionTypeLabel(string $type): string
    {
        return [
            'allowance' => 'Allowance',
            'backpay' => 'Backpay',
            'medical_claim' => 'Medical Claim',
            'accommodation' => 'Accommodation',
            'advance_payment' => 'Advance Payment',
            'npl' => 'No-Pay Leave',
        ][$type] ?? ucfirst(str_replace('_', ' ', $type));
    }

    /**
     * Worker id for a passport in the current entry list.
     */
    protected function workerIdForPassport(string $passport): int
    {
        foreach ($this->entries as $entry) {
            if ($entry['worker_passport'] === $passport) {
                return (int) $entry['worker_id'];
            }
        }

        return 0;
    }

    /**
     * Validate the NPL Month / NPL Days columns of one imported row.
     *
     * @return bool true when the row has an error
     */
    protected function validateImportedNplRow(
        int $rowNumber,
        $rawNplMonth,
        string $rawNplDays,
        ?int $nplYear,
        ?int $nplMonth,
        ?float $nplDays
    ): bool {
        $hasError = false;

        if ($rawNplMonth === '' || $rawNplMonth === null) {
            $this->importErrors[] = "Row {$rowNumber}: NPL Month is required for npl transactions (e.g. Jul-2025)";
            $hasError = true;
        } elseif ($nplYear === null || $nplMonth === null) {
            $this->importErrors[] = "Row {$rowNumber}: Invalid NPL Month '{$rawNplMonth}'. Use a format like Jul-2025";
            $hasError = true;
        } else {
            // Same window the on-screen picker offers.
            $allowed = collect($this->nplCalculator->selectableMonths($this->nplEntryAnchorMonth()))
                ->pluck('key')
                ->all();
            $key = sprintf('%04d-%02d', $nplYear, $nplMonth);

            if (! in_array($key, $allowed, true)) {
                $oldest = \Carbon\Carbon::create($nplYear, $nplMonth, 1)->format('M Y');
                $this->importErrors[] = "Row {$rowNumber}: NPL Month {$oldest} is outside the allowed range (".
                    end($allowed).' to '.reset($allowed).')';
                $hasError = true;
            }
        }

        if ($rawNplDays === '') {
            $this->importErrors[] = "Row {$rowNumber}: NPL Days is required for npl transactions";

            return true;
        }

        if ($nplDays === null) {
            $this->importErrors[] = "Row {$rowNumber}: Invalid NPL Days '{$rawNplDays}'. Must be a valid number";

            return true;
        }

        if ($nplDays <= 0) {
            $this->importErrors[] = "Row {$rowNumber}: NPL Days must be greater than 0 (got {$nplDays})";

            return true;
        }

        // Cannot take more unpaid days than the month actually has.
        if ($nplYear !== null && $nplMonth !== null) {
            $daysInMonth = $this->nplCalculator->daysInMonth($nplYear, $nplMonth);

            if ($nplDays > $daysInMonth) {
                $label = \Carbon\Carbon::create($nplYear, $nplMonth, 1)->format('M Y');
                $this->importErrors[] = "Row {$rowNumber}: NPL Days ({$nplDays}) cannot exceed the {$daysInMonth} days in {$label}";
                $hasError = true;
            }
        }

        return $hasError;
    }

    /**
     * Reject the same NPL month appearing twice for one worker, whether across
     * two import rows or against a transaction the worker already has.
     */
    protected function validateImportedNplDuplicates(): void
    {
        $seen = [];
        $rejected = [];

        foreach ($this->importData as $position => $item) {
            if ($item['transaction_type'] !== 'npl' || empty($item['npl_year'])) {
                continue;
            }

            $key = $item['passport'].'|'.sprintf('%04d-%02d', $item['npl_year'], $item['npl_month']);
            $label = \Carbon\Carbon::create($item['npl_year'], $item['npl_month'], 1)->format('M Y');

            if (isset($seen[$key])) {
                $this->importErrors[] = "Row {$item['row_number']}: NPL for {$label} is already listed on row {$seen[$key]} for '{$item['name']}'";
                $rejected[] = $position;

                continue;
            }

            $seen[$key] = $item['row_number'];

            // Also guard against months already recorded on the worker's entry.
            // In override mode the existing transactions are discarded, so skip.
            if ($this->importMode === 'override') {
                continue;
            }

            foreach ($this->entries as $entry) {
                if ($entry['worker_passport'] !== $item['passport']) {
                    continue;
                }

                $existing = collect($entry['transactions'] ?? [])
                    ->where('type', 'npl')
                    ->flatMap(fn ($txn) => collect($txn['npl_details'] ?? [])
                        ->map(fn ($d) => sprintf('%04d-%02d', $d['npl_year'], $d['npl_month'])))
                    ->all();

                if (in_array(sprintf('%04d-%02d', $item['npl_year'], $item['npl_month']), $existing, true)) {
                    $this->importErrors[] = "Row {$item['row_number']}: NPL for {$label} is already recorded for '{$item['name']}'";
                    $rejected[] = $position;
                }

                break;
            }
        }

        // Drop the rejected rows so a flagged duplicate cannot be imported
        // anyway — the preview lists only what will actually be applied.
        foreach ($rejected as $position) {
            unset($this->importData[$position]);
        }

        $this->importData = array_values($this->importData);
    }

    public function confirmImport()
    {
        if (empty($this->importData)) {
            Flux::toast(
                variant: 'danger',
                heading: 'No Data',
                text: 'No data to import.'
            );

            return;
        }

        try {
            $importedWorkers = 0;
            $importedTransactions = 0;

            // Group import data by passport
            $groupedData = [];
            foreach ($this->importData as $item) {
                $passport = $item['passport'];
                if (! isset($groupedData[$passport])) {
                    $groupedData[$passport] = [
                        'name' => $item['name'],
                        'ot_normal' => $item['ot_normal'],
                        'ot_rest' => $item['ot_rest'],
                        'ot_public' => $item['ot_public'],
                        'transactions' => [],
                    ];
                }

                // Update OT hours if provided
                if ($item['ot_normal'] !== null) {
                    $groupedData[$passport]['ot_normal'] = max($groupedData[$passport]['ot_normal'] ?? 0, $item['ot_normal']);
                }
                if ($item['ot_rest'] !== null) {
                    $groupedData[$passport]['ot_rest'] = max($groupedData[$passport]['ot_rest'] ?? 0, $item['ot_rest']);
                }
                if ($item['ot_public'] !== null) {
                    $groupedData[$passport]['ot_public'] = max($groupedData[$passport]['ot_public'] ?? 0, $item['ot_public']);
                }

                // Add transaction if provided
                if ($item['transaction_type']) {
                    $transaction = [
                        'type' => $item['transaction_type'],
                        'amount' => $item['transaction_amount'],
                        'remarks' => $item['transaction_remarks'],
                    ];

                    // Build the per-month NPL breakdown so imported NPL is
                    // valued the same way as NPL entered on screen.
                    if ($item['transaction_type'] === 'npl' && ! empty($item['npl_year'])) {
                        $monthlySalary = $this->nplCalculator->resolveMonthlySalary(
                            $this->workerIdForPassport($passport),
                            auth()->user()->contractor_clab_no
                        );

                        $transaction['npl_details'] = [
                            $this->nplCalculator->calculateMonth(
                                $monthlySalary,
                                $item['npl_year'],
                                $item['npl_month'],
                                (float) $item['npl_days']
                            ),
                        ];
                    }

                    $groupedData[$passport]['transactions'][] = $transaction;
                }
            }

            // Validate accommodation RM100 limit per worker across all import rows + existing transactions
            $accommodationErrors = [];
            foreach ($groupedData as $passport => $data) {
                $importAccommodation = collect($data['transactions'])
                    ->where('type', 'accommodation')
                    ->sum('amount');

                if ($importAccommodation > 0) {
                    // Get existing accommodation from current entries
                    $existingAccommodation = 0;
                    if ($this->importMode !== 'override') {
                        foreach ($this->entries as $entry) {
                            if ($entry['worker_passport'] === $passport) {
                                $existingAccommodation = collect($entry['transactions'] ?? [])
                                    ->where('type', 'accommodation')
                                    ->sum('amount');
                                break;
                            }
                        }
                    }

                    $totalAccommodation = $existingAccommodation + $importAccommodation;
                    if ($totalAccommodation > 100) {
                        $accommodationErrors[] = "Worker '{$data['name']}' ({$passport}): Total accommodation RM ".number_format($totalAccommodation, 2).' exceeds RM 100.00 limit';
                    }
                }
            }

            if (! empty($accommodationErrors)) {
                Flux::toast(
                    variant: 'danger',
                    heading: 'Accommodation Limit Exceeded',
                    text: implode('. ', $accommodationErrors)
                );

                return;
            }

            // Update entries
            foreach ($this->entries as $index => &$entry) {
                if (isset($groupedData[$entry['worker_passport']])) {
                    $data = $groupedData[$entry['worker_passport']];

                    // Update OT hours based on import mode
                    if ($this->importMode === 'override') {
                        // Override: Replace OT hours
                        if ($data['ot_normal'] !== null) {
                            $entry['ot_normal_hours'] = $data['ot_normal'];
                        }
                        if ($data['ot_rest'] !== null) {
                            $entry['ot_rest_hours'] = $data['ot_rest'];
                        }
                        if ($data['ot_public'] !== null) {
                            $entry['ot_public_hours'] = $data['ot_public'];
                        }
                    } else {
                        // Add: Add to existing OT hours
                        if ($data['ot_normal'] !== null) {
                            $entry['ot_normal_hours'] = floatval($entry['ot_normal_hours'] ?? 0) + $data['ot_normal'];
                        }
                        if ($data['ot_rest'] !== null) {
                            $entry['ot_rest_hours'] = floatval($entry['ot_rest_hours'] ?? 0) + $data['ot_rest'];
                        }
                        if ($data['ot_public'] !== null) {
                            $entry['ot_public_hours'] = floatval($entry['ot_public_hours'] ?? 0) + $data['ot_public'];
                        }
                    }

                    // Handle transactions based on import mode
                    if (! empty($data['transactions'])) {
                        if ($this->importMode === 'override') {
                            // Override: Replace all existing transactions
                            $entry['transactions'] = $data['transactions'];
                        } else {
                            // Add: Append to existing transactions
                            $entry['transactions'] = array_merge($entry['transactions'] ?? [], $data['transactions']);
                        }
                        $importedTransactions += count($data['transactions']);
                    } elseif ($this->importMode === 'override') {
                        // If override mode and no new transactions, clear existing
                        $entry['transactions'] = [];
                    }

                    $importedWorkers++;
                }
            }
            unset($entry); // Break the &$entry reference from the loop above to prevent last-entry corruption

            // Force Livewire reactivity
            $this->entries = $this->entries;

            // Persist all affected entries to database immediately
            foreach ($this->entries as $index => $entry) {
                if (isset($groupedData[$entry['worker_passport']])) {
                    $this->autoSaveDraft($index);
                }
            }

            $this->closeImportModal();

            // Log activity
            $this->logOTActivity(
                action: 'bulk_import',
                description: 'Imported OT and transactions via file upload',
                properties: [
                    'entry_period' => $this->period['entry_month_name'],
                    'workers_count' => $importedWorkers,
                    'transactions_count' => $importedTransactions,
                ]
            );

            Flux::toast(
                variant: 'success',
                heading: 'Import Successful',
                text: "Imported data for {$importedWorkers} workers with {$importedTransactions} transactions."
            );

        } catch (\Exception $e) {
            Flux::toast(
                variant: 'danger',
                heading: 'Import Failed',
                text: 'Failed to import data: '.$e->getMessage()
            );
        }
    }

    /**
     * Log an OT entry activity
     */
    protected function logOTActivity(
        string $action,
        string $description,
        $subject = null,
        ?array $properties = null
    ) {
        return $this->logActivity(
            module: 'ot_entry',
            action: $action,
            description: $description,
            subject: $subject,
            properties: $properties
        );
    }

    /**
     * Sanitize numeric value from import
     * Handles formats like: 10.5, 1,900.00, 1.900,00, removes non-numeric chars
     */
    protected function sanitizeNumericValue($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        // Convert to string
        $value = (string) $value;

        // Remove any whitespace
        $value = trim($value);

        // Remove all characters except digits, dots, commas, and minus sign
        $value = preg_replace('/[^\d.,-]/', '', $value);

        if ($value === '' || $value === '-') {
            return null;
        }

        // Handle different decimal/thousand separator formats
        // Check if comma is used as decimal separator (e.g., 1.900,50 or 10,5)
        if (preg_match('/,\d{1,2}$/', $value)) {
            // Comma is decimal separator (European format)
            $value = str_replace('.', '', $value); // Remove thousand separators
            $value = str_replace(',', '.', $value); // Convert decimal separator
        } else {
            // Dot is decimal separator (US format) or no decimal
            $value = str_replace(',', '', $value); // Remove thousand separators
        }

        return is_numeric($value) ? floatval($value) : null;
    }

    public function render()
    {
        return view('livewire.client.o-t-entry')
            ->layout('components.layouts.app', ['title' => 'OT Entry']);
    }
}
