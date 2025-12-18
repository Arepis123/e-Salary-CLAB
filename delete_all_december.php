<?php

/**
 * Delete ALL December 2025 Payroll - No Questions Asked
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "\n========================================\n";
echo "Checking December 2025 Payroll...\n";
echo "========================================\n\n";

// Check what exists first
$submissions = DB::table('payroll_submissions')
    ->where('month', 12)
    ->where('year', 2025)
    ->get();

if ($submissions->isEmpty()) {
    echo "No December 2025 submissions found.\n\n";
    exit(0);
}

echo "Found submissions:\n";
foreach ($submissions as $sub) {
    echo "- ID: {$sub->id}, CLAB: {$sub->contractor_clab_no}, Status: {$sub->status}, Submitted: {$sub->submitted_at}\n";
}
echo "\n";

// Get all IDs
$ids = $submissions->pluck('id')->toArray();
echo "Submission IDs to delete: " . implode(', ', $ids) . "\n\n";

// Start deletion
echo "Starting deletion...\n\n";

try {
    DB::beginTransaction();

    // Get worker IDs first
    $workerIds = DB::table('payroll_workers')
        ->whereIn('payroll_submission_id', $ids)
        ->pluck('id')
        ->toArray();

    echo "Worker IDs found: " . count($workerIds) . "\n";

    // Delete in order
    if (!empty($workerIds)) {
        $t = DB::table('payroll_worker_transactions')->whereIn('payroll_worker_id', $workerIds)->delete();
        echo "Deleted {$t} transactions\n";
    }

    $w = DB::table('payroll_workers')->whereIn('payroll_submission_id', $ids)->delete();
    echo "Deleted {$w} workers\n";

    $p = DB::table('payroll_payments')->whereIn('payroll_submission_id', $ids)->delete();
    echo "Deleted {$p} payments\n";

    $s = DB::table('payroll_submissions')->whereIn('id', $ids)->delete();
    echo "Deleted {$s} submissions\n";

    DB::commit();
    echo "\n✅ ALL DELETED!\n\n";

} catch (\Exception $e) {
    DB::rollBack();
    echo "\n❌ ERROR: " . $e->getMessage() . "\n\n";
    exit(1);
}

// Verify deletion
$remaining = DB::table('payroll_submissions')
    ->where('month', 12)
    ->where('year', 2025)
    ->count();

echo "Remaining December 2025 submissions: {$remaining}\n\n";

if ($remaining > 0) {
    echo "⚠️  WARNING: Some submissions still remain!\n";
    echo "Showing remaining submissions:\n";
    $still = DB::table('payroll_submissions')
        ->where('month', 12)
        ->where('year', 2025)
        ->get();
    foreach ($still as $s) {
        echo "- ID: {$s->id}, CLAB: {$s->contractor_clab_no}, Status: {$s->status}\n";
    }
    echo "\n";
} else {
    echo "✅ Verification: December 2025 is completely clear!\n\n";
}
