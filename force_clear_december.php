<?php

/**
 * Force Clear December 2025 Payroll Data
 *
 * This script uses raw database queries to forcefully delete
 * all December 2025 payroll data.
 *
 * Run this script using: php force_clear_december.php
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "\n";
echo "==========================================\n";
echo "  FORCE Clear December 2025 Payroll\n";
echo "==========================================\n\n";

try {
    // Start a transaction
    DB::beginTransaction();

    // Get submission IDs for December 2025
    $submissionIds = DB::table('payroll_submissions')
        ->where('month', 12)
        ->where('year', 2025)
        ->pluck('id')
        ->toArray();

    if (empty($submissionIds)) {
        echo "✓ No December 2025 payroll data found. Nothing to delete.\n\n";
        DB::rollBack();
        exit(0);
    }

    echo "Found " . count($submissionIds) . " December 2025 submission(s)\n";
    echo "Submission IDs: " . implode(', ', $submissionIds) . "\n\n";

    // Get worker IDs for these submissions
    $workerIds = DB::table('payroll_workers')
        ->whereIn('payroll_submission_id', $submissionIds)
        ->pluck('id')
        ->toArray();

    echo "Found " . count($workerIds) . " worker record(s)\n\n";

    echo "🗑️  Deleting data...\n\n";

    // Delete transactions (if worker IDs exist)
    if (!empty($workerIds)) {
        $deletedTransactions = DB::table('payroll_transactions')
            ->whereIn('payroll_worker_id', $workerIds)
            ->delete();
        echo "✓ Deleted {$deletedTransactions} transaction(s)\n";
    }

    // Delete workers
    $deletedWorkers = DB::table('payroll_workers')
        ->whereIn('payroll_submission_id', $submissionIds)
        ->delete();
    echo "✓ Deleted {$deletedWorkers} worker record(s)\n";

    // Delete payments
    $deletedPayments = DB::table('payroll_payments')
        ->whereIn('payroll_submission_id', $submissionIds)
        ->delete();
    echo "✓ Deleted {$deletedPayments} payment record(s)\n";

    // Delete submissions
    $deletedSubmissions = DB::table('payroll_submissions')
        ->where('month', 12)
        ->where('year', 2025)
        ->delete();
    echo "✓ Deleted {$deletedSubmissions} submission(s)\n";

    // Commit the transaction
    DB::commit();

    echo "\n";
    echo "✅ December 2025 payroll data has been forcefully cleared!\n";
    echo "\n";
    echo "Summary:\n";
    echo "--------\n";
    echo "Submissions: {$deletedSubmissions}\n";
    echo "Workers: {$deletedWorkers}\n";
    echo "Transactions: " . ($deletedTransactions ?? 0) . "\n";
    echo "Payments: {$deletedPayments}\n";
    echo "\n";

} catch (\Exception $e) {
    DB::rollBack();
    echo "\n";
    echo "❌ ERROR: {$e->getMessage()}\n";
    echo "\n";
    echo "Stack trace:\n";
    echo $e->getTraceAsString();
    echo "\n\n";
    exit(1);
}
