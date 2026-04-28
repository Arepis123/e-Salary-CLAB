<?php

/**
 * Payment Sync Script
 *
 * Syncs all pending Billplz payments and fixes missing receipt data on paid submissions.
 * Only runs between the 18th of the month and the 1st of the next month (payment window).
 * Safe to run frequently — it only touches records that still need updating.
 *
 * Usage (set this as a cron job on the server):
 *   0 * * * * php /path/to/e-payroll/run-sync-payments.php >> /path/to/e-payroll/storage/logs/sync-payments-cron.log 2>&1
 *
 * Or with a dry run (safe preview, no changes made):
 *   php run-sync-payments.php --dry-run
 *
 * To force-run regardless of schedule gate:
 *   php run-sync-payments.php --force
 */

define('BASE_PATH', __DIR__);
define('LOG_FILE', BASE_PATH . '/storage/logs/sync-payments.log');
define('PHP_BIN', PHP_BINARY);
define('ARTISAN', BASE_PATH . '/artisan');

// --------------------------------------------------------------------------
// Helpers
// --------------------------------------------------------------------------

function log_line(string $message): void
{
    $myt  = new DateTimeZone('Asia/Kuala_Lumpur');
    $line = '[' . (new DateTime('now', $myt))->format('Y-m-d H:i:s T') . '] ' . $message . PHP_EOL;
    file_put_contents(LOG_FILE, $line, FILE_APPEND | LOCK_EX);
    echo $line;
}

function run_artisan(string $command): int
{
    $cmd = escapeshellarg(PHP_BIN) . ' ' . escapeshellarg(ARTISAN) . ' ' . $command;

    log_line("Running: {$cmd}");

    $output     = [];
    $returnCode = 0;

    exec($cmd . ' 2>&1', $output, $returnCode);

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
// Gate: only run from the 18th through the 1st of the next month,
//       between 08:00–19:00 Malaysia Time (MYT, UTC+8)
// --------------------------------------------------------------------------

$myt  = new DateTimeZone('Asia/Kuala_Lumpur');
$now  = new DateTime('now', $myt);
$today = (int) $now->format('j');  // Day of month in MYT
$hour  = (int) $now->format('G');  // 24-hour in MYT

log_line("MYT time: " . $now->format('Y-m-d H:i:s T'));

// Active window: day >= 18 OR day == 1
$inWindow = ($today >= 18 || $today === 1);

if (! $inWindow && ! $force) {
    log_line("Skipped: MYT date is the {$today}th, outside the active window (18th–1st). Use --force to override.");
    exit(0);
}

if (($hour < 8 || $hour >= 19) && ! $force) {
    log_line("Skipped: MYT hour is {$hour}:00, outside the active hours (08:00–19:00). Use --force to override.");
    exit(0);
}

// --------------------------------------------------------------------------
// Sanity checks
// --------------------------------------------------------------------------

if (! file_exists(ARTISAN)) {
    log_line('ERROR: artisan not found at ' . ARTISAN);
    exit(1);
}

if (! is_dir(BASE_PATH . '/storage/logs')) {
    mkdir(BASE_PATH . '/storage/logs', 0755, true);
}

// --------------------------------------------------------------------------
// Run
// --------------------------------------------------------------------------

log_line('========== Payment Sync Started' . ($dryRun ? ' [DRY RUN]' : '') . ' ==========');

$artisanArgs = 'payment:sync-all-pending --fix-receipts';

if ($dryRun) {
    $artisanArgs .= ' --dry-run';
}

$code = run_artisan($artisanArgs);

log_line('Exit code: ' . $code);

$status = $code === 0 ? 'SUCCESS' : 'COMPLETED WITH ERRORS';
log_line("========== Payment Sync {$status} ==========");

exit($code);
