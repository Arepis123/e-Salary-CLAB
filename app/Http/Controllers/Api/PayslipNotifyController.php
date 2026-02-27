<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\PayslipReady;
use App\Models\PayrollSubmission;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class PayslipNotifyController extends Controller
{
    /**
     * Called by external Python FTP system after uploading a payslip file.
     *
     * Expected request body (JSON):
     * {
     *   "contractor_name": "SYUKRAN MAJU SDN BHD",   ← name as known by Python
     *   "month":           2,
     *   "year":            2026,
     *   "file_name":       "SyukranMaju_Feb2026.zip"  ← filename only, file must already exist in storage/app/payslips/
     * }
     *
     * Required header:
     *   X-Payslip-Token: <PAYSLIP_API_SECRET from .env>
     */
    public function notify(Request $request)
    {
        // --- Authenticate via shared secret token ---
        $secret = config('services.payslip_api.secret');
        if (! $secret || $request->header('X-Payslip-Token') !== $secret) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // --- Validate input ---
        $validated = $request->validate([
            'contractor_name' => 'required|string',
            'month'           => 'required|integer|min:1|max:12',
            'year'            => 'required|integer|min:2020',
            'file_name'       => 'required|string',
        ]);

        $contractorName = trim($validated['contractor_name']);
        $month          = (int) $validated['month'];
        $year           = (int) $validated['year'];
        $fileName       = $validated['file_name'];
        $filePath       = 'payslips/'.$fileName;

        // --- Resolve contractor name → CLAB number ---
        // Try exact match first, then case-insensitive
        $client = User::where('role', 'client')
            ->where('name', $contractorName)
            ->first();

        if (! $client) {
            $client = User::where('role', 'client')
                ->whereRaw('LOWER(name) = ?', [strtolower($contractorName)])
                ->first();
        }

        if (! $client) {
            // Return helpful list of close names for debugging
            $similar = User::where('role', 'client')
                ->whereRaw('LOWER(name) LIKE ?', ['%'.strtolower(substr($contractorName, 0, 10)).'%'])
                ->pluck('name')
                ->take(5);

            return response()->json([
                'error'            => 'Contractor name not found',
                'contractor_name'  => $contractorName,
                'did_you_mean'     => $similar,
            ], 404);
        }

        $clabNo = $client->contractor_clab_no;

        // --- Verify the file actually exists on disk ---
        if (! Storage::disk('local')->exists($filePath)) {
            return response()->json([
                'error'    => 'File not found on server',
                'expected' => storage_path('app/'.$filePath),
            ], 422);
        }

        // --- Find the matching submission ---
        $submission = PayrollSubmission::where('contractor_clab_no', $clabNo)
            ->where('month', $month)
            ->where('year', $year)
            ->whereNotIn('status', ['draft'])
            ->latest()
            ->first();

        if (! $submission) {
            return response()->json([
                'error'           => 'No submission found for this contractor and period',
                'clab_no'         => $clabNo,
                'contractor_name' => $contractorName,
                'month'           => $month,
                'year'            => $year,
            ], 404);
        }

        // --- Update the submission ---
        $submission->update([
            'payslip_file_path' => $filePath,
            'payslip_file_name' => $fileName,
        ]);

        Log::info('Payslip registered via API', [
            'submission_id'   => $submission->id,
            'contractor_name' => $contractorName,
            'clab_no'         => $clabNo,
            'month'           => $month,
            'year'            => $year,
            'file'            => $filePath,
        ]);

        // --- Send email notification to contractor ---
        $emailSent = false;
        if ($submission->user && $submission->user->email) {
            Mail::to($submission->user->email)->send(new PayslipReady($submission)); // Comment this line if dont want sent email
            $emailSent = true;
        }

        return response()->json([
            'success'         => true,
            'submission_id'   => $submission->id,
            'contractor_name' => $contractorName,
            'clab_no'         => $clabNo,
            'period'          => $month.'/'.$year,
            'file_path'       => $filePath,
            'email_sent'      => $emailSent,
        ]);
    }
}
