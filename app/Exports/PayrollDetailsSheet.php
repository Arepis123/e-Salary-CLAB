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

    protected $contractorNames = [];

    protected $payrollRecords;

    public function __construct($workers)
    {
        $this->workers = $workers;

        // Get all worker IDs
        $workerIds = $this->workers->pluck('wkr_id')->toArray();

        // Get all payroll records for these workers
        $this->payrollRecords = PayrollWorker::whereIn('worker_id', $workerIds)
            ->with(['payrollSubmission'])
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
            $this->contractorNames = \App\Models\User::whereIn('contractor_clab_no', $clabNos)
                ->pluck('name', 'contractor_clab_no')
                ->toArray();
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
            'Worker ID',
            'Worker Name',
            'Passport Number',
            'CLAB ID',
            'Contractor Name',
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

        // Get Contractor Name from preloaded data
        $contractorName = '-';
        if ($clabId !== '-' && isset($this->contractorNames[$clabId])) {
            $contractorName = $this->contractorNames[$clabId];
        }

        return [
            $payrollMonth,
            $payrollWorker->worker_id,
            $payrollWorker->worker_name,
            $payrollWorker->worker_passport,
            $clabId,
            $contractorName,
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
            'F' => 30, // Contractor Name
            'G' => 15, // Basic Salary
            'H' => 15, // Regular Hours
            'I' => 16, // Normal OT Hours
            'J' => 18, // Rest Day OT Hours
            'K' => 20, // Public Holiday OT Hours
            'L' => 15, // Total OT Hours
            'M' => 15, // Regular Pay
            'N' => 15, // Normal OT Pay
            'O' => 16, // Rest Day OT Pay
            'P' => 20, // Public Holiday OT Pay
            'Q' => 15, // Total OT Pay
            'R' => 15, // Gross Salary
            'S' => 16, // Advance Payment
            'T' => 15, // Deduction
            'U' => 16, // EPF (Employee)
            'V' => 17, // SOCSO (Employee)
            'W' => 18, // Total Deductions
            'X' => 16, // EPF (Employer)
            'Y' => 17, // SOCSO (Employer)
            'Z' => 22, // Total Employer Contribution
            'AA' => 15, // Net Salary
            'AB' => 20, // Total Payment to CLAB
        ];
    }
}
