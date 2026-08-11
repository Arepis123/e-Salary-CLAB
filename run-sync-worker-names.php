<?php

/**
 * Worker Name Sync Script
 *
 * Refreshes the worker_name / worker_passport snapshots this system copied out
 * of worker_db at submission time. Renames made in the other system write
 * straight to the database, so nothing here invalidates those copies on its own.
 *
 * Default scope is the current period plus the inactive worker registry, which
 * leaves closed submissions and adjustment history untouched. Safe to run daily;
 * it only writes rows that actually drifted, and does nothing when they all match.
 *
 * Usage (set this as a cron job on the server):
 *   0 19 * * * php /path/to/e-payroll/run-sync-worker-names.php >> /path/to/e-payroll/storage/logs/sync-worker-names-cron.log 2>&1
 *
 * The server runs on UTC, so 19:00 UTC is 03:00 MYT the next day. The exact hour
 * is not important — the job is idempotent and cheap when nothing has drifted.
 *
 * Or with a dry run (safe preview, no changes made):
 *   php run-sync-worker-names.php --dry-run
 *
 * Other options are passed straight through to the artisan command:
 *   php run-sync-worker-names.php --all
 *   php run-sync-worker-names.php --worker=141141
 *   php run-sync-worker-names.php --month=7 --year=2026
 */
define('BASE_PATH', __DIR__);
define('LOG_FILE', BASE_PATH.'/storage/logs/sync-worker-names.log');
define('PHP_BIN', PHP_BINARY);
define('ARTISAN', BASE_PATH.'/artisan');

// --------------------------------------------------------------------------
// Helpers
// --------------------------------------------------------------------------

function log_line(string $message): void
{
    $myt = new DateTimeZone('Asia/Kuala_Lumpur');
    $line = '['.(new DateTime('now', $myt))->format('Y-m-d H:i:s T').'] '.$message.PHP_EOL;
    file_put_contents(LOG_FILE, $line, FILE_APPEND | LOCK_EX);
    echo $line;
}

function run_artisan(string $command): int
{
    $cmd = escapeshellarg(PHP_BIN).' '.escapeshellarg(ARTISAN).' '.$command;

    log_line("Running: {$cmd}");

    $output = [];
    $returnCode = 0;

    exec($cmd.' 2>&1', $output, $returnCode);

    foreach ($output as $line) {
        log_line('  '.$line);
    }

    return $returnCode;
}

// --------------------------------------------------------------------------
// Parse arguments
//
// No calendar gate here: unlike the payroll runners this is idempotent and
// cheap on a no-op, so it is safe on whatever cadence the cron is set to.
// --------------------------------------------------------------------------

$args = array_slice($argv ?? [], 1);

$allowed = ['--dry-run', '--all'];
$passThrough = [];

foreach ($args as $arg) {
    if (in_array($arg, $allowed, true)) {
        $passThrough[] = $arg;

        continue;
    }

    // Value options: --worker=141141, --month=7, --year=2026
    if (preg_match('/^--(worker|month|year)=(.+)$/', $arg, $matches)) {
        $passThrough[] = '--'.$matches[1].'='.escapeshellarg($matches[2]);

        continue;
    }

    log_line("ERROR: unrecognised argument '{$arg}'.");
    log_line('Supported: --dry-run, --all, --worker=ID, --month=N, --year=YYYY');
    exit(1);
}

$dryRun = in_array('--dry-run', $args, true);

// --------------------------------------------------------------------------
// Sanity checks
// --------------------------------------------------------------------------

if (! file_exists(ARTISAN)) {
    log_line('ERROR: artisan not found at '.ARTISAN);
    exit(1);
}

if (! is_dir(BASE_PATH.'/storage/logs')) {
    mkdir(BASE_PATH.'/storage/logs', 0755, true);
}

// --------------------------------------------------------------------------
// Run
// --------------------------------------------------------------------------

log_line('========== Worker Name Sync Started'.($dryRun ? ' [DRY RUN]' : '').' ==========');

// --no-interaction is always sent: the artisan command asks for confirmation
// before writing when it detects a TTY, which would hang the cron job.
// --no-ansi keeps escape codes out of the log file.
$artisanArgs = trim('workers:sync-names --no-interaction --no-ansi '.implode(' ', $passThrough));

$code = run_artisan($artisanArgs);

log_line('Exit code: '.$code);

$status = $code === 0 ? 'SUCCESS' : 'COMPLETED WITH ERRORS';
log_line("========== Worker Name Sync {$status} ==========");

exit($code);
