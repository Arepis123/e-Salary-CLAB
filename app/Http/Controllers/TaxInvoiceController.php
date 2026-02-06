<?php

namespace App\Http\Controllers;

use App\Models\PayrollSubmission;
use Illuminate\Http\Request;

class TaxInvoiceController extends Controller
{
    /**
     * Download a single receipt (for client use)
     */
    public function downloadSingleReceipt($id)
    {
        $invoice = PayrollSubmission::with([
            'workers.transactions',
            'workers.worker',
            'payment',
            'user',
        ])->findOrFail($id);

        // Check if user has access to this invoice
        $user = auth()->user();
        if ($user->isClient() && $invoice->contractor_clab_no !== $user->contractor_clab_no) {
            abort(403, 'You do not have access to this receipt');
        }

        // Check if receipt is available (must be paid with tax invoice)
        if ($invoice->status !== 'paid' || ! $invoice->hasTaxInvoice()) {
            abort(404, 'Receipt not available');
        }

        $contractor = $invoice->user;

        $pdf = \PDF::loadView('admin.tax-invoice-pdf', compact('invoice', 'contractor'))
            ->setPaper('a4', 'landscape')
            ->setOption('enable-local-file-access', true)
            ->setOption('no-stop-slow-scripts', true);

        $filename = 'Receipt-'.$invoice->tax_invoice_number.'-'.$invoice->month_year.'.pdf';

        return $pdf->download($filename);
    }

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

        // Multiple invoices - generate a single combined PDF (much faster than separate PDFs + ZIP)
        // Generate all tax invoice numbers first if needed
        foreach ($invoices as $invoice) {
            if (! $invoice->hasTaxInvoice()) {
                $invoice->generateTaxInvoiceNumber();
            }
        }

        // Generate single multi-page PDF (one wkhtmltopdf process instead of N)
        $pdf = \PDF::loadView('admin.tax-invoice-bulk-pdf', compact('invoices'))
            ->setPaper('a4', 'landscape')
            ->setOption('enable-local-file-access', true)
            ->setOption('no-stop-slow-scripts', true);

        $filename = 'Official-Receipts-'.\Carbon\Carbon::create($year, $month)->format('Y_m').'_'.now()->format('YmdHis').'.pdf';

        return $pdf->download($filename);
    }
}
