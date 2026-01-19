<?php

namespace App\Exports;

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

    public function __construct($entries, $period = null)
    {
        $this->entries = $entries;
        $this->period = $period;
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
        return [
            'Employee ID',
            'Employee Email',
            'Employee Name',
            'Location',
            'Department',
            'Salary',
            'Salary Type',
            'General Allowance',
            'ADVANCE SALARY',
            'Normal',
            'Public holiday',
        ];
    }

    public function map($entry): array
    {
        // Get allowance and advance from transactions
        $allowance = 0;
        $advanceSalary = 0;

        if ($entry->transactions) {
            foreach ($entry->transactions as $transaction) {
                if ($transaction->type === 'allowance') {
                    $allowance += $transaction->amount;
                } elseif ($transaction->type === 'advance_payment') {
                    $advanceSalary += $transaction->amount;
                }
            }
        }

        return [
            $entry->worker_id, // Employee ID
            '', // Employee Email - blank
            $entry->worker_name, // Employee Name
            $entry->contractor_state ?? '', // Location - contractor's state
            $entry->contractor_name ?? '', // Department - contractor name
            $entry->worker_salary ?? '', // Salary
            'monthly', // Salary Type
            $allowance > 0 ? $allowance : '', // General Allowance
            $advanceSalary > 0 ? $advanceSalary : '', // ADVANCE SALARY
            $entry->ot_normal_hours > 0 ? $entry->ot_normal_hours : '', // Normal OT
            $entry->ot_public_hours > 0 ? $entry->ot_public_hours : '', // Public holiday OT
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 11]],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 15, // Employee ID
            'B' => 25, // Employee Email
            'C' => 30, // Employee Name
            'D' => 20, // Location
            'E' => 30, // Department
            'F' => 15, // Salary
            'G' => 15, // Salary Type
            'H' => 18, // General Allowance
            'I' => 18, // ADVANCE SALARY
            'J' => 12, // Normal
            'K' => 15, // Public holiday
        ];
    }
}
