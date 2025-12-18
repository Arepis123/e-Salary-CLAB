<?php

/**
 * Check December 2025 Payroll Status
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\PayrollSubmission;

echo "\n";
echo "==========================================\n";
echo "  December 2025 Payroll Status\n";
echo "==========================================\n\n";

$submissions = PayrollSubmission::where('month', 12)
    ->where('year', 2025)
    ->with('workers')
    ->get();

if ($submissions->isEmpty()) {
    echo "✓ No December 2025 payroll data found.\n\n";
    exit(0);
}

echo "Found {$submissions->count()} December 2025 submission(s):\n\n";

foreach ($submissions as $submission) {
    echo "Submission ID: {$submission->id}\n";
    echo "CLAB: {$submission->contractor_clab_no}\n";
    echo "Status: {$submission->status}\n";
    echo "Workers: {$submission->workers->count()}\n";
    echo "Submitted At: " . ($submission->submitted_at ? $submission->submitted_at->format('Y-m-d H:i:s') : 'Not submitted') . "\n";
    echo "-------------------------------------------\n";
}

echo "\n";
