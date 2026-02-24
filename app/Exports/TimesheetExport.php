<?php

namespace App\Exports;

use App\Models\ContractorConfiguration;
use App\Models\DeductionTemplate;
use App\Models\WorkerDeduction;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class TimesheetExport implements FromCollection, WithColumnWidths, WithHeadings, WithMapping, WithStyles, WithTitle
{
    protected $entries;

    protected $period;

    protected $deductionTemplates;

    protected $workerDeductions;

    protected $contractorDeductions;

    public function __construct($entries, $period = null)
    {
        $this->entries = $entries;
        $this->period = $period;

        // Get all active deduction templates (both contractor and worker level)
        $this->deductionTemplates = DeductionTemplate::active()
            ->orderBy('name')
            ->get();

        // Get all worker deductions for the workers in this export (for worker-level deductions)
        $workerIds = $entries->pluck('worker_id')->unique()->toArray();
        $this->workerDeductions = WorkerDeduction::whereIn('worker_id', $workerIds)
            ->with('deductionTemplate')
            ->get()
            ->groupBy('worker_id');

        // Get contractor deductions for all contractors in this export (for contractor-level deductions)
        $clabNos = $entries->pluck('contractor_clab_no')->unique()->toArray();
        $this->contractorDeductions = ContractorConfiguration::whereIn('contractor_clab_no', $clabNos)
            ->with('deductions')
            ->get()
            ->keyBy('contractor_clab_no');
    }

    public function collection()
    {
        return $this->entries;
    }

    public function title(): string
    {
        return 'Timesheet Report';
    }

    public function headings(): array
    {
        $headings = [
            'Employee ID',
            'Employee Email',
            'Employee Name',
            'Passport No',
            'Location',
            'Department',
            'Salary',
            'Amount this cycle',
            'Salary Type',
            'Worked For (month/day/hour)',
            'General Allowance',
            'BACKPAY',
            'ADVANCE SALARY',
            'ACCOMODATION',
            'NPL',
            'Other Deduction',
            'Normal',
            'Rest Day',
            'Public holiday',
        ];

        // Add dynamic deduction columns based on template names
        foreach ($this->deductionTemplates as $template) {
            $headings[] = $template->name;
        }

        // Add Remarks column at the end
        $headings[] = 'Remarks';

        return $headings;
    }

    public function map($entry): array
    {
        // Get allowance, advance, accommodation, NPL, and other deduction from transactions
        $allowance = 0;
        $advanceSalary = 0;
        $accommodation = 0;
        $npl = 0;
        $otherDeduction = 0;

        if ($entry->transactions) {
            foreach ($entry->transactions as $transaction) {
                if ($transaction->type === 'allowance') {
                    $allowance += $transaction->amount;
                } elseif ($transaction->type === 'advance_payment') {
                    $advanceSalary += $transaction->amount;
                } elseif ($transaction->type === 'accommodation') {
                    $accommodation += $transaction->amount;
                } elseif ($transaction->type === 'npl') {
                    $npl += $transaction->amount;
                } elseif ($transaction->type === 'deduction') {
                    $otherDeduction += $transaction->amount;
                }
            }
        }

        $row = [
            $entry->worker_id, // Employee ID
            $entry->worker_email ?? '', // Employee Email from worker_db
            $entry->worker_name, // Employee Name
            $entry->worker_passport ?? '', // Passport No
            $entry->contractor_state ?? '', // Location - contractor's state
            $entry->contractor_name ?? '', // Department - contractor name
            $entry->worker_salary ?? '', // Salary
            '',
            'monthly', // Salary Type
            '',
            $allowance > 0 ? $allowance : '', // General Allowance
            '',
            $advanceSalary > 0 ? $advanceSalary : '', // ADVANCE SALARY
            $accommodation > 0 ? $accommodation : '', // ACCOMODATION
            $npl > 0 ? $npl : '', // NPL
            $otherDeduction > 0 ? $otherDeduction : '', // Other Deduction
            $entry->ot_normal_hours > 0 ? $entry->ot_normal_hours : '', // Normal OT
            $entry->ot_rest_hours > 0 ? $entry->ot_rest_hours : '', // Rest OT
            $entry->ot_public_hours > 0 ? $entry->ot_public_hours : '', // Public holiday OT
        ];

        // Add deduction values for each template
        $workerDeductions = $this->workerDeductions[$entry->worker_id] ?? collect();
        $contractorConfig = $this->contractorDeductions[$entry->contractor_clab_no] ?? null;
        $contractorDeductionIds = $contractorConfig
            ? $contractorConfig->deductions->pluck('id')->toArray()
            : [];

        // Get worker's payroll period count for period-based deductions
        $periodCount = $entry->period_count ?? 0;

        foreach ($this->deductionTemplates as $template) {
            $hasDeduction = false;

            if ($template->type === 'contractor') {
                // Contractor-level: check if contractor has this deduction enabled
                $hasDeduction = in_array($template->id, $contractorDeductionIds);
                // Also check period if deduction has period restrictions
                if ($hasDeduction && ! empty($template->apply_periods)) {
                    $hasDeduction = $template->shouldApplyInPeriod($periodCount);
                }
            } else {
                // Worker-level: check if this specific worker has this deduction assigned
                $hasDeduction = $workerDeductions->contains(function ($deduction) use ($template) {
                    return $deduction->deduction_template_id === $template->id;
                });
                // Also check period if deduction has period restrictions
                if ($hasDeduction && ! empty($template->apply_periods)) {
                    $hasDeduction = $template->shouldApplyInPeriod($periodCount);
                }
            }

            // If deduction applies, show the template amount
            $row[] = $hasDeduction ? $template->amount : '';
        }

        // Add Remarks column
        $row[] = $entry->remarks ?? '';

        return $row;
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => false, 'size' => 11]],
        ];
    }

    public function columnWidths(): array
    {
        $widths = [
            'A' => 15, // Employee ID
            'B' => 25, // Employee Email
            'C' => 30, // Employee Name
            'D' => 18, // Passport No
            'E' => 20, // Location
            'F' => 30, // Department
            'G' => 15, // Salary
            'H' => 10, // Worked For (month/day/hour)
            'I' => 15, // Salary Type
            'J' => 18, // General Allowance
            'K' => 10, // BACKPAY
            'L' => 12, // ADVANCE SALARY
            'M' => 10, // ACCOMODATION
            'N' => 10, // NPL
            'O' => 15, // Other Deduction
            'P' => 15, // Normal
            'Q' => 15, // Rest Day
            'R' => 15, // Public holiday
        ];

        // Add column widths for deduction templates (starting from column P)
        $columnIndex = 15; // P = 16th column (0-indexed = 15)
        foreach ($this->deductionTemplates as $template) {
            $columnLetter = $this->getColumnLetter($columnIndex);
            $widths[$columnLetter] = max(15, strlen($template->name) + 2);
            $columnIndex++;
        }

        return $widths;
    }

    /**
     * Convert column index to Excel column letter (0 = A, 1 = B, etc.)
     */
    protected function getColumnLetter(int $index): string
    {
        $letter = '';
        while ($index >= 0) {
            $letter = chr(($index % 26) + 65).$letter;
            $index = intval($index / 26) - 1;
        }

        return $letter;
    }
}
