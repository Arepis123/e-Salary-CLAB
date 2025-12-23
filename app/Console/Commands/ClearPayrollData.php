<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ClearPayrollData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payroll:clear {--force : Force the operation without confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear all payroll submission data from the database';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if (! $this->option('force')) {
            if (! $this->confirm('This will delete ALL payroll submission data. Are you sure?')) {
                $this->info('Operation cancelled.');

                return Command::SUCCESS;
            }
        }

        $this->info('Clearing payroll data...');

        try {
            // Disable foreign key checks temporarily
            DB::statement('SET FOREIGN_KEY_CHECKS=0');

            // Truncate tables in order (child tables first, then parent)
            $tables = [
                'payroll_worker_transactions',
                'payroll_payments',
                'payroll_workers',
                'payroll_reminders',
                'payroll_submissions',
            ];

            foreach ($tables as $table) {
                DB::table($table)->truncate();
                $this->line("✓ Cleared {$table}");
            }

            // Re-enable foreign key checks
            DB::statement('SET FOREIGN_KEY_CHECKS=1');

            $this->newLine();
            $this->info('All payroll data has been cleared successfully!');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            // Make sure to re-enable foreign key checks even if there's an error
            DB::statement('SET FOREIGN_KEY_CHECKS=1');

            $this->error('Error clearing payroll data: '.$e->getMessage());

            return Command::FAILURE;
        }
    }
}
