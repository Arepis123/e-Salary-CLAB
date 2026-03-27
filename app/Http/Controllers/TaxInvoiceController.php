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
        set_time_limit(120);

        $invoiceIds = $request->input('invoices', []);
        $month      = $request->input('month');
        $year       = $request->input('year');

        if (empty($invoiceIds)) {
            abort(400, 'No invoices selected');
        }

        // Only load what the receipt template actually needs — no worker rows
        $invoices = PayrollSubmission::with(['payment', 'user'])
            ->whereIn('id', $invoiceIds)
            ->get();

        if ($invoices->isEmpty()) {
            abort(404, 'No receipts found');
        }

        // Generate any missing tax invoice numbers
        foreach ($invoices as $invoice) {
            if (! $invoice->hasTaxInvoice()) {
                $invoice->generateTaxInvoiceNumber();
            }
        }

        // If only one invoice, download directly
        if ($invoices->count() === 1) {
            $invoice    = $invoices->first();
            $contractor = $invoice->user;

            $contractorRecord = \App\Models\Contractor::find($invoice->contractor_clab_no);
            $contractorState  = null;
            if ($contractorRecord && $contractorRecord->ctr_state) {
                $contractorState = \DB::connection('worker_db')
                    ->table('mst_states')
                    ->where('state_id', $contractorRecord->ctr_state)
                    ->value('state_name');
            }

            $pdf = \PDF::loadView('admin.tax-invoice-pdf', compact('invoice', 'contractor', 'contractorRecord', 'contractorState'))
                ->setPaper('a4', 'portrait')
                ->setOption('enable-local-file-access', true);

            $filename = 'Receipt-'.$invoice->tax_invoice_number.'-'.$invoice->month_year.'.pdf';

            return $pdf->download($filename);
        }

        // Multiple invoices — batch-load contractor records and states from worker_db
        $clabNos = $invoices->pluck('contractor_clab_no')->unique()->toArray();

        $contractorRecords = \App\Models\Contractor::whereIn('ctr_clab_no', $clabNos)
            ->get()->keyBy('ctr_clab_no');

        $stateIds = $contractorRecords->pluck('ctr_state')->filter()->unique()->toArray();
        $states   = \DB::connection('worker_db')
            ->table('mst_states')
            ->whereIn('state_id', $stateIds)
            ->pluck('state_name', 'state_id');

        // Attach contractorRecord and contractorState to each invoice for the template
        foreach ($invoices as $invoice) {
            $record                  = $contractorRecords->get($invoice->contractor_clab_no);
            $invoice->_contractorRecord = $record;
            $invoice->_contractorState  = $record && $record->ctr_state ? ($states[$record->ctr_state] ?? null) : null;
        }

        $pdf = \PDF::loadView('admin.tax-invoice-bulk-pdf', compact('invoices'))
            ->setPaper('a4', 'portrait')
            ->setOption('enable-local-file-access', true);

        $filename = 'Official-Receipts-'.\Carbon\Carbon::create($year, $month)->format('Y_m').'_'.now()->format('YmdHis').'.pdf';

        return $pdf->download($filename);
    }
}
