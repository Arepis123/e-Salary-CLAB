<?php

namespace App\Exports;

use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PaymentSummarySheet implements FromCollection, WithColumnWidths, WithStyles, WithTitle
{
    protected $stats;

    protected $clientPayments;

    protected $topWorkers;

    protected $chartData;

    protected $selectedMonth;

    protected $selectedYear;

    public function __construct($stats, $clientPayments, $topWorkers, $chartData, $selectedMonth, $selectedYear)
    {
        $this->stats = $stats;
        $this->clientPayments = $clientPayments;
        $this->topWorkers = $topWorkers;
        $this->chartData = $chartData;
        $this->selectedMonth = $selectedMonth;
        $this->selectedYear = $selectedYear;
    }

    /**
     * Sheet title
     */
    public function title(): string
    {
        return 'Payment Summary';
    }

    /**
     * Return the collection of summary data
     */
    public function collection()
    {
        $period = Carbon::create($this->selectedYear, $this->selectedMonth)->format('F Y');
        $data = collect();

        // Report Header
        $data->push(['Payment Summary Report']);
        $data->push(['Generated:', now()->format('d M Y, h:i A')]);
        $data->push([]);

        // Period Info
        $data->push(['Report Period:', $period]);
        $data->push([]);

        // Overall Summary
        $data->push(['OVERALL SUMMARY']);
        $data->push([]);
        $data->push(['Metric', 'Value', '']);
        $data->push(['Total Paid', 'RM '.number_format($this->stats['total_paid'], 2), '']);
        $data->push(['Pending Amount', 'RM '.number_format($this->stats['pending_amount'], 2), '']);
        $data->push(['Average Salary', 'RM '.number_format($this->stats['average_salary'], 2), '']);
        $data->push(['Completed Payments', $this->stats['completed_payments'], '']);
        $data->push(['Pending Payments', $this->stats['pending_payments'], '']);
        $data->push([]);

        // Client Summary Section
        if (! empty($this->clientPayments)) {
            $data->push(['CLIENT SUMMARY']);
            $data->push([]);
            $data->push(['Metric', 'Value', '']);

            $totalClients = count($this->clientPayments);
            $paidClients = collect($this->clientPayments)->where('status', 'Paid')->count();
            $partiallyPaidClients = collect($this->clientPayments)->where('status', 'Partially Paid')->count();
            $pendingClients = collect($this->clientPayments)->where('status', 'Pending')->count();

            $data->push(['Total Clients', $totalClients, '']);
            $data->push(['Total Workers', collect($this->clientPayments)->sum('workers'), '']);
            $data->push(['Total Basic Salary', 'RM '.number_format(collect($this->clientPayments)->sum('basic_salary'), 2), '']);
            $data->push(['Total Overtime', 'RM '.number_format(collect($this->clientPayments)->sum('overtime'), 2), '']);
            $data->push(['Total Allowances', 'RM '.number_format(collect($this->clientPayments)->sum('allowances'), 2), '']);
            $data->push(['Total Deductions', 'RM '.number_format(collect($this->clientPayments)->sum('deductions'), 2), '']);
            $data->push(['Grand Total Amount', 'RM '.number_format(collect($this->clientPayments)->sum('total'), 2), '']);
            $data->push([]);
        }

        // Payment Status Breakdown
        if (! empty($this->clientPayments)) {
            $data->push(['PAYMENT STATUS BREAKDOWN']);
            $data->push([]);
            $data->push(['Status', 'Count', 'Percentage']);

            $totalClients = count($this->clientPayments);
            $paidClients = collect($this->clientPayments)->where('status', 'Paid')->count();
            $partiallyPaidClients = collect($this->clientPayments)->where('status', 'Partially Paid')->count();
            $pendingClients = collect($this->clientPayments)->where('status', 'Pending')->count();

            $data->push(['Paid', $paidClients, $totalClients > 0 ? round(($paidClients / $totalClients) * 100, 1).'%' : '0%']);
            $data->push(['Partially Paid', $partiallyPaidClients, $totalClients > 0 ? round(($partiallyPaidClients / $totalClients) * 100, 1).'%' : '0%']);
            $data->push(['Pending', $pendingClients, $totalClients > 0 ? round(($pendingClients / $totalClients) * 100, 1).'%' : '0%']);
        }

        return $data;
    }

    /**
     * Define column widths
     */
    public function columnWidths(): array
    {
        return [
            'A' => 30,  // Label/Metric
            'B' => 25,  // Value
            'C' => 15,  // Percentage/Additional info
        ];
    }

    /**
     * Apply styles to the spreadsheet
     */
    public function styles(Worksheet $sheet)
    {
        // Report title (row 1)
        $sheet->getStyle('A1')->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 16,
                'color' => ['rgb' => '1F2937'],
            ],
        ]);

        // Section headers styling
        $sectionRows = [];
        foreach ($sheet->getRowIterator() as $row) {
            $cellValue = $sheet->getCell('A'.$row->getRowIndex())->getValue();
            if (in_array($cellValue, ['OVERALL SUMMARY', 'CLIENT SUMMARY', 'PAYMENT STATUS BREAKDOWN'])) {
                $sectionRows[] = $row->getRowIndex();
            }
        }

        foreach ($sectionRows as $rowIndex) {
            $sheet->getStyle('A'.$rowIndex)->applyFromArray([
                'font' => [
                    'bold' => true,
                    'size' => 14,
                    'color' => ['rgb' => 'FFFFFF'],
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '6366F1'],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_LEFT,
                ],
            ]);
            $sheet->mergeCells('A'.$rowIndex.':C'.$rowIndex);
            $sheet->getRowDimension($rowIndex)->setRowHeight(25);
        }

        // Table headers styling (rows with "Metric", "Status")
        foreach ($sheet->getRowIterator() as $row) {
            $cellValue = $sheet->getCell('A'.$row->getRowIndex())->getValue();
            if (in_array($cellValue, ['Metric', 'Status'])) {
                $sheet->getStyle('A'.$row->getRowIndex().':C'.$row->getRowIndex())->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'size' => 11,
                        'color' => ['rgb' => 'FFFFFF'],
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '94A3B8'],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);
                $sheet->getRowDimension($row->getRowIndex())->setRowHeight(20);
            }
        }

        // Apply borders to data tables
        $maxRow = $sheet->getHighestRow();
        $sheet->getStyle('A1:C'.$maxRow)->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'E5E7EB'],
                ],
            ],
        ]);

        return [];
    }
}
