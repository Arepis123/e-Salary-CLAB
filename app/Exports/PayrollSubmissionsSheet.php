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
            'Transaction ID',
            'CLAB No',
            'Contractor Name',
            'Period',
            'FCL No',
            'Payroll Amount',
            'Admin Fee',
            'SST',
            'Total',
            'Penalty',
            'Grandtotal',
            'Status',
            'Payment Status',
            'Submitted Date',
            'Paid Date',
            'Payment Deadline',
            'OR No',
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

        // Payment status - check submission status instead of payment status
        $paymentStatus = ($submission->status === 'paid')
            ? 'Paid'
            : 'Awaiting Payment';

        // Transaction ID from Billplz - find the completed payment
        $transactionId = '-';
        if ($submission->payments) {
            $completedPayment = $submission->payments->firstWhere('status', 'completed');
            if ($completedPayment && $completedPayment->transaction_id) {
                $transactionId = $completedPayment->transaction_id;
            }
        }

        return [
            $rowNumber,
            'PAY'.str_pad($submission->id, 6, '0', STR_PAD_LEFT),
            $transactionId,
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
            $submission->tax_invoice_number ? $submission->tax_invoice_number : '-',
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

        // Format currency columns (H, I, J, K, L, M) - shifted due to Transaction ID column
        $lastRow = $this->submissions->count() + 1;
        $sheet->getStyle('H2:M'.$lastRow)->getNumberFormat()->setFormatCode('#,##0.00');

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
            'C' => 25,  // Transaction ID
            'D' => 12,  // CLAB No
            'E' => 30,  // Contractor Name
            'F' => 12,  // Period
            'G' => 12,  // Total Workers / FCL
            'H' => 14,  // Payroll Amount
            'I' => 14,  // Service Charge / Admin Fee
            'J' => 10,  // SST
            'K' => 14,  // Total (Payroll + Service + SST)
            'L' => 10,  // Penalty
            'M' => 16,  // Total with Penalty / Grandtotal
            'N' => 16,  // Status
            'O' => 16,  // Payment Status
            'P' => 18,  // Submitted Date
            'Q' => 18,  // Paid Date
            'R' => 16,  // Payment Deadline
            'S' => 16,  // OR No
        ];
    }
}
