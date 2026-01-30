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

class PaidPayrollSummarySheet implements FromCollection, WithColumnWidths, WithHeadings, WithMapping, WithStyles, WithTitle
{
    protected $data;

    protected $period;

    public function __construct($data, $period = [])
    {
        $this->data = $data;
        $this->period = $period;
    }

    public function collection()
    {
        return collect($this->data);
    }

    public function title(): string
    {
        return 'Paid Payroll Summary';
    }

    public function headings(): array
    {
        return [
            'No',
            'CLAB No',
            'Contractor Name',
            'Total Workers',
            'Total Basic Salary',
            'Total OT Pay',
            'Total Deductions',
            'Total Net Salary',
            'Payroll Amount',
            'Service Charge',
            'SST',
            'Penalty',
            'Total Paid',
            'OR No',
            'Transaction ID',
            'Paid Date',
        ];
    }

    public function map($item): array
    {
        static $rowNumber = 0;
        $rowNumber++;

        return [
            $rowNumber,
            $item['contractor_clab_no'],
            $item['contractor_name'],
            $item['total_workers'],
            $this->formatCurrency($item['total_basic_salary']),
            $this->formatCurrency($item['total_ot_pay']),
            $this->formatCurrency($item['total_deductions']),
            $this->formatCurrency($item['total_net_salary']),
            $this->formatCurrency($item['admin_final_amount']),
            $this->formatCurrency($item['service_charge']),
            $this->formatCurrency($item['sst']),
            $this->formatCurrency($item['penalty']),
            $this->formatCurrency($item['total_paid']),
            $item['tax_invoice_number'] ?? '-',
            $item['transaction_id'] ?? '-',
            $item['paid_at'] ? \Carbon\Carbon::parse($item['paid_at'])->format('d M Y H:i') : '-',
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
                'startColor' => ['rgb' => '10B981'], // Green color for paid
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
            'D' => 14,  // Total Workers
            'E' => 18,  // Total Basic Salary
            'F' => 15,  // Total OT Pay
            'G' => 16,  // Total Deductions
            'H' => 18,  // Total Net Salary
            'I' => 16,  // Payroll Amount
            'J' => 14,  // Service Charge
            'K' => 12,  // SST
            'L' => 12,  // Penalty
            'M' => 16,  // Total Paid
            'N' => 18,  // OR No
            'O' => 25,  // Transaction ID
            'P' => 18,  // Paid Date
        ];
    }
}
