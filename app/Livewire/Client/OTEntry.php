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

    public $importMode = 'add'; // 'add' or 'override'

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
            'newTransactionType' => 'required|in:accommodation,advance_payment,deduction,npl,allowance',
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

        // Row 1: Instructions (will be skipped during import)
        $sheet->setCellValue('A1', 'INSTRUCTIONS: Fill passport, name, OT hours. For transactions, use types: accommodation, advance_payment, deduction, npl, allowance. Leave OT columns empty if adding only transactions. You can have multiple rows for the same worker. DELETE THIS ROW AND EXAMPLE ROWS BEFORE IMPORTING.');
        $sheet->mergeCells('A1:H1');
        $sheet->getStyle('A1')->getFont()->setItalic(true)->setBold(true);
        $sheet->getStyle('A1')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('FFFFCC');
        $sheet->getRowDimension(1)->setRowHeight(30);

        // Row 2: Headers
        $headers = [
            'A2' => 'Worker Passport',
            'B2' => 'Worker Name',
            'C2' => 'OT Normal Hours',
            'D2' => 'OT Rest Hours',
            'E2' => 'OT Public Hours',
            'F2' => 'Transaction Type',
            'G2' => 'Transaction Amount',
            'H2' => 'Transaction Remarks',
        ];

        foreach ($headers as $cell => $value) {
            $sheet->setCellValue($cell, $value);
            $sheet->getStyle($cell)->getFont()->setBold(true);
            $sheet->getStyle($cell)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('D9E1F2');
        }

        // Row 3+: Example data - Accommodation
        $sheet->setCellValue('A3', 'AB012345');
        $sheet->setCellValue('B3', 'JOHN DOE');
        $sheet->setCellValue('C3', '10');
        $sheet->setCellValue('D3', '8');
        $sheet->setCellValue('E3', '0');
        $sheet->setCellValue('F3', 'accommodation');
        $sheet->setCellValue('G3', '200.00');
        $sheet->setCellValue('H3', 'Monthly accommodation deduction');

        // Example - Advance Payment
        $sheet->setCellValue('A4', 'AB012345');
        $sheet->setCellValue('B4', 'JOHN DOE');
        $sheet->setCellValue('C4', '');
        $sheet->setCellValue('D4', '');
        $sheet->setCellValue('E4', '');
        $sheet->setCellValue('F4', 'advance_payment');
        $sheet->setCellValue('G4', '500.00');
        $sheet->setCellValue('H4', 'Advance payment for medical expenses');

        // Example - Other Deduction
        $sheet->setCellValue('A5', 'AB012345');
        $sheet->setCellValue('B5', 'JOHN DOE');
        $sheet->setCellValue('C5', '');
        $sheet->setCellValue('D5', '');
        $sheet->setCellValue('E5', '');
        $sheet->setCellValue('F5', 'deduction');
        $sheet->setCellValue('G5', '50.00');
        $sheet->setCellValue('H5', 'Deduction for damaged equipment');

        // Example - NPL (No-Pay Leave)
        $sheet->setCellValue('A6', 'AB012345');
        $sheet->setCellValue('B6', 'JOHN DOE');
        $sheet->setCellValue('C6', '');
        $sheet->setCellValue('D6', '');
        $sheet->setCellValue('E6', '');
        $sheet->setCellValue('F6', 'npl');
        $sheet->setCellValue('G6', '2');
        $sheet->setCellValue('H6', 'No-pay leave for 2 days');

        // Example - Allowance (Earning)
        $sheet->setCellValue('A7', 'AB012346');
        $sheet->setCellValue('B7', 'JANE DOE');
        $sheet->setCellValue('C7', '5');
        $sheet->setCellValue('D7', '0');
        $sheet->setCellValue('E7', '8');
        $sheet->setCellValue('F7', 'allowance');
        $sheet->setCellValue('G7', '150.00');
        $sheet->setCellValue('H7', 'Transportation allowance');

        // Style example rows with light gray background
        $sheet->getStyle('A3:H7')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('F0F0F0');

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

            // Process rows and intelligently skip instructions/headers
            $dataStartIndex = 0;
            foreach ($rows as $index => $row) {
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

                $passport = trim($row[0] ?? '');
                $name = trim($row[1] ?? '');
                $otNormal = $this->sanitizeNumericValue($row[2] ?? '');
                $otRest = $this->sanitizeNumericValue($row[3] ?? '');
                $otPublic = $this->sanitizeNumericValue($row[4] ?? '');
                $txnType = strtolower(trim($row[5] ?? ''));
                $txnAmount = $this->sanitizeNumericValue($row[6] ?? '');
                $txnRemarks = trim($row[7] ?? '');

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
                    $validTypes = ['accommodation', 'advance_payment', 'deduction', 'npl', 'allowance'];
                    if (! in_array($txnType, $validTypes)) {
                        $this->importErrors[] = "Row {$rowNumber}: Invalid transaction type '{$txnType}'. Must be one of: " . implode(', ', $validTypes);
                        $rowHasError = true;
                    }

                    if (empty($rawTxnAmount)) {
                        $this->importErrors[] = "Row {$rowNumber}: Transaction amount is required when transaction type is provided";
                        $rowHasError = true;
                    } elseif ($txnAmount === null) {
                        $this->importErrors[] = "Row {$rowNumber}: Invalid transaction amount '{$rawTxnAmount}'. Must be a valid number";
                        $rowHasError = true;
                    } elseif ($txnAmount <= 0) {
                        $this->importErrors[] = "Row {$rowNumber}: Transaction amount must be greater than 0 (got {$txnAmount})";
                        $rowHasError = true;
                    } elseif ($txnType === 'npl' && $txnAmount > 31) {
                        $this->importErrors[] = "Row {$rowNumber}: NPL days cannot exceed 31 days (got {$txnAmount})";
                        $rowHasError = true;
                    } elseif ($txnType !== 'npl' && $txnAmount > 100000) {
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
                    'transaction_amount' => $txnAmount,
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
