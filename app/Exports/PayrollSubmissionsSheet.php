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

class PayrollSubmissionsSheet implements FromCollection, WithColumnWidths, WithHeadings, WithMapping, WithStyles, WithTitle
{
    protected $submissions;

    protected $filters;

    public function __construct($submissions, $filters = [])
    {
        $this->submissions = $submissions;
        $this->filters = $filters;
    }

    /**
     * Return the collection of submissions to export
     */
    public function collection()
    {
        return collect($this->submissions);
    }

    /**
     * Sheet title
     */
    public function title(): string
    {
        return 'Payroll Submissions';
    }

    /**
     * Define the column headings
     */
    public function headings(): array
    {
        return [
            'No',
            'Submission ID',
            'CLAB No',
            'Contractor Name',
            'Period',
            'Total Workers',
            'Payroll Amount',
            'Service Charge',
            'SST',
            'Total (Payroll + Service + SST)',
            'Penalty',
            'Total with Penalty',
            'Status',
            'Payment Status',
            'Submitted Date',
            'Paid Date',
            'Payment Deadline',
        ];
    }

    /**
     * Map each submission to spreadsheet row
     */
    public function map($submission): array
    {
        static $rowNumber = 0;
        $rowNumber++;

        // Status mapping
        $status = match ($submission->status) {
            'paid' => 'Completed',
            'pending_payment' => 'Pending Payment',
            'overdue' => 'Overdue',
            'draft' => 'Draft',
            default => ucfirst($submission->status)
        };

        // Payment status
        $paymentStatus = ($submission->payment && $submission->payment->status === 'completed')
            ? 'Paid'
            : 'Awaiting Payment';

        return [
            $rowNumber,
            'PAY'.str_pad($submission->id, 6, '0', STR_PAD_LEFT),
            $submission->contractor_clab_no,
            $submission->user ? $submission->user->name : 'Client '.$submission->contractor_clab_no,
            $submission->month_year,
            $submission->total_workers,
            $submission->admin_final_amount ?? 0,
            $submission->service_charge,
            $submission->sst,
            $submission->client_total,
            $submission->penalty_amount ?? 0,
            $submission->total_due,
            $status,
            $paymentStatus,
            $submission->submitted_at ? $submission->submitted_at->format('d M Y H:i') : 'Not submitted',
            $submission->paid_at ? $submission->paid_at->format('d M Y H:i') : '-',
            $submission->payment_deadline ? $submission->payment_deadline->format('d M Y') : '-',
        ];
    }

    /**
     * Apply styles to the spreadsheet
     */
    public function styles(Worksheet $sheet)
    {
        // Style the header row
        $sheet->getStyle('1:1')->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 12,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '6366F1'], // Indigo color
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        // Set row height for header
        $sheet->getRowDimension(1)->setRowHeight(25);

        // Format currency columns (G, H, I, J, K, L)
        $lastRow = $this->submissions->count() + 1;
        $sheet->getStyle('G2:L'.$lastRow)->getNumberFormat()->setFormatCode('#,##0.00');

        return [];
    }

    /**
     * Define column widths
     */
    public function columnWidths(): array
    {
        return [
            'A' => 6,   // No
            'B' => 15,  // Submission ID
            'C' => 12,  // CLAB No
            'D' => 30,  // Contractor Name
            'E' => 12,  // Period
            'F' => 12,  // Total Workers
            'G' => 14,  // Total Amount
            'H' => 14,  // Service Charge
            'I' => 10,  // SST
            'J' => 14,  // Grand Total
            'K' => 10,  // Penalty
            'L' => 16,  // Total with Penalty
            'M' => 16,  // Status
            'N' => 16,  // Payment Status
            'O' => 18,  // Submitted Date
            'P' => 18,  // Paid Date
            'Q' => 16,  // Payment Deadline
        ];
    }
}
