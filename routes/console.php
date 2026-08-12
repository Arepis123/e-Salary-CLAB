<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule automatic cleanup of failed login attempts
// Runs daily at 2:00 AM to delete records older than 60 days
Schedule::command('auth:cleanup-failed-attempts')->dailyAt('02:00');

// Schedule automatic payment reminders
// Runs daily at 9:00 AM to send reminders 14, 7, and 3 days before due date
Schedule::command('reminders:payment')->dailyAt('09:00');

// Schedule automatic penalty application for overdue submissions (BACKUP CHECK)
// Runs daily at 12:01 AM as a safety check for any missed penalties
// Note: Penalties are now applied immediately when client submits late
Schedule::command('penalties:apply-overdue')->dailyAt('00:01');

// Refresh worker name/passport snapshots from worker_db (the external system)
// Runs daily at 3:00 AM MYT. Renames made in the other system write straight to
// the database, so nothing in this app invalidates the copies it took at
// submission time; this closes that gap without rewriting closed periods.
//
// NOTE: the scheduler does not run on the production server, so in prod this is
// driven by a server cron calling run-sync-worker-names.php directly.
Schedule::command('workers:sync-names --no-interaction')
    ->dailyAt('03:00')
    ->timezone('Asia/Kuala_Lumpur');

// Auto-submit OT entries on the 16th of every month at 12:01 AM MYT.
// Times below are MYT: the scheduler resolves them against config('app.timezone')
// (Asia/Kuala_Lumpur), NOT the server clock, so no UTC offset is applied here.
Schedule::command('payroll:auto-submit-ot')
    ->monthlyOn(16, '00:01')
    ->timezone('Asia/Kuala_Lumpur');

// Auto-submit timesheets on the 16th of every month at 12:03 AM MYT.
// Runs after auto-submit-ot so OT data is already submitted when payroll is built
Schedule::command('payroll:auto-submit')
    ->monthlyOn(16, '00:03')
    ->timezone('Asia/Kuala_Lumpur');
