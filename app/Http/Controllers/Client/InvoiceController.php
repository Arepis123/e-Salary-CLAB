<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\PayrollSubmission;

class InvoiceController extends Controller
{
    /**
     * Show individual invoice details
     */
    public function show($id)
    {
        $clabNo = auth()->user()->contractor_clab_no;

        $invoice = PayrollSubmission::with(['workers.transactions', 'payment'])
            ->where('id', $id)
            ->where('contractor_clab_no', $clabNo)
            ->firstOrFail();

        // Block access to draft submissions - they should be edited, not viewed as invoices
        if ($invoice->status === 'draft') {
            return redirect()->route('timesheet.edit', $invoice->id)
                ->with('error', 'Draft submissions cannot be viewed as invoices. Please complete the draft first.');
        }

        // Update penalty if invoice is overdue
        $invoice->updatePenalty();
        $invoice->refresh();

        return view('client.invoice-detail', compact('invoice'));
    }

    /**
     * Download Invoice as PDF (before payment)
     */
    public function download($id)
    {
        // Increase timeout for PDF generation (especially for large payrolls)
        set_time_limit(env('PHP_MAX_EXECUTION_TIME', 300));

        $clabNo = auth()->user()->contractor_clab_no;
        $contractor = auth()->user();

        $invoice = PayrollSubmission::with(['workers.transactions', 'payment'])
            ->where('id', $id)
            ->where('contractor_clab_no', $clabNo)
            ->firstOrFail();

        // Block download for draft submissions
        if ($invoice->status === 'draft') {
            return redirect()->route('timesheet.edit', $invoice->id)
                ->with('error', 'Draft submissions cannot be downloaded as invoices. Please complete the draft first.');
        }

        $pdf = \PDF::loadView('client.invoice-pdf', compact('invoice', 'contractor'))
            ->setPaper('a4', 'landscape');

        $filename = 'ProForma-Invoice-'.str_pad($invoice->id, 4, '0', STR_PAD_LEFT).'-'.$invoice->month_year.'.pdf';

        return $pdf->download($filename);
    }

    /**
     * Download Receipt as PDF (after payment)
     * Only available for paid invoices
     */
    public function downloadTaxInvoice($id)
    {
        // Increase timeout for PDF generation (especially for large payrolls)
        set_time_limit(env('PHP_MAX_EXECUTION_TIME', 300));

        $clabNo = auth()->user()->contractor_clab_no;
        $contractor = auth()->user();

        $invoice = PayrollSubmission::with(['workers.transactions', 'payment'])
            ->where('id', $id)
            ->where('contractor_clab_no', $clabNo)
            ->firstOrFail();

        // Only allow tax invoice download for paid invoices
        if ($invoice->status !== 'paid') {
            return redirect()->back()->with('error', 'Tax invoice is only available for paid invoices.');
        }

        // Generate tax invoice number if not already generated
        if (! $invoice->hasTaxInvoice()) {
            $invoice->generateTaxInvoiceNumber();
            $invoice->refresh();
        }

        $pdf = \PDF::loadView('client.tax-invoice-pdf', compact('invoice', 'contractor'))
            ->setPaper('a4', 'landscape');

        $filename = 'Tax-Invoice-'.$invoice->tax_invoice_number.'-'.$invoice->month_year.'.pdf';

        return $pdf->download($filename);
    }
}
