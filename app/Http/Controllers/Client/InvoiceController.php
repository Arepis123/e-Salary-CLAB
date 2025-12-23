<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\PayrollSubmission;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    /**
     * Display invoices page
     */
    public function index(Request $request)
    {
        $clabNo = $request->user()->contractor_clab_no;

        if (! $clabNo) {
            return view('client.invoices', [
                'error' => 'No contractor CLAB number assigned to your account.',
            ]);
        }

        // Get all submissions (invoices) for this contractor
        $invoices = PayrollSubmission::where('contractor_clab_no', $clabNo)
            ->with(['payment'])
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->paginate(10);

        // Calculate statistics
        $pendingInvoices = PayrollSubmission::where('contractor_clab_no', $clabNo)
            ->whereIn('status', ['pending_payment', 'overdue'])
            ->count();

        $paidInvoices = PayrollSubmission::where('contractor_clab_no', $clabNo)
            ->where('status', 'paid')
            ->count();

        // Use total_due accessor to include dynamic penalty calculation
        $allSubmissions = PayrollSubmission::where('contractor_clab_no', $clabNo)->get();
        $totalInvoiced = $allSubmissions->sum(function ($submission) {
            return $submission->total_due;
        });

        $stats = [
            'pending_invoices' => $pendingInvoices,
            'paid_invoices' => $paidInvoices,
            'total_invoiced' => $totalInvoiced,
        ];

        return view('client.invoices', compact('invoices', 'stats'));
    }

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

        // Update penalty if invoice is overdue
        $invoice->updatePenalty();
        $invoice->refresh();

        return view('client.invoice-detail', compact('invoice'));
    }

    /**
     * Download Pro Forma Invoice as PDF (before payment)
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
