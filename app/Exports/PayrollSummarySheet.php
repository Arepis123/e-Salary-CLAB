<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PayrollSummarySheet implements FromCollection, WithColumnWidths, WithStyles, WithTitle
{
    protected $submissions;

    protected $filters;

    public function __construct($submissions, $filters = [])
    {
        $this->submissions = $submissions;
        $this->filters = $filters;
    }

    /**
     * Sheet title
     */
    public function title(): string
    {
        return 'Summary';
    }

    /**
     * Return the collection of summary data
     */
    public function collection()
    {
        $data = collect();

        // Report Header
        $data->push(['Payroll Submissions Report']);
        $data->push(['Generated:', now()->format('d M Y, h:i A')]);
        $data->push([]);

        // Applied Filters
        $filterInfo = [];
        if (! empty($this->filters['search'])) {
            $filterInfo[] = 'Search: '.$this->filters['search'];
        }
        if (! empty($this->filters['contractor'])) {
            $filterInfo[] = 'Contractor: '.$this->filters['contractor'];
        }
        if (! empty($this->filters['status'])) {
            $filterInfo[] = 'Status: '.ucfirst($this->filters['status']);
        }
        if (! empty($this->filters['payment_status'])) {
            $filterInfo[] = 'Payment: '.ucfirst($this->filters['payment_status']);
        }

        if (! empty($filterInfo)) {
            $data->push(['Filters Applied:', implode(', ', $filterInfo)]);
            $data->push([]);
        }

        // Overall Summary
        $data->push(['OVERALL SUMMARY']);
        $data->push([]);
        $data->push(['Metric', 'Value']);
        $data->push(['Total Submissions', $this->submissions->count()]);
        $data->push(['Total Workers', $this->submissions->sum('total_workers')]);
        $data->push(['Total Payroll Amount', 'RM '.number_format($this->submissions->sum('admin_final_amount'), 2)]);
        $data->push(['Total Service Charges', 'RM '.number_format($this->submissions->sum('service_charge'), 2)]);
        $data->push(['Total SST', 'RM '.number_format($this->submissions->sum('sst'), 2)]);
        $totalClientAmount = $this->submissions->sum(fn($s) => $s->client_total);
        $data->push(['Grand Total (Payroll + Service + SST)', 'RM '.number_format($totalClientAmount, 2)]);
        $data->push(['Total Penalties', 'RM '.number_format($this->submissions->sum('penalty_amount'), 2)]);
        $data->push([]);

        // Status Breakdown
        $data->push(['STATUS BREAKDOWN']);
        $data->push([]);
        $data->push(['Status', 'Count', 'Percentage']);

        $total = $this->submissions->count();
        $completed = $this->submissions->where('status', 'paid')->count();
        $pending = $this->submissions->whereIn('status', ['pending_payment', 'overdue'])->count();
        $draft = $this->submissions->where('status', 'draft')->count();

        $data->push(['Completed', $completed, $total > 0 ? round(($completed / $total) * 100, 1).'%' : '0%']);
        $data->push(['Pending Payment', $pending, $total > 0 ? round(($pending / $total) * 100, 1).'%' : '0%']);
        $data->push(['Draft', $draft, $total > 0 ? round(($draft / $total) * 100, 1).'%' : '0%']);
        $data->push([]);

        // Payment Status Breakdown
        $data->push(['PAYMENT STATUS BREAKDOWN']);
        $data->push([]);
        $data->push(['Payment Status', 'Count', 'Percentage']);

        $paidCount = $this->submissions->filter(function ($s) {
            return $s->payment && $s->payment->status === 'completed';
        })->count();
        $awaitingCount = $total - $paidCount;

        $data->push(['Paid', $paidCount, $total > 0 ? round(($paidCount / $total) * 100, 1).'%' : '0%']);
        $data->push(['Awaiting Payment', $awaitingCount, $total > 0 ? round(($awaitingCount / $total) * 100, 1).'%' : '0%']);

        return $data;
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
            if (in_array($cellValue, ['OVERALL SUMMARY', 'STATUS BREAKDOWN', 'PAYMENT STATUS BREAKDOWN'])) {
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

        // Table headers styling (rows with "Metric", "Status", "Payment Status")
        foreach ($sheet->getRowIterator() as $row) {
            $cellValue = $sheet->getCell('A'.$row->getRowIndex())->getValue();
            if (in_array($cellValue, ['Metric', 'Status', 'Payment Status'])) {
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
}
