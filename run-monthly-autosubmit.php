<?php

/**
 * Monthly Auto-Submit Script
 *
 * Runs on the 16th of every month to:
 *   1. Auto-submit OT entries for contractors who haven't submitted (payroll:auto-submit-ot)
 *   2. Auto-submit timesheets for contractors who haven't submitted  (payroll:auto-submit)
 *
 * Usage (set this as a cron job on the server):
 *   0 0 16 * * php /path/to/e-payroll/run-monthly-autosubmit.php >> /path/to/e-payroll/storage/logs/autosubmit-cron.log 2>&1
 *
 * Or with a dry run (safe preview, no changes made):
 *   php run-monthly-autosubmit.php --dry-run
 *
 * To force-run regardless of the date:
 *   php run-monthly-autosubmit.php --force
 */

define('BASE_PATH', __DIR__);
define('LOG_FILE', BASE_PATH . '/storage/logs/autosubmit.log');
define('PHP_BIN', PHP_BINARY);          // Use the same PHP that is running this script
define('ARTISAN', BASE_PATH . '/artisan');

// --------------------------------------------------------------------------
// Helpers
// --------------------------------------------------------------------------

function log_line(string $message): void
{
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
    file_put_contents(LOG_FILE, $line, FILE_APPEND | LOCK_EX);
    echo $line;
}

function run_artisan(string $command, bool $dryRun = false): int
{
    $artisanCmd = escapeshellarg(PHP_BIN) . ' ' . escapeshellarg(ARTISAN) . ' ' . $command;

    if ($dryRun) {
        $artisanCmd .= ' --dry-run';
    }

    log_line("Running: {$artisanCmd}");

    $output     = [];
    $returnCode = 0;

    exec($artisanCmd . ' 2>&1', $output, $returnCode);

    foreach ($output as $line) {
        log_line('  ' . $line);
    }

    return $returnCode;
}

// --------------------------------------------------------------------------
// Parse arguments
// --------------------------------------------------------------------------

$args   = array_slice($argv ?? [], 1);
$force  = in_array('--force', $args, true);
$dryRun = in_array('--dry-run', $args, true);

// --------------------------------------------------------------------------
// Gate: only run on the 16th (unless --force is passed)
// --------------------------------------------------------------------------

$today = (int) date('j');   // Day of month without leading zero

if ($today !== 16 && !$force) {
    log_line("Skipped: today is the {$today}th, not the 16th. Use --force to override.");
    exit(0);
}

// --------------------------------------------------------------------------
// Sanity checks
// --------------------------------------------------------------------------

if (!file_exists(ARTISAN)) {
    log_line('ERROR: artisan not found at ' . ARTISAN);
    exit(1);
}

if (!is_dir(BASE_PATH . '/storage/logs')) {
    mkdir(BASE_PATH . '/storage/logs', 0755, true);
}

// --------------------------------------------------------------------------
// Run
// --------------------------------------------------------------------------

log_line('========== Monthly Auto-Submit Started' . ($dryRun ? ' [DRY RUN]' : '') . ' ==========');

// Step 1 – Auto-submit OT entries (previous month)
log_line('--- Step 1: payroll:auto-submit-ot ---');
$code1 = run_artisan('payroll:auto-submit-ot', $dryRun);
log_line('Exit code: ' . $code1);

if ($code1 !== 0) {
    log_line('WARNING: payroll:auto-submit-ot returned a non-zero exit code. Continuing to step 2.');
}

// Brief pause so that any DB writes from step 1 are fully committed
sleep(5);

// Step 2 – Auto-submit timesheets (current month, uses OT data from step 1)
log_line('--- Step 2: payroll:auto-submit ---');
$code2 = run_artisan('payroll:auto-submit', $dryRun);
log_line('Exit code: ' . $code2);

// --------------------------------------------------------------------------
// Summary
// --------------------------------------------------------------------------

$overallStatus = ($code1 === 0 && $code2 === 0) ? 'SUCCESS' : 'COMPLETED WITH ERRORS';
log_line("========== Monthly Auto-Submit {$overallStatus} ==========");

exit(($code1 !== 0 || $code2 !== 0) ? 1 : 0);
