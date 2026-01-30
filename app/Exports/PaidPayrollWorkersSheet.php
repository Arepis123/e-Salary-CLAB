<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PaidPayrollWorkersSheet implements FromCollection, WithColumnWidths, WithHeadings, WithMapping, WithStyles, WithTitle
{
    protected $data;

    protected $period;

    protected $flattenedWorkers = [];

    public function __construct($data, $period = [])
    {
        $this->data = $data;
        $this->period = $period;

        // Flatten workers with contractor info
        foreach ($this->data as $contractor) {
            foreach ($contractor['workers'] as $worker) {
                $this->flattenedWorkers[] = [
                    'contractor_clab_no' => $contractor['contractor_clab_no'],
                    'contractor_name' => $contractor['contractor_name'],
                    'tax_invoice_number' => $contractor['tax_invoice_number'],
                    'paid_at' => $contractor['paid_at'],
                    'worker' => $worker,
                ];
            }
        }
    }

    public function collection()
    {
        return collect($this->flattenedWorkers);
    }

    public function title(): string
    {
        return 'Worker Details';
    }

    public function headings(): array
    {
        return [
            'No',
            'CLAB No',
            'Contractor Name',
            'Worker ID',
            'Worker Name',
            'Passport No',
            'Basic Salary',
            'OT Normal (hrs)',
            'OT Rest (hrs)',
            'OT Public (hrs)',
            'Total OT Pay',
            'Gross Salary',
            'Total Deductions',
            'Net Salary',
            'OR No',
            'Paid Date',
        ];
    }

    public function map($item): array
    {
        static $rowNumber = 0;
        $rowNumber++;

        $worker = $item['worker'];

        return [
            $rowNumber,
            $item['contractor_clab_no'],
            $item['contractor_name'],
            $worker['worker_id'],
            $worker['worker_name'],
            $worker['worker_passport'] ?? '-',
            $this->formatCurrency($worker['basic_salary']),
            number_format($worker['ot_normal_hours'] ?? 0, 2),
            number_format($worker['ot_rest_hours'] ?? 0, 2),
            number_format($worker['ot_public_hours'] ?? 0, 2),
            $this->formatCurrency($worker['total_ot_pay']),
            $this->formatCurrency($worker['gross_salary']),
            $this->formatCurrency($worker['total_deductions']),
            $this->formatCurrency($worker['net_salary']),
            $item['tax_invoice_number'] ?? '-',
            $item['paid_at'] ? \Carbon\Carbon::parse($item['paid_at'])->format('d M Y') : '-',
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
        // Style the header row
        $sheet->getStyle('1:1')->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 11,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '3B82F6'], // Blue color
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        // Set row height for header
        $sheet->getRowDimension(1)->setRowHeight(25);

        return [];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 6,   // No
            'B' => 15,  // CLAB No
            'C' => 30,  // Contractor Name
            'D' => 12,  // Worker ID
            'E' => 25,  // Worker Name
            'F' => 18,  // Passport No
            'G' => 15,  // Basic Salary
            'H' => 14,  // OT Normal
            'I' => 14,  // OT Rest
            'J' => 14,  // OT Public
            'K' => 15,  // Total OT Pay
            'L' => 15,  // Gross Salary
            'M' => 16,  // Total Deductions
            'N' => 15,  // Net Salary
            'O' => 18,  // OR No
            'P' => 15,  // Paid Date
        ];
    }
}
