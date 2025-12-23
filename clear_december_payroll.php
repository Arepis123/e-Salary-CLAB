<?php

/**
 * Clear December 2025 Payroll Data
 *
 * This script will delete all payroll submissions for December 2025
 * for all contractors, including related workers, transactions, and payments.
 *
 * Run this script using: php clear_december_payroll.php
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\PayrollSubmission;

echo "\n";
echo "==========================================\n";
echo "  Clear December 2025 Payroll Data\n";
echo "==========================================\n\n";

try {
    // Find all December 2025 submissions
    $submissions = PayrollSubmission::where('month', 12)
        ->where('year', 2025)
        ->with(['workers.transactions', 'payments'])
        ->get();

    if ($submissions->isEmpty()) {
        echo "✓ No December 2025 payroll data found. Nothing to delete.\n\n";
        exit(0);
    }

    echo "Found {$submissions->count()} December 2025 submission(s):\n";
    echo "-------------------------------------------\n";

    $totalWorkers = 0;
    $totalTransactions = 0;
    $totalPayments = 0;

    foreach ($submissions as $submission) {
        $workerCount = $submission->workers->count();
        $transactionCount = $submission->workers->sum(function ($worker) {
            return $worker->transactions->count();
        });
        $paymentCount = $submission->payments->count();

        $totalWorkers += $workerCount;
        $totalTransactions += $transactionCount;
        $totalPayments += $paymentCount;

        echo "- Submission #{$submission->id}: {$submission->month_year}\n";
        echo "  CLAB: {$submission->contractor_clab_no}\n";
        echo "  Status: {$submission->status}\n";
        echo "  Workers: {$workerCount}\n";
        echo "  Transactions: {$transactionCount}\n";
        echo "  Payments: {$paymentCount}\n";
        echo "\n";
    }

    echo "-------------------------------------------\n";
    echo "TOTAL TO DELETE:\n";
    echo "- Submissions: {$submissions->count()}\n";
    echo "- Workers: {$totalWorkers}\n";
    echo "- Transactions: {$totalTransactions}\n";
    echo "- Payments: {$totalPayments}\n";
    echo "-------------------------------------------\n\n";

    // Confirmation prompt
    echo "⚠️  WARNING: This action cannot be undone!\n";
    echo 'Are you sure you want to delete all December 2025 payroll data? (yes/no): ';

    $handle = fopen('php://stdin', 'r');
    $line = trim(fgets($handle));
    fclose($handle);

    if (strtolower($line) !== 'yes') {
        echo "\n❌ Operation cancelled. No data was deleted.\n\n";
        exit(0);
    }

    echo "\n🗑️  Deleting data...\n\n";

    // Delete in the correct order to avoid foreign key issues
    $deletedSubmissions = 0;
    $deletedWorkers = 0;
    $deletedTransactions = 0;
    $deletedPayments = 0;

    foreach ($submissions as $submission) {
        // Delete transactions first
        foreach ($submission->workers as $worker) {
            $count = $worker->transactions()->count();
            $worker->transactions()->delete();
            $deletedTransactions += $count;
        }

        // Delete workers
        $count = $submission->workers()->count();
        $submission->workers()->delete();
        $deletedWorkers += $count;

        // Delete payments
        $count = $submission->payments()->count();
        $submission->payments()->delete();
        $deletedPayments += $count;

        // Delete submission
        $submission->delete();
        $deletedSubmissions++;
    }

    echo "✓ Successfully deleted:\n";
    echo "  - {$deletedSubmissions} submission(s)\n";
    echo "  - {$deletedWorkers} worker record(s)\n";
    echo "  - {$deletedTransactions} transaction(s)\n";
    echo "  - {$deletedPayments} payment record(s)\n";
    echo "\n";
    echo "✅ December 2025 payroll data has been cleared!\n\n";

} catch (\Exception $e) {
    echo "\n";
    echo "❌ ERROR: {$e->getMessage()}\n";
    echo "\n";
    echo "Stack trace:\n";
    echo $e->getTraceAsString();
    echo "\n\n";
    exit(1);
}
