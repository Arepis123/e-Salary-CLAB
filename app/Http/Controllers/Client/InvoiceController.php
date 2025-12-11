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

        if (!$clabNo) {
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
        $totalInvoiced = $allSubmissions->sum(function($submission) {
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
     * Download invoice as PDF
     */
    public function download($id)
    {
        $clabNo = auth()->user()->contractor_clab_no;
        $contractor = auth()->user();

        $invoice = PayrollSubmission::with(['workers.transactions', 'payment'])
            ->where('id', $id)
            ->where('contractor_clab_no', $clabNo)
            ->firstOrFail();

        $pdf = \PDF::loadView('client.invoice-pdf', compact('invoice', 'contractor'))
            ->setPaper('a4', 'landscape');

        $filename = 'Invoice-INV-' . str_pad($invoice->id, 4, '0', STR_PAD_LEFT) . '-' . $invoice->month_year . '.pdf';

        return $pdf->download($filename);
    }
}
