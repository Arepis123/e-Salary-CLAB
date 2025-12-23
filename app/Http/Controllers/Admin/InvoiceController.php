<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PayrollSubmission;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    /**
     * Display all invoices (Admin can view all invoices from all contractors)
     */
    public function index(Request $request)
    {
        // Use a wrapper view that loads the Livewire component
        return view('admin.invoices-wrapper');
    }

    /**
     * Show individual invoice details (Admin can view any invoice)
     */
    public function show($id)
    {
        $invoice = PayrollSubmission::with(['workers.transactions', 'payment', 'user'])
            ->where('id', $id)
            ->firstOrFail();

        return view('admin.invoice-detail', compact('invoice'));
    }

    /**
     * Download Pro Forma Invoice as PDF (Admin can download any invoice)
     */
    public function download($id)
    {
        $invoice = PayrollSubmission::with(['workers.transactions', 'payment', 'user'])
            ->where('id', $id)
            ->firstOrFail();

        $contractor = $invoice->user;

        $pdf = \PDF::loadView('admin.invoice-pdf', compact('invoice', 'contractor'))
            ->setPaper('a4', 'landscape');

        $filename = 'ProForma-Invoice-'.str_pad($invoice->id, 4, '0', STR_PAD_LEFT).'-'.$invoice->month_year.'.pdf';

        return $pdf->download($filename);
    }

    /**
     * Download Receipt as PDF (Admin can download any paid invoice)
     */
    public function downloadTaxInvoice($id)
    {
        $invoice = PayrollSubmission::with(['workers.transactions', 'payment', 'user'])
            ->where('id', $id)
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

        $contractor = $invoice->user;

        $pdf = \PDF::loadView('admin.tax-invoice-pdf', compact('invoice', 'contractor'))
            ->setPaper('a4', 'landscape');

        $filename = 'Tax-Invoice-'.$invoice->tax_invoice_number.'-'.$invoice->month_year.'.pdf';

        return $pdf->download($filename);
    }
}
