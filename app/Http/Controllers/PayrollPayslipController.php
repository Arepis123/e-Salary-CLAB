<?php

namespace App\Http\Controllers;

use App\Models\PayrollSubmission;
use Illuminate\Support\Facades\Storage;

class PayrollPayslipController extends Controller
{
    /**
     * Download the payslip file for a payroll submission
     *
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function download(PayrollSubmission $submission)
    {
        // Authorization: Only admin or submission owner can download
        if (! auth()->user()->hasAdminAccess() &&
            auth()->user()->contractor_clab_no !== $submission->contractor_clab_no) {
            abort(403, 'Unauthorized access to payslip file.');
        }

        // Check if payslip file path exists in database
        if (! $submission->hasPayslipFile()) {
            abort(404, 'Payslip file not found.');
        }

        // Check if the physical file actually exists in storage
        if (! Storage::disk('local')->exists($submission->payslip_file_path)) {
            // Log the missing file for admin awareness
            \Log::warning('Payslip file missing from storage', [
                'submission_id' => $submission->id,
                'file_path' => $submission->payslip_file_path,
                'contractor_clab_no' => $submission->contractor_clab_no,
            ]);

            abort(404, 'The payslip file is missing from storage. Please contact the administrator for assistance.');
        }

        // Download the file from private storage
        return Storage::disk('local')->download(
            $submission->payslip_file_path,
            $submission->payslip_file_name
        );
    }
}
