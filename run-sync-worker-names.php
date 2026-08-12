<?php

/**
 * Worker Name Sync Script
 *
 * Refreshes the worker_name / worker_passport snapshots this system copied out
 * of worker_db at submission time. Renames made in the other system write
 * straight to the database, so nothing here invalidates those copies on its own.
 *
 * Default scope is a rolling 12-month window ending at the current month, plus
 * the inactive worker registry. The window matters: OT for month M is entered
 * during days 1-15 of M+1, so the period being worked on is the previous one,
 * and a single-month scope would look at an empty period for half the cycle.
 * Anything older than the window, and adjustment history, is left untouched.
 * Safe to run daily; it only writes rows that actually drifted.
 *
 * This boots the framework in-process and calls the console kernel directly
 * rather than shelling out to artisan. The production host (Plesk) has exec()
 * in disable_functions, so any subprocess approach fails there with
 * "Call to undefined function exec()".
 *
 * Usage (set this as a cron job on the server):
 *   0 3 * * * /opt/plesk/php/8.2/bin/php /path/to/e-payroll/run-sync-worker-names.php >> /path/to/e-payroll/storage/logs/sync-worker-names-cron.log 2>&1
 *
 * Cron fires on the server's clock, which is not necessarily MYT. Check with
 * `date` on the server and adjust the hour if needed; the exact time does not
 * matter much, as the job is idempotent and cheap when nothing has drifted.
 *
 * Or with a dry run (safe preview, no changes made):
 *   php run-sync-worker-names.php --dry-run
 *
 * Other options are passed straight through to the artisan command:
 *   php run-sync-worker-names.php --all
 *   php run-sync-worker-names.php --worker=141141
 *   php run-sync-worker-names.php --months=6
 *   php run-sync-worker-names.php --month=7 --year=2026
 */
define('BASE_PATH', __DIR__);
define('LOG_FILE', BASE_PATH.'/storage/logs/sync-worker-names.log');

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

// --------------------------------------------------------------------------
// Parse arguments
//
// No calendar gate here: unlike the payroll runners this is idempotent and
// cheap on a no-op, so it is safe on whatever cadence the cron is set to.
// --------------------------------------------------------------------------

$args = array_slice($argv ?? [], 1);

// Always non-interactive: the command asks for confirmation before writing when
// it detects a TTY, which would hang the cron job.
$options = ['--no-interaction' => true];

foreach ($args as $arg) {
    if ($arg === '--dry-run' || $arg === '--all') {
        $options[$arg] = true;

        continue;
    }

    // Value options: --worker=141141, --months=6, --month=7, --year=2026
    if (preg_match('/^--(worker|months|month|year)=(.+)$/', $arg, $matches)) {
        $options['--'.$matches[1]] = $matches[2];

        continue;
    }

    log_line("ERROR: unrecognised argument '{$arg}'.");
    log_line('Supported: --dry-run, --all, --worker=ID, --months=N, --month=N, --year=YYYY');
    exit(1);
}

$dryRun = in_array('--dry-run', $args, true);

// --------------------------------------------------------------------------
// Sanity checks
// --------------------------------------------------------------------------

if (! is_dir(BASE_PATH.'/storage/logs')) {
    mkdir(BASE_PATH.'/storage/logs', 0755, true);
}

foreach ([BASE_PATH.'/vendor/autoload.php', BASE_PATH.'/bootstrap/app.php'] as $required) {
    if (! file_exists($required)) {
        log_line('ERROR: not found at '.$required.' — is this script in the project root?');
        exit(1);
    }
}

// --------------------------------------------------------------------------
// Run
// --------------------------------------------------------------------------

log_line('========== Worker Name Sync Started'.($dryRun ? ' [DRY RUN]' : '').' ==========');

require BASE_PATH.'/vendor/autoload.php';

/** @var Illuminate\Foundation\Application $app */
$app = require BASE_PATH.'/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

log_line('Running: artisan workers:sync-names '.json_encode($options));

try {
    $code = $kernel->call('workers:sync-names', $options);
    $output = trim((string) $kernel->output());
} catch (Throwable $e) {
    log_line('ERROR: '.get_class($e).': '.$e->getMessage());
    log_line('  at '.$e->getFile().':'.$e->getLine());
    log_line('========== Worker Name Sync FAILED ==========');
    exit(1);
}

foreach (explode(PHP_EOL, $output) as $line) {
    log_line('  '.$line);
}

log_line('Exit code: '.$code);

$status = $code === 0 ? 'SUCCESS' : 'COMPLETED WITH ERRORS';
log_line("========== Worker Name Sync {$status} ==========");

exit($code);
