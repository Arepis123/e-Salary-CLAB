<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class TimesheetController extends Controller
{
    /**
     * View specific payroll submission details
     */
    public function show(Request $request, $id)
    {
        $clabNo = $request->user()->contractor_clab_no;

        $submission = \App\Models\PayrollSubmission::with(['workers.transactions', 'payment'])
            ->where('id', $id)
            ->where('contractor_clab_no', $clabNo)
            ->firstOrFail();

        // Update penalty if invoice is overdue
        $submission->updatePenalty();
        $submission->refresh();

        return view('client.timesheet-detail', compact('submission'));
    }
}
