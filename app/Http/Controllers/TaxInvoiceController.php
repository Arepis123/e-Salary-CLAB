<?php

namespace App\Http\Controllers;

use App\Models\PayrollSubmission;
use Illuminate\Http\Request;

class TaxInvoiceController extends Controller
{
    public function downloadReceipts(Request $request)
    {
        $invoiceIds = $request->input('invoices', []);
        $month = $request->input('month');
        $year = $request->input('year');

        if (empty($invoiceIds)) {
            abort(400, 'No invoices selected');
        }

        // Eager load all relationships at once for better performance
        $invoices = PayrollSubmission::with([
            'workers.transactions',
            'workers.worker',
            'payment',
            'user',
        ])
            ->whereIn('id', $invoiceIds)
            ->get();

        if ($invoices->isEmpty()) {
            abort(404, 'No receipts found');
        }

        // If only one invoice, download directly
        if ($invoices->count() === 1) {
            $invoice = $invoices->first();

            // Generate tax invoice number if not already generated
            if (! $invoice->hasTaxInvoice()) {
                $invoice->generateTaxInvoiceNumber();
                $invoice->refresh();
            }

            $contractor = $invoice->user;

            $pdf = \PDF::loadView('admin.tax-invoice-pdf', compact('invoice', 'contractor'))
                ->setPaper('a4', 'landscape')
                ->setOption('enable-local-file-access', true)
                ->setOption('no-stop-slow-scripts', true);

            $filename = 'Tax-Invoice-'.$invoice->tax_invoice_number.'-'.$invoice->month_year.'.pdf';

            return $pdf->download($filename);
        }

        // Multiple invoices - create ZIP file
        $zip = new \ZipArchive;
        $zipFilename = 'Tax-Invoices-'.\Carbon\Carbon::create($year, $month)->format('Y_m').'_'.now()->format('YmdHis').'.zip';
        $zipPath = storage_path('app/temp/'.$zipFilename);

        // Create temp directory if it doesn't exist
        if (! file_exists(storage_path('app/temp'))) {
            mkdir(storage_path('app/temp'), 0755, true);
        }

        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === true) {
            // Generate all tax invoice numbers first if needed
            foreach ($invoices as $invoice) {
                if (! $invoice->hasTaxInvoice()) {
                    $invoice->generateTaxInvoiceNumber();
                }
            }

            // Refresh invoices after generating numbers
            $invoices = PayrollSubmission::with([
                'workers.transactions',
                'workers.worker',
                'payment',
                'user',
            ])
                ->whereIn('id', $invoiceIds)
                ->get();

            // Generate PDFs and add to ZIP
            foreach ($invoices as $invoice) {
                $contractor = $invoice->user;

                $pdf = \PDF::loadView('admin.tax-invoice-pdf', compact('invoice', 'contractor'))
                    ->setPaper('a4', 'landscape')
                    ->setOption('enable-local-file-access', true)
                    ->setOption('no-stop-slow-scripts', true);

                $filename = 'Tax-Invoice-'.$invoice->tax_invoice_number.'-'.$invoice->month_year.'.pdf';

                $zip->addFromString($filename, $pdf->output());
            }

            $zip->close();

            return response()->download($zipPath, $zipFilename)->deleteFileAfterSend(true);
        }

        abort(500, 'Failed to create ZIP file');
    }
}
