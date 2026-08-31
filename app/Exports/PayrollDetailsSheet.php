<?php

namespace App\Exports;

use App\Models\Contractor;
use App\Models\PayrollWorker;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PayrollDetailsSheet implements FromCollection, WithColumnWidths, WithHeadings, WithMapping, WithStyles, WithTitle
{
    protected $workers;

    protected $contractorNames = [];

    protected $payrollRecords;

    public function __construct($workers, ?string $clabNo = null)
    {
        $this->workers = $workers;

        // Get all worker IDs
        $workerIds = collect($workers)->pluck('wkr_id')->filter()->toArray();

        // Get all payroll records for these workers. Transactions (and their
        // NPL breakdowns) are eager loaded so the per-row totals below cost no
        // extra queries.
        $query = PayrollWorker::whereIn('worker_id', $workerIds)
            ->with(['payrollSubmission', 'transactions.nplDetails']);

        // A contractor's export must never surface payroll a worker earned
        // under a different employer, so scope to their own submissions.
        if ($clabNo) {
            $query->whereHas('payrollSubmission', fn ($q) => $q->where('contractor_clab_no', $clabNo));
        }

        $this->payrollRecords = $query
            ->orderBy('payroll_submission_id', 'desc')
            ->orderBy('worker_name')
            ->get();

        // Preload contractor names to avoid N+1 queries
        $clabNos = $this->payrollRecords
            ->pluck('payrollSubmission.contractor_clab_no')
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        if (! empty($clabNos)) {
            $this->contractorNames = Contractor::whereIn('ctr_clab_no', $clabNos)
                ->pluck('ctr_comp_name', 'ctr_clab_no')
                ->filter()
                ->toArray();

            $missing = array_diff($clabNos, array_keys($this->contractorNames));

            if (! empty($missing)) {
                $this->contractorNames += \App\Models\User::whereIn('contractor_clab_no', $missing)
                    ->pluck('name', 'contractor_clab_no')
                    ->toArray();
            }
        }
    }

    public function collection()
    {
        return $this->payrollRecords;
    }

    public function title(): string
    {
        return 'Payroll Details';
    }

    public function headings(): array
    {
        return [
            'Payroll Month',
            'Submission Status',
            'Payment Deadline',
            'Worker ID',
            'Worker Name',
            'Passport Number',
            'CLAB ID',
            'Contractor Name',
            // Salary basis
            'Basic Salary',
            'Pro-Rated',
            'Days Worked',
            'Days In Month',
            // Hours
            'Regular Hours',
            'Normal OT Hours',
            'Rest Day OT Hours',
            'Public Holiday OT Hours',
            'Total OT Hours',
            // Pay
            'Regular Pay',
            'Normal OT Pay',
            'Rest Day OT Pay',
            'Public Holiday OT Pay',
            'Total OT Pay',
            // Transaction earnings
            'Allowance',
            'Backpay',
            'Medical Claim',
            'Total Earnings (Transactions)',
            // Transaction deductions
            'Advance Payment',
            'Other Deduction',
            'Accommodation',
            'NPL (No-Pay Leave)',
            'Total Deductions (Transactions)',
            // Statutory
            'Gross Salary',
            'EPF (Employee)',
            'SOCSO (Employee)',
            'Total Statutory Deductions',
            'EPF (Employer)',
            'SOCSO (Employer)',
            'Total Employer Contribution',
            // Totals
            'Net Salary',
            'Total Payment to CLAB',
        ];
    }

    public function map($payrollWorker): array
    {
        $submission = $payrollWorker->payrollSubmission;
        $payrollMonth = $submission && $submission->month_year ? $submission->month_year : '-';

        // Get CLAB ID from submission
        $clabId = $submission && $submission->contractor_clab_no ? $submission->contractor_clab_no : '-';

        // Get Contractor Name from preloaded data
        $contractorName = $this->contractorNames[$clabId] ?? '-';

        // Transaction totals, computed from the eager-loaded relation so the
        // model accessors (which each fire their own query) are not used here.
        $transactions = $payrollWorker->transactions;
        $sum = fn (string $type) => (float) $transactions->where('type', $type)->sum('amount');

        $allowance = $sum('allowance');
        $backpay = $sum('backpay');
        $medicalClaim = $sum('medical_claim');
        $advancePayment = $sum('advance_payment');
        $otherDeduction = $sum('deduction');
        $accommodation = $sum('accommodation');

        // NPL stores days, not ringgit — each transaction values itself against
        // the months the leave was taken.
        $npl = round(
            $transactions->where('type', 'npl')
                ->sum(fn ($t) => $t->nplAmount((float) $payrollWorker->basic_salary)),
            2
        );

        $totalEarnings = $allowance + $backpay + $medicalClaim;
        $totalDeductions = $advancePayment + $otherDeduction + $accommodation + $npl;

        return [
            $payrollMonth,
            $submission ? ucwords(str_replace('_', ' ', $submission->status)) : '-',
            $submission && $submission->payment_deadline ? $submission->payment_deadline->format('Y-m-d') : '-',
            $payrollWorker->worker_id,
            $payrollWorker->worker_name,
            $payrollWorker->worker_passport,
            $clabId,
            $contractorName,
            // Salary basis
            $this->formatCurrency($payrollWorker->basic_salary),
            $payrollWorker->is_pro_rated ? 'Yes' : 'No',
            $payrollWorker->days_worked ?? '-',
            $payrollWorker->total_days_in_month ?? '-',
            // Hours
            number_format($payrollWorker->regular_hours ?? 0, 2),
            number_format($payrollWorker->ot_normal_hours ?? 0, 2),
            number_format($payrollWorker->ot_rest_hours ?? 0, 2),
            number_format($payrollWorker->ot_public_hours ?? 0, 2),
            number_format(
                ($payrollWorker->ot_normal_hours ?? 0)
                + ($payrollWorker->ot_rest_hours ?? 0)
                + ($payrollWorker->ot_public_hours ?? 0),
                2
            ),
            // Pay
            $this->formatCurrency($payrollWorker->regular_pay),
            $this->formatCurrency($payrollWorker->ot_normal_pay),
            $this->formatCurrency($payrollWorker->ot_rest_pay),
            $this->formatCurrency($payrollWorker->ot_public_pay),
            $this->formatCurrency($payrollWorker->total_ot_pay),
            // Transaction earnings
            $this->formatCurrency($allowance),
            $this->formatCurrency($backpay),
            $this->formatCurrency($medicalClaim),
            $this->formatCurrency($totalEarnings),
            // Transaction deductions
            $this->formatCurrency($advancePayment),
            $this->formatCurrency($otherDeduction),
            $this->formatCurrency($accommodation),
            $this->formatCurrency($npl),
            $this->formatCurrency($totalDeductions),
            // Statutory
            $this->formatCurrency($payrollWorker->gross_salary),
            $this->formatCurrency($payrollWorker->epf_employee),
            $this->formatCurrency($payrollWorker->socso_employee),
            $this->formatCurrency($payrollWorker->total_deductions),
            $this->formatCurrency($payrollWorker->epf_employer),
            $this->formatCurrency($payrollWorker->socso_employer),
            $this->formatCurrency($payrollWorker->total_employer_contribution),
            // Totals
            $this->formatCurrency($payrollWorker->net_salary),
            $this->formatCurrency($payrollWorker->total_payment),
        ];
    }

    private function formatCurrency($amount)
    {
        if ($amount === null || $amount == 0) {
            return 'RM 0.00';
        }

        return 'RM '.number_format($amount, 2);
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 12]],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 16,  // Payroll Month
            'B' => 18,  // Submission Status
            'C' => 16,  // Payment Deadline
            'D' => 12,  // Worker ID
            'E' => 28,  // Worker Name
            'F' => 18,  // Passport Number
            'G' => 14,  // CLAB ID
            'H' => 32,  // Contractor Name
            'I' => 14,  // Basic Salary
            'J' => 11,  // Pro-Rated
            'K' => 13,  // Days Worked
            'L' => 14,  // Days In Month
            'M' => 14,  // Regular Hours
            'N' => 16,  // Normal OT Hours
            'O' => 18,  // Rest Day OT Hours
            'P' => 22,  // Public Holiday OT Hours
            'Q' => 15,  // Total OT Hours
            'R' => 14,  // Regular Pay
            'S' => 15,  // Normal OT Pay
            'T' => 17,  // Rest Day OT Pay
            'U' => 21,  // Public Holiday OT Pay
            'V' => 14,  // Total OT Pay
            'W' => 14,  // Allowance
            'X' => 14,  // Backpay
            'Y' => 15,  // Medical Claim
            'Z' => 26,  // Total Earnings (Transactions)
            'AA' => 17, // Advance Payment
            'AB' => 16, // Other Deduction
            'AC' => 16, // Accommodation
            'AD' => 18, // NPL
            'AE' => 28, // Total Deductions (Transactions)
            'AF' => 14, // Gross Salary
            'AG' => 16, // EPF (Employee)
            'AH' => 17, // SOCSO (Employee)
            'AI' => 24, // Total Statutory Deductions
            'AJ' => 16, // EPF (Employer)
            'AK' => 17, // SOCSO (Employer)
            'AL' => 24, // Total Employer Contribution
            'AM' => 14, // Net Salary
            'AN' => 20, // Total Payment to CLAB
        ];
    }
}
