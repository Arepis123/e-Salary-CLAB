<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanupFailedLoginAttempts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'auth:cleanup-failed-attempts {--days=60 : Number of days to keep records}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete failed login attempts older than specified days (default: 60 days)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $days = (int) $this->option('days');

        $this->info("Cleaning up failed login attempts older than {$days} days...");

        // Calculate cutoff date
        $cutoffDate = now()->subDays($days);

        // Count records to be deleted
        $count = DB::table('failed_login_attempts')
            ->where('attempted_at', '<', $cutoffDate)
            ->count();

        if ($count === 0) {
            $this->info('No old records found to delete.');

            return Command::SUCCESS;
        }

        // Delete old records
        $deleted = DB::table('failed_login_attempts')
            ->where('attempted_at', '<', $cutoffDate)
            ->delete();

        $this->info("Successfully deleted {$deleted} failed login attempt records.");
        $this->comment("Cutoff date: {$cutoffDate->format('Y-m-d H:i:s')}");

        // Show statistics
        $remaining = DB::table('failed_login_attempts')->count();
        $this->info("Remaining records in database: {$remaining}");

        return Command::SUCCESS;
    }
}
