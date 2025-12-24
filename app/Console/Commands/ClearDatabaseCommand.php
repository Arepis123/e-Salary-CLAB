<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ClearDatabaseCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:clear {--force : Force the operation without confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear all database tables except admin and super_admin users';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Confirm before proceeding (unless --force is used)
        if (!$this->option('force')) {
            if (!$this->confirm('⚠️  This will delete ALL data except admin and super_admin users. Are you sure?')) {
                $this->info('Operation cancelled.');
                return 0;
            }
        }

        $this->info('Starting database cleanup...');

        // Store admin and super_admin users before clearing
        $adminUsers = DB::table('users')
            ->whereIn('role', ['admin', 'super_admin'])
            ->get()
            ->toArray();

        if (empty($adminUsers)) {
            $this->error('❌ No admin or super_admin users found! Aborting to prevent complete data loss.');
            return 1;
        }

        $this->info('✓ Found ' . count($adminUsers) . ' admin/super_admin users to preserve.');

        // Disable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        try {
            // Get all table names
            $tables = DB::select('SHOW TABLES');
            $databaseName = env('DB_DATABASE');
            $tableKey = "Tables_in_{$databaseName}";

            $excludedTables = ['migrations']; // Don't clear migrations table

            $this->newLine();
            $this->info('Clearing tables...');

            foreach ($tables as $table) {
                $tableName = $table->$tableKey;

                // Skip excluded tables
                if (in_array($tableName, $excludedTables)) {
                    $this->line("  → Skipping: {$tableName}");
                    continue;
                }

                // Special handling for users table
                if ($tableName === 'users') {
                    $deleted = DB::table($tableName)
                        ->whereNotIn('role', ['admin', 'super_admin'])
                        ->delete();
                    $this->line("  → Cleared: {$tableName} ({$deleted} rows deleted, admins preserved)");
                    continue;
                }

                // Truncate all other tables
                DB::table($tableName)->truncate();
                $this->line("  → Cleared: {$tableName}");
            }

            $this->newLine();
            $this->info('✓ Database cleared successfully!');
            $this->newLine();
            $this->info('Preserved admin users:');

            foreach ($adminUsers as $user) {
                $this->line("  → {$user->name} ({$user->email}) - Role: {$user->role}");
            }

        } catch (\Exception $e) {
            $this->error('❌ Error clearing database: ' . $e->getMessage());
            return 1;
        } finally {
            // Re-enable foreign key checks
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        }

        $this->newLine();
        $this->info('✅ Database cleanup completed! You can now start fresh.');
        return 0;
    }
}
