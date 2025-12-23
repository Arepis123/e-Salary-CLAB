<?php

namespace App\Livewire\Client;

use App\Services\OTEntryService;
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

    // Import management
    public $showImportModal = false;

    public $importFile;

    public $importData = [];

    public $importErrors = [];

    public $showImportPreview = false;

    protected $otEntryService;

    public function boot(OTEntryService $otEntryService)
    {
        $this->otEntryService = $otEntryService;
    }

    public function mount()
    {
        $clabNo = auth()->user()->contractor_clab_no;

        if (! $clabNo) {
            Flux::toast(
                variant: 'danger',
                heading: 'Error',
                text: 'No contractor CLAB number assigned to your account.'
            );

            return redirect()->route('dashboard');
        }

        // Get entry period information
        $this->period = $this->otEntryService->getEntryPeriod();

        // Use contractor-specific window check instead of default
        $this->isWithinWindow = $this->otEntryService->isContractorWindowOpen($clabNo);

        // Get or create entries for this contractor
        $this->loadEntries();

        // Check submission status
        $this->hasSubmitted = $this->otEntryService->hasSubmittedEntries($clabNo);
        $this->submissionStatus = $this->otEntryService->getSubmissionStatus($clabNo);
    }

    public function loadEntries()
    {
        $clabNo = auth()->user()->contractor_clab_no;
        $entriesCollection = $this->otEntryService->getOrCreateEntriesForContractor($clabNo);

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
                    // Delete existing transactions
                    $savedEntry->transactions()->delete();

                    // Create new transactions
                    foreach ($entry['transactions'] as $txn) {
                        $savedEntry->transactions()->create([
                            'type' => $txn['type'],
                            'amount' => $txn['amount'],
                            'remarks' => $txn['remarks'],
                        ]);
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
        }
    }

    public function saveTransactions()
    {
        if ($this->currentWorkerIndex === null) {
            return;
        }

        // Save transactions to the worker
        $this->entries[$this->currentWorkerIndex]['transactions'] = array_values($this->transactions);

        // Close modal
        $this->closeTransactionModal();
        Flux::toast(
            variant: 'success',
            heading: 'Transactions Saved',
            text: 'Transactions saved successfully for this worker.'
        );
    }

    // Import methods
    public function downloadTemplate()
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();

        // Set headers
        $headers = [
            'A1' => 'Worker Passport',
            'B1' => 'Worker Name',
            'C1' => 'OT Normal Hours',
            'D1' => 'OT Rest Hours',
            'E1' => 'OT Public Hours',
            'F1' => 'Transaction Type',
            'G1' => 'Transaction Amount',
            'H1' => 'Transaction Remarks',
        ];

        foreach ($headers as $cell => $value) {
            $sheet->setCellValue($cell, $value);
            $sheet->getStyle($cell)->getFont()->setBold(true);
        }

        // Add example data
        $sheet->setCellValue('A2', 'MA841043');
        $sheet->setCellValue('B2', 'JOHN DOE');
        $sheet->setCellValue('C2', '10');
        $sheet->setCellValue('D2', '8');
        $sheet->setCellValue('E2', '0');
        $sheet->setCellValue('F2', 'advance_payment');
        $sheet->setCellValue('G2', '500.00');
        $sheet->setCellValue('H2', 'Advance payment for medical expenses');

        // Add second example with NPL
        $sheet->setCellValue('A3', 'MA841043');
        $sheet->setCellValue('B3', 'JOHN DOE');
        $sheet->setCellValue('C3', '');
        $sheet->setCellValue('D3', '');
        $sheet->setCellValue('E3', '');
        $sheet->setCellValue('F3', 'npl');
        $sheet->setCellValue('G3', '2');
        $sheet->setCellValue('H3', 'No-pay leave for 2 days');

        // Add instructions row
        $sheet->insertNewRowBefore(2, 1);
        $sheet->setCellValue('A2', 'Instructions: Fill passport, name, OT hours. For transactions, use types: advance_payment, deduction, npl, allowance. Leave OT columns empty if adding only transactions. You can have multiple rows for the same worker (for multiple transactions).');
        $sheet->mergeCells('A2:H2');
        $sheet->getStyle('A2')->getFont()->setItalic(true);
        $sheet->getStyle('A2')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('FFFFCC');

        // Auto-size columns
        foreach (range('A', 'H') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Create file
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
    }

    public function closeImportModal()
    {
        $this->showImportModal = false;
        $this->importFile = null;
        $this->importData = [];
        $this->importErrors = [];
        $this->showImportPreview = false;
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

            // Skip header row and instructions row
            $dataRows = array_slice($rows, 2);

            $this->importData = [];
            $this->importErrors = [];
            $clabNo = auth()->user()->contractor_clab_no;

            foreach ($dataRows as $index => $row) {
                $rowNumber = $index + 3; // +3 because of header and instruction rows

                // Skip empty rows
                if (empty(array_filter($row))) {
                    continue;
                }

                $passport = trim($row[0] ?? '');
                $name = trim($row[1] ?? '');
                $otNormal = $row[2] ?? '';
                $otRest = $row[3] ?? '';
                $otPublic = $row[4] ?? '';
                $txnType = trim($row[5] ?? '');
                $txnAmount = $row[6] ?? '';
                $txnRemarks = trim($row[7] ?? '');

                // Validate required fields
                if (empty($passport)) {
                    $this->importErrors[] = "Row {$rowNumber}: Passport is required";

                    continue;
                }

                if (empty($name)) {
                    $this->importErrors[] = "Row {$rowNumber}: Worker name is required";

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
                    $this->importErrors[] = "Row {$rowNumber}: Worker with passport {$passport} not found in your worker list";

                    continue;
                }

                // Validate transaction type if provided
                if (! empty($txnType)) {
                    if (! in_array($txnType, ['advance_payment', 'deduction', 'npl', 'allowance'])) {
                        $this->importErrors[] = "Row {$rowNumber}: Invalid transaction type '{$txnType}'. Must be: advance_payment, deduction, npl, or allowance";

                        continue;
                    }

                    if (empty($txnAmount)) {
                        $this->importErrors[] = "Row {$rowNumber}: Transaction amount is required when transaction type is provided";

                        continue;
                    }

                    if (empty($txnRemarks)) {
                        $this->importErrors[] = "Row {$rowNumber}: Transaction remarks is required when transaction type is provided";

                        continue;
                    }
                }

                // Add to import data
                $this->importData[] = [
                    'passport' => $passport,
                    'name' => $name,
                    'ot_normal' => is_numeric($otNormal) ? floatval($otNormal) : null,
                    'ot_rest' => is_numeric($otRest) ? floatval($otRest) : null,
                    'ot_public' => is_numeric($otPublic) ? floatval($otPublic) : null,
                    'transaction_type' => $txnType ?: null,
                    'transaction_amount' => is_numeric($txnAmount) ? floatval($txnAmount) : null,
                    'transaction_remarks' => $txnRemarks ?: null,
                    'row_number' => $rowNumber,
                ];
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
                Flux::toast(
                    variant: 'warning',
                    heading: 'Import Warnings',
                    text: count($this->importErrors).' errors found. Please review before proceeding.'
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
                    $groupedData[$passport]['transactions'][] = [
                        'type' => $item['transaction_type'],
                        'amount' => $item['transaction_amount'],
                        'remarks' => $item['transaction_remarks'],
                    ];
                }
            }

            // Update entries
            foreach ($this->entries as $index => &$entry) {
                if (isset($groupedData[$entry['worker_passport']])) {
                    $data = $groupedData[$entry['worker_passport']];

                    // Update OT hours
                    if ($data['ot_normal'] !== null) {
                        $entry['ot_normal_hours'] = $data['ot_normal'];
                    }
                    if ($data['ot_rest'] !== null) {
                        $entry['ot_rest_hours'] = $data['ot_rest'];
                    }
                    if ($data['ot_public'] !== null) {
                        $entry['ot_public_hours'] = $data['ot_public'];
                    }

                    // Add transactions
                    if (! empty($data['transactions'])) {
                        $entry['transactions'] = array_merge($entry['transactions'] ?? [], $data['transactions']);
                        $importedTransactions += count($data['transactions']);
                    }

                    $importedWorkers++;
                }
            }

            // Force Livewire reactivity
            $this->entries = $this->entries;

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
                text: "Imported data for {$importedWorkers} workers with {$importedTransactions} transactions. Don't forget to save your changes!"
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

    public function render()
    {
        return view('livewire.client.o-t-entry')
            ->layout('components.layouts.app', ['title' => 'OT Entry']);
    }
}
