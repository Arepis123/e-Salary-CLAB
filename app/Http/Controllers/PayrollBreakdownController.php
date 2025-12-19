<?php

namespace App\Http\Controllers;

use App\Models\PayrollSubmission;
use Illuminate\Support\Facades\Storage;

class PayrollBreakdownController extends Controller
{
    /**
     * Download the breakdown file for a payroll submission
     *
     * @param PayrollSubmission $submission
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function download(PayrollSubmission $submission)
    {
        // Authorization: Only admin or submission owner can download
        if (!auth()->user()->hasAdminAccess() &&
            auth()->user()->contractor_clab_no !== $submission->contractor_clab_no) {
            abort(403, 'Unauthorized access to breakdown file.');
        }

        // Check if breakdown file path exists in database
        if (!$submission->hasBreakdownFile()) {
            abort(404, 'Breakdown file not found.');
        }

        // Check if the physical file actually exists in storage
        if (!Storage::disk('local')->exists($submission->breakdown_file_path)) {
            // Log the missing file for admin awareness
            \Log::warning('Breakdown file missing from storage', [
                'submission_id' => $submission->id,
                'file_path' => $submission->breakdown_file_path,
                'contractor_clab_no' => $submission->contractor_clab_no,
            ]);

            abort(404, 'The breakdown file is missing from storage. Please contact the administrator for assistance.');
        }

        // Download the file from private storage
        return Storage::disk('local')->download(
            $submission->breakdown_file_path,
            $submission->breakdown_file_name
        );
    }
}
