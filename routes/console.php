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
