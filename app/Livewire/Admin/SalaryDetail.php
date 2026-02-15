<?php

namespace App\Livewire\Admin;

use App\Mail\PayrollApproved;
use App\Mail\PayslipReady;
use App\Models\PayrollSubmission;
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
            ->with(['worker', 'transactions'])
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

        $pdf = \PDF::loadView('admin.tax-invoice-pdf', [
            'invoice' => $this->submission,
            'contractor' => $contractor,
        ])->setPaper('a4', 'landscape');

        $filename = 'Official-Receipt-'.$this->submission->tax_invoice_number.'-'.$this->submission->month_year.'.pdf';

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, $filename);
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
            $sheet->mergeCells('A1:O1');
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
                'O9' => 'Transaction Details',
            ];

            foreach ($headers as $cell => $value) {
                $sheet->setCellValue($cell, $value);
            }

            // Style headers
            $sheet->getStyle('A9:O9')->applyFromArray([
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
                $sheet->setCellValue('K'.$row, $worker->advance_payment ?? 0);
                $sheet->setCellValue('L'.$row, $worker->other_deduction ?? 0);
                $sheet->setCellValue('M'.$row, $worker->npl_days ?? 0);
                $sheet->setCellValue('N'.$row, $worker->allowance ?? 0);

                // Add transaction details
                $transactionDetails = [];
                foreach ($worker->transactions as $txn) {
                    if ($txn->type === 'allowance') {
                        $transactionDetails[] = "+RM {$txn->amount} (Allowance".($txn->remarks ? ': '.$txn->remarks : '').')';
                    } elseif ($txn->type === 'npl') {
                        $transactionDetails[] = "{$txn->amount} days (NPL".($txn->remarks ? ': '.$txn->remarks : '').')';
                    } elseif ($txn->type === 'advance_payment') {
                        $transactionDetails[] = "-RM {$txn->amount} (Advance".($txn->remarks ? ': '.$txn->remarks : '').')';
                    } elseif ($txn->type === 'deduction') {
                        // For configured deductions, show the description if available
                        $label = $txn->description ?? 'Deduction';
                        $transactionDetails[] = "-RM {$txn->amount} ({$label}".($txn->remarks ? ' - '.$txn->remarks : '').')';
                    }
                }
                $sheet->setCellValue('O'.$row, implode("\n", $transactionDetails));
                $sheet->getStyle('O'.$row)->getAlignment()->setWrapText(true);
                $sheet->getStyle('O'.$row)->getAlignment()->setVertical(Alignment::VERTICAL_TOP);

                // Format currency columns
                foreach (['G', 'K', 'L', 'N'] as $col) {
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
            $sheet->setCellValue('O'.$totalRow, ''); // No total for transaction details

            // Style total row
            $sheet->getStyle('A'.$totalRow.':O'.$totalRow)->applyFromArray([
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
            foreach (['G', 'K', 'L', 'N'] as $col) {
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
            foreach (range('A', 'O') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }

            // Set a minimum width for Transaction Details column to show wrapped text properly
            $sheet->getColumnDimension('O')->setWidth(50);

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
        $this->showReviewModal = true;
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
            $filePath = $this->breakdownFile->getRealPath();

            // Load the spreadsheet
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($filePath);
            $sheet = $spreadsheet->getActiveSheet();

            $highestColumn = $sheet->getHighestColumn();
            $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);

            // Search for header row in the first 10 rows
            $headerRow = null;
            $headers = [];
            $requiredColumns = ['Gross Salary', 'EPF', 'SOCSO', 'EIS', 'HRDF'];

            for ($row = 1; $row <= min(10, $sheet->getHighestRow()); $row++) {
                $rowHeaders = [];
                for ($col = 1; $col <= $highestColumnIndex; $col++) {
                    $cellValue = $sheet->getCellByColumnAndRow($col, $row)->getValue();
                    if ($cellValue) {
                        // Normalize: trim whitespace and remove extra spaces
                        $normalized = preg_replace('/\s+/', ' ', trim($cellValue));
                        $rowHeaders[$col] = $normalized;
                    }
                }

                // Check if this row contains at least 3 of the required columns (to be flexible)
                $foundCount = 0;
                foreach ($requiredColumns as $requiredCol) {
                    foreach ($rowHeaders as $headerName) {
                        if (strcasecmp($headerName, $requiredCol) === 0) {
                            $foundCount++;
                            break;
                        }
                    }
                }

                // If we found at least 3 required columns, this is likely the header row
                if ($foundCount >= 3) {
                    $headerRow = $row;
                    $headers = $rowHeaders;
                    break;
                }
            }

            if ($headerRow === null) {
                Flux::toast(
                    variant: 'danger',
                    heading: 'Header Row Not Found',
                    text: 'Could not find header row with required columns in the first 10 rows.'
                );
                $this->breakdownFile = null;

                return;
            }

            // Log found headers for debugging
            \Log::info('Excel headers found', [
                'headers' => $headers,
                'submission_id' => $this->submission->id,
            ]);

            // Find required columns (case-insensitive)
            // Note: HRDF is optional as some Excel formats don't have it
            $requiredColumns = ['Gross Salary', 'EPF', 'SOCSO', 'EIS'];
            $optionalColumns = ['HRDF'];
            $optionalDeductionColumns = ['Custom Advance Salary', 'Custom Accomodation'];
            $columnIndices = [];
            $missingColumns = [];

            foreach ($requiredColumns as $requiredCol) {
                $found = false;
                foreach ($headers as $colIndex => $headerName) {
                    if (strcasecmp($headerName, $requiredCol) === 0) {
                        // For EPF, SOCSO, EIS: Take the LAST occurrence (employer contribution)
                        // Don't break, keep searching for later occurrences
                        $columnIndices[$requiredCol] = $colIndex;
                        $found = true;
                        // Don't break - continue searching to find the last occurrence
                    }
                }

                if (! $found) {
                    $missingColumns[] = $requiredCol;
                }
            }

            // Check for optional columns (additions)
            foreach ($optionalColumns as $optionalCol) {
                foreach ($headers as $colIndex => $headerName) {
                    if (strcasecmp($headerName, $optionalCol) === 0) {
                        $columnIndices[$optionalCol] = $colIndex;
                        // Don't break - take last occurrence
                    }
                }
            }

            // Check for optional deduction columns (subtractions)
            foreach ($optionalDeductionColumns as $deductionCol) {
                foreach ($headers as $colIndex => $headerName) {
                    if (strcasecmp($headerName, $deductionCol) === 0) {
                        $columnIndices[$deductionCol] = $colIndex;
                        // Don't break - take last occurrence
                    }
                }
            }

            if (! empty($missingColumns)) {
                $foundColumnsList = implode(', ', array_values($headers));

                Flux::toast(
                    variant: 'danger',
                    heading: 'Invalid Excel Format',
                    text: 'Missing required columns: '.implode(', ', $missingColumns).'. Found columns: '.$foundColumnsList
                );

                \Log::warning('Excel parsing failed - missing columns', [
                    'submission_id' => $this->submission->id,
                    'missing' => $missingColumns,
                    'found' => $headers,
                ]);

                $this->breakdownFile = null;

                return;
            }

            // Read totals from the last row (which contains the sum of all columns)
            $highestRow = $sheet->getHighestRow();

            // Read CALCULATED values from the last row (formulas are evaluated)
            $totals = [];
            foreach (array_merge($requiredColumns, $optionalColumns) as $colName) {
                if (isset($columnIndices[$colName])) {
                    $value = $sheet->getCellByColumnAndRow($columnIndices[$colName], $highestRow)->getCalculatedValue();
                    $totals[$colName] = floatval($value);
                } else {
                    // Optional column not found, set to 0
                    $totals[$colName] = 0;
                }
            }

            // Read optional deduction columns (use abs() since Excel values are negative)
            $deductions = [];
            foreach ($optionalDeductionColumns as $deductionCol) {
                if (isset($columnIndices[$deductionCol])) {
                    $value = $sheet->getCellByColumnAndRow($columnIndices[$deductionCol], $highestRow)->getCalculatedValue();
                    $deductions[$deductionCol] = abs(floatval($value));
                } else {
                    $deductions[$deductionCol] = 0;
                }
            }

            // Calculate total payroll amount (additions - deductions)
            $totalAdditions = array_sum($totals);
            $totalDeductions = array_sum($deductions);
            $totalAmount = $totalAdditions - $totalDeductions;

            // Store breakdown for display
            $this->calculatedBreakdown = [
                'gross_salary' => $totals['Gross Salary'],
                'epf' => $totals['EPF'],
                'socso' => $totals['SOCSO'],
                'eis' => $totals['EIS'],
                'hrdf' => $totals['HRDF'],
                'custom_advance_salary' => $deductions['Custom Advance Salary'],
                'custom_accomodation' => $deductions['Custom Accomodation'],
                'total' => $totalAmount,
            ];

            // Auto-fill the amount
            $this->reviewFinalAmount = number_format($totalAmount, 2, '.', '');

            Flux::toast(
                variant: 'success',
                heading: 'Excel Parsed Successfully',
                text: 'Total payroll amount: RM '.number_format($totalAmount, 2)
            );

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
            $directory = 'payroll-breakdowns/'.$this->submission->year.'/'.$this->submission->month;
            $fullDirectoryPath = storage_path('app/'.$directory);

            if (! file_exists($fullDirectoryPath)) {
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
            'payslipFile' => 'required|file|mimes:zip|max:10240', // 10MB max
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
            $filePath = $this->newBreakdownFile->getRealPath();

            // Load the spreadsheet
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($filePath);
            $sheet = $spreadsheet->getActiveSheet();

            $highestColumn = $sheet->getHighestColumn();
            $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);

            // Search for header row in the first 10 rows
            $headerRow = null;
            $headers = [];
            $requiredColumns = ['Gross Salary', 'EPF', 'SOCSO', 'EIS', 'HRDF'];

            for ($row = 1; $row <= min(10, $sheet->getHighestRow()); $row++) {
                $rowHeaders = [];
                for ($col = 1; $col <= $highestColumnIndex; $col++) {
                    $cellValue = $sheet->getCellByColumnAndRow($col, $row)->getValue();
                    if ($cellValue) {
                        // Normalize: trim whitespace and remove extra spaces
                        $normalized = preg_replace('/\s+/', ' ', trim($cellValue));
                        $rowHeaders[$col] = $normalized;
                    }
                }

                // Check if this row contains at least 3 of the required columns (to be flexible)
                $foundCount = 0;
                foreach ($requiredColumns as $requiredCol) {
                    foreach ($rowHeaders as $headerName) {
                        if (strcasecmp($headerName, $requiredCol) === 0) {
                            $foundCount++;
                            break;
                        }
                    }
                }

                // If we found at least 3 required columns, this is likely the header row
                if ($foundCount >= 3) {
                    $headerRow = $row;
                    $headers = $rowHeaders;
                    break;
                }
            }

            if ($headerRow === null) {
                Flux::toast(
                    variant: 'danger',
                    heading: 'Header Row Not Found',
                    text: 'Could not find header row with required columns in the first 10 rows.'
                );
                $this->newBreakdownFile = null;

                return;
            }

            // Log found headers for debugging
            \Log::info('Excel headers found', [
                'headers' => $headers,
                'submission_id' => $this->submission->id,
            ]);

            // Find required columns (case-insensitive)
            // Note: HRDF is optional as some Excel formats don't have it
            $requiredColumns = ['Gross Salary', 'EPF', 'SOCSO', 'EIS'];
            $optionalColumns = ['HRDF'];
            $optionalDeductionColumns = ['Custom Advance Salary', 'Custom Accomodation'];
            $columnIndices = [];
            $missingColumns = [];

            foreach ($requiredColumns as $requiredCol) {
                $found = false;
                foreach ($headers as $colIndex => $headerName) {
                    if (strcasecmp($headerName, $requiredCol) === 0) {
                        // For EPF, SOCSO, EIS: Take the LAST occurrence (employer contribution)
                        // Don't break, keep searching for later occurrences
                        $columnIndices[$requiredCol] = $colIndex;
                        $found = true;
                        // Don't break - continue searching to find the last occurrence
                    }
                }

                if (! $found) {
                    $missingColumns[] = $requiredCol;
                }
            }

            // Check for optional columns (additions)
            foreach ($optionalColumns as $optionalCol) {
                foreach ($headers as $colIndex => $headerName) {
                    if (strcasecmp($headerName, $optionalCol) === 0) {
                        $columnIndices[$optionalCol] = $colIndex;
                        // Don't break - take last occurrence
                    }
                }
            }

            // Check for optional deduction columns (subtractions)
            foreach ($optionalDeductionColumns as $deductionCol) {
                foreach ($headers as $colIndex => $headerName) {
                    if (strcasecmp($headerName, $deductionCol) === 0) {
                        $columnIndices[$deductionCol] = $colIndex;
                        // Don't break - take last occurrence
                    }
                }
            }

            if (! empty($missingColumns)) {
                $foundColumnsList = implode(', ', array_values($headers));

                Flux::toast(
                    variant: 'danger',
                    heading: 'Invalid Excel Format',
                    text: 'Missing required columns: '.implode(', ', $missingColumns).'. Found columns: '.$foundColumnsList
                );

                \Log::warning('Excel parsing failed - missing columns', [
                    'submission_id' => $this->submission->id,
                    'missing' => $missingColumns,
                    'found' => $headers,
                ]);

                $this->newBreakdownFile = null;

                return;
            }

            // Read totals from the last row (which contains the sum of all columns)
            $highestRow = $sheet->getHighestRow();

            // Read CALCULATED values from the last row (formulas are evaluated)
            $totals = [];
            foreach (array_merge($requiredColumns, $optionalColumns) as $colName) {
                if (isset($columnIndices[$colName])) {
                    $value = $sheet->getCellByColumnAndRow($columnIndices[$colName], $highestRow)->getCalculatedValue();
                    $totals[$colName] = floatval($value);
                } else {
                    // Optional column not found, set to 0
                    $totals[$colName] = 0;
                }
            }

            // Read optional deduction columns (use abs() since Excel values are negative)
            $deductions = [];
            foreach ($optionalDeductionColumns as $deductionCol) {
                if (isset($columnIndices[$deductionCol])) {
                    $value = $sheet->getCellByColumnAndRow($columnIndices[$deductionCol], $highestRow)->getCalculatedValue();
                    $deductions[$deductionCol] = abs(floatval($value));
                } else {
                    $deductions[$deductionCol] = 0;
                }
            }

            // Calculate total payroll amount (additions - deductions)
            $totalAdditions = array_sum($totals);
            $totalDeductions = array_sum($deductions);
            $totalAmount = $totalAdditions - $totalDeductions;

            // Store breakdown for display
            $this->calculatedBreakdown = [
                'gross_salary' => $totals['Gross Salary'],
                'epf' => $totals['EPF'],
                'socso' => $totals['SOCSO'],
                'eis' => $totals['EIS'],
                'hrdf' => $totals['HRDF'],
                'custom_advance_salary' => $deductions['Custom Advance Salary'],
                'custom_accomodation' => $deductions['Custom Accomodation'],
                'total' => $totalAmount,
            ];

            // Auto-fill the amount
            $this->editPayrollAmount = number_format($totalAmount, 2, '.', '');

            Flux::toast(
                variant: 'success',
                heading: 'Excel Parsed Successfully',
                text: 'Total payroll amount: RM '.number_format($totalAmount, 2)
            );

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

                // Generate custom filename
                $extension = $this->newBreakdownFile->getClientOriginalExtension();
                $monthName = strtoupper(date('M', mktime(0, 0, 0, $this->submission->month, 1)));
                $customFileName = sprintf(
                    'worker_breakdown_%s_%s_%s.%s',
                    $this->submission->contractor_clab_no,
                    $monthName,
                    $this->submission->year,
                    $extension
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

                $changes[] = 'File: '.$customFileName;
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

    public function render()
    {
        // Refresh submission from database to get latest payment data
        $this->submission->refresh();

        return view('livewire.admin.salary-detail');
    }
}
