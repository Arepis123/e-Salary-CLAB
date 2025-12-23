<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class WorkerDetailsSheet implements FromCollection, WithColumnWidths, WithHeadings, WithMapping, WithStyles, WithTitle
{
    protected $workers;

    public function __construct($workers)
    {
        $this->workers = $workers;
    }

    public function collection()
    {
        return $this->workers;
    }

    public function title(): string
    {
        return 'Worker Details';
    }

    public function headings(): array
    {
        return [
            'Worker ID',
            'Name',
            'Passport Number',
            'CLAB ID',
            'Passport Expiry',
            'Permit Expiry',
            'Nationality',
            'Position/Trade',
            'Gender',
            'Phone',
            'Basic Salary',
            'Contract Start',
            'Contract End',
            'Contract Status',
        ];
    }

    public function map($worker): array
    {
        // Handle both client context (contract_info) and admin context (activeContract)
        $contract = null;
        if (is_object($worker) && isset($worker->contract_info)) {
            // Client context
            $contract = $worker->contract_info;
        } elseif (is_object($worker) && isset($worker->activeContract)) {
            // Admin context
            $contract = $worker->activeContract;
        } elseif (is_object($worker)) {
            // Try to load the relationship if not loaded
            $worker->load('activeContract');
            $contract = $worker->activeContract;
        }

        $contractActive = $contract && $contract->isActive();

        // Get CLAB ID
        $clabId = '-';
        if ($contract && isset($contract->con_ctr_clab_no)) {
            $clabId = $contract->con_ctr_clab_no;
        } elseif (is_object($worker) && isset($worker->wkr_currentemp)) {
            $clabId = $worker->wkr_currentemp ?? '-';
        }

        return [
            is_object($worker) ? $worker->wkr_id : ($worker['wkr_id'] ?? '-'),
            is_object($worker) ? $worker->name : ($worker['name'] ?? '-'),
            is_object($worker) ? $worker->ic_number : ($worker['ic_number'] ?? '-'),
            $clabId,
            is_object($worker) && $worker->wkr_passexp ? $worker->wkr_passexp->format('Y-m-d') : '-',
            is_object($worker) && $worker->wkr_permitexp ? $worker->wkr_permitexp->format('Y-m-d') : '-',
            is_object($worker) && $worker->country ? $worker->country->cty_desc : '-',
            is_object($worker) ? ($worker->position ?? ($worker->workTrade ? $worker->workTrade->trade_desc : '-')) : '-',
            is_object($worker) ? $this->getGender($worker->wkr_gender) : '-',
            is_object($worker) ? ($worker->phone ?? '-') : '-',
            is_object($worker) && $worker->basic_salary ? 'RM '.number_format($worker->basic_salary, 2) : '-',
            $contract ? $contract->con_start->format('Y-m-d') : '-',
            $contract ? $contract->con_end->format('Y-m-d') : '-',
            $contractActive ? 'Active' : 'Inactive',
        ];
    }

    private function getGender($gender)
    {
        return match ($gender) {
            1 => 'Male',
            2 => 'Female',
            default => '-'
        };
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
            'A' => 15, // Worker ID
            'B' => 25, // Name
            'C' => 20, // Passport Number
            'D' => 18, // CLAB ID
            'E' => 18, // Passport Expiry
            'F' => 18, // Permit Expiry
            'G' => 20, // Nationality
            'H' => 25, // Position/Trade
            'I' => 12, // Gender
            'J' => 18, // Phone
            'K' => 18, // Basic Salary
            'L' => 18, // Contract Start
            'M' => 18, // Contract End
            'N' => 18, // Contract Status
        ];
    }
}
