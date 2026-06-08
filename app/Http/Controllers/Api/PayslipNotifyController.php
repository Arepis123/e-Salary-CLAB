<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\PayslipReady;
use App\Models\PayrollSubmission;
use App\Models\User;
use App\Models\Worker;
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
     *   "file_name":       "SyukranMaju_Feb2026.zip", ← filename only, file must already exist in storage/app/payslips/
     *   "force_reupload":  true                       ← optional, default false. If true, re-sends email even if file was already uploaded
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
            'force_reupload'  => 'boolean',
        ]);

        $contractorName = trim($validated['contractor_name']);
        $month          = (int) $validated['month'];
        $year           = (int) $validated['year'];
        $fileName       = $validated['file_name'];
        $filePath       = 'payslips/'.$fileName;
        $forceReupload  = (bool) ($validated['force_reupload'] ?? false);

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

        // --- Check if this is a re-upload (file was already registered before) ---
        $isReupload = ! is_null($submission->payslip_file_path);

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
            'is_reupload'     => $isReupload,
            'force_reupload'  => $forceReupload,
        ]);

        // --- Send email notification to contractor ---
        // Skip if re-upload, unless force_reupload is explicitly set to true
        $emailSent = false;
        $shouldEmail = ! $isReupload || $forceReupload;
        if ($shouldEmail && $submission->user && $submission->user->email) {
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
            'is_reupload'     => $isReupload,
            'email_sent'      => $emailSent,
        ]);
    }

    /**
     * Look up the current contractor (company) name for a batch of worker
     * passport / IC numbers.
     *
     * Resolution chain (all in worker_db):
     *   workers.wkr_passno → workers.wkr_currentemp → contractors.ctr_clab_no → contractors.ctr_comp_name
     *
     * Expected request body (JSON):
     * {
     *   "ic_numbers": ["A1234567", "880123081234", "B7654321", "950505105566"]
     * }
     *
     * Required header:
     *   X-Payslip-Token: <PAYSLIP_API_SECRET from .env>
     */
    public function contractorsByIc(Request $request)
    {
        // --- Authenticate via shared secret token ---
        $secret = config('services.payslip_api.secret');
        if (! $secret || $request->header('X-Payslip-Token') !== $secret) {
            return response()->json(['success' => false, 'error' => 'Invalid token'], 401);
        }

        // --- Validate input ---
        $icNumbers = $request->input('ic_numbers');
        if (! is_array($icNumbers) || count($icNumbers) === 0) {
            return response()->json([
                'success' => false,
                'error'   => 'ic_numbers must be a non-empty array',
            ], 400);
        }

        // Normalise: trim, drop blanks, de-duplicate (preserve original order)
        $icNumbers = array_values(array_unique(array_filter(
            array_map(fn ($ic) => trim((string) $ic), $icNumbers),
            fn ($ic) => $ic !== ''
        )));

        if (count($icNumbers) === 0) {
            return response()->json([
                'success' => false,
                'error'   => 'ic_numbers must be a non-empty array',
            ], 400);
        }

        try {
            // Single join across worker_db: passport → current employer → company name
            $rows = Worker::query()
                ->whereIn('workers.wkr_passno', $icNumbers)
                ->leftJoin('contractors', 'workers.wkr_currentemp', '=', 'contractors.ctr_clab_no')
                ->get(['workers.wkr_passno', 'contractors.ctr_comp_name']);

            $results = [];
            foreach ($rows as $row) {
                // Only treat as resolved when a company name actually exists
                if (! empty($row->ctr_comp_name)) {
                    $results[$row->wkr_passno] = $row->ctr_comp_name;
                }
            }

            $notFound = array_values(array_diff($icNumbers, array_keys($results)));

            return response()->json([
                'success'   => true,
                'results'   => (object) $results,
                'not_found' => $notFound,
            ]);
        } catch (\Throwable $e) {
            Log::error('contractors-by-ic lookup failed', [
                'error' => $e->getMessage(),
                'count' => count($icNumbers),
            ]);

            return response()->json([
                'success' => false,
                'error'   => 'Database unavailable',
            ], 500);
        }
    }
}
