<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class OTTransactionExport implements FromCollection, WithColumnWidths, WithHeadings, WithMapping, WithStyles, WithTitle
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
        return 'OT & Transaction Report';
    }

    public function headings(): array
    {
        return [
            'Employee ID',
            'Employee Email',
            'Employee Name',
            'Passport No',
            'Location',
            'Department',
            'Salary',
            'OT Period',
            'Normal OT (hrs)',
            'Rest Day OT (hrs)',
            'Public Holiday OT (hrs)',
            'Total OT (hrs)',
            'Allowance',
            'Advance Salary',
            'Deduction',
            'Status',
            'Submitted At',
        ];
    }

    public function map($entry): array
    {
        // Get allowance, advance, and deduction from transactions
        $allowance = 0;
        $advanceSalary = 0;
        $deduction = 0;

        if ($entry->transactions) {
            foreach ($entry->transactions as $transaction) {
                if ($transaction->type === 'allowance') {
                    $allowance += $transaction->amount;
                } elseif ($transaction->type === 'advance_payment') {
                    $advanceSalary += $transaction->amount;
                } elseif ($transaction->type === 'deduction') {
                    $deduction += $transaction->amount;
                }
            }
        }

        $totalOT = $entry->ot_normal_hours + $entry->ot_rest_hours + $entry->ot_public_hours;

        return [
            $entry->worker_id,
            $entry->worker_email ?? '',
            $entry->worker_name,
            $entry->worker_passport ?? '',
            $entry->contractor_state ?? '',
            $entry->contractor_name ?? '',
            $entry->worker_salary ?? '',
            $entry->entry_period ?? '',
            $entry->ot_normal_hours > 0 ? $entry->ot_normal_hours : '',
            $entry->ot_rest_hours > 0 ? $entry->ot_rest_hours : '',
            $entry->ot_public_hours > 0 ? $entry->ot_public_hours : '',
            $totalOT > 0 ? $totalOT : '',
            $allowance > 0 ? $allowance : '',
            $advanceSalary > 0 ? $advanceSalary : '',
            $deduction > 0 ? $deduction : '',
            ucfirst($entry->status),
            $entry->submitted_at ? $entry->submitted_at->format('d/m/Y H:i') : '',
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
            'B' => 30, // Employee Email
            'C' => 30, // Employee Name
            'D' => 18, // Passport No
            'E' => 20, // Location
            'F' => 30, // Department
            'G' => 15, // Salary
            'H' => 18, // OT Period
            'I' => 15, // Normal OT
            'J' => 15, // Rest Day OT
            'K' => 18, // Public Holiday OT
            'L' => 15, // Total OT
            'M' => 15, // Allowance
            'N' => 15, // Advance Salary
            'O' => 15, // Deduction
            'P' => 12, // Status
            'Q' => 18, // Submitted At
        ];
    }
}
