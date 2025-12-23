<?php

namespace App\Exports;

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

    public function __construct($workers)
    {
        $this->workers = $workers;
    }

    public function collection()
    {
        // Get all worker IDs
        $workerIds = $this->workers->pluck('wkr_id')->toArray();

        // Get all payroll records for these workers
        return PayrollWorker::whereIn('worker_id', $workerIds)
            ->with(['payrollSubmission'])
            ->orderBy('payroll_submission_id', 'desc')
            ->orderBy('worker_name')
            ->get();
    }

    public function title(): string
    {
        return 'Payroll Details';
    }

    public function headings(): array
    {
        return [
            'Payroll Month',
            'Worker ID',
            'Worker Name',
            'Passport Number',
            'CLAB ID',
            'Basic Salary',
            'Regular Hours',
            'Normal OT Hours',
            'Rest Day OT Hours',
            'Public Holiday OT Hours',
            'Total OT Hours',
            'Regular Pay',
            'Normal OT Pay',
            'Rest Day OT Pay',
            'Public Holiday OT Pay',
            'Total OT Pay',
            'Gross Salary',
            'Advance Payment',
            'Deduction',
            'EPF (Employee)',
            'SOCSO (Employee)',
            'Total Deductions',
            'EPF (Employer)',
            'SOCSO (Employer)',
            'Total Employer Contribution',
            'Net Salary',
            'Total Payment to CLAB',
        ];
    }

    public function map($payrollWorker): array
    {
        $submission = $payrollWorker->payrollSubmission;
        $payrollMonth = $submission
            ? ($submission->month_year ? \Carbon\Carbon::parse($submission->month_year)->format('M Y') : '-')
            : '-';

        // Get CLAB ID from submission
        $clabId = $submission && $submission->contractor_clab_no ? $submission->contractor_clab_no : '-';

        return [
            $payrollMonth,
            $payrollWorker->worker_id,
            $payrollWorker->worker_name,
            $payrollWorker->worker_passport,
            $clabId,
            $this->formatCurrency($payrollWorker->basic_salary),
            number_format($payrollWorker->regular_hours ?? 0, 2),
            number_format($payrollWorker->ot_normal_hours ?? 0, 2),
            number_format($payrollWorker->ot_rest_hours ?? 0, 2),
            number_format($payrollWorker->ot_public_hours ?? 0, 2),
            number_format($payrollWorker->total_overtime_hours ?? 0, 2),
            $this->formatCurrency($payrollWorker->regular_pay),
            $this->formatCurrency($payrollWorker->ot_normal_pay),
            $this->formatCurrency($payrollWorker->ot_rest_pay),
            $this->formatCurrency($payrollWorker->ot_public_pay),
            $this->formatCurrency($payrollWorker->total_ot_pay),
            $this->formatCurrency($payrollWorker->gross_salary),
            $this->formatCurrency($payrollWorker->advance_payment),
            $this->formatCurrency($payrollWorker->deduction),
            $this->formatCurrency($payrollWorker->epf_employee),
            $this->formatCurrency($payrollWorker->socso_employee),
            $this->formatCurrency($payrollWorker->total_deductions),
            $this->formatCurrency($payrollWorker->epf_employer),
            $this->formatCurrency($payrollWorker->socso_employer),
            $this->formatCurrency($payrollWorker->total_employer_contribution),
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
            'A' => 15, // Payroll Month
            'B' => 12, // Worker ID
            'C' => 25, // Worker Name
            'D' => 18, // Passport Number
            'E' => 18, // CLAB ID
            'F' => 15, // Basic Salary
            'G' => 15, // Regular Hours
            'H' => 16, // Normal OT Hours
            'I' => 18, // Rest Day OT Hours
            'J' => 20, // Public Holiday OT Hours
            'K' => 15, // Total OT Hours
            'L' => 15, // Regular Pay
            'M' => 15, // Normal OT Pay
            'N' => 16, // Rest Day OT Pay
            'O' => 20, // Public Holiday OT Pay
            'P' => 15, // Total OT Pay
            'Q' => 15, // Gross Salary
            'R' => 16, // Advance Payment
            'S' => 15, // Deduction
            'T' => 16, // EPF (Employee)
            'U' => 17, // SOCSO (Employee)
            'V' => 18, // Total Deductions
            'W' => 16, // EPF (Employer)
            'X' => 17, // SOCSO (Employer)
            'Y' => 22, // Total Employer Contribution
            'Z' => 15, // Net Salary
            'AA' => 20, // Total Payment to CLAB
        ];
    }
}
