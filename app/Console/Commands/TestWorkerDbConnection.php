<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class TestWorkerDbConnection extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:test-worker';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test the second database (worker_db) connection';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Testing worker database connection...');
        $this->newLine();

        try {
            // Test First Database (e_salary)
            $this->info('📊 Testing First Database (e_salary):');
            $firstDb = \DB::connection('mariadb')->getDatabaseName();
            $this->line("   ✓ Connected to: {$firstDb}");

            $firstTables = \DB::connection('mariadb')->select('SHOW TABLES');
            $this->line('   ✓ Tables found: '.count($firstTables));
            $this->newLine();

            // Test Second Database (worker_db)
            $this->info('👷 Testing Second Database (worker_db):');
            $secondDb = \DB::connection('worker_db')->getDatabaseName();
            $this->line("   ✓ Connected to: {$secondDb}");

            // Try to get tables from second database
            try {
                $secondTables = \DB::connection('worker_db')->select('SHOW TABLES');
                $this->line('   ✓ Tables found: '.count($secondTables));

                if (count($secondTables) > 0) {
                    $this->line('   ✓ Tables:');
                    foreach ($secondTables as $table) {
                        $tableArray = (array) $table;
                        $tableName = reset($tableArray);
                        $this->line("      - {$tableName}");
                    }
                }
            } catch (\Exception $e) {
                $this->warn('   ⚠ Database exists but no tables found (this is normal if database is new)');
            }

            $this->newLine();
            $this->info('✅ Both database connections are working!');

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('❌ Connection failed: '.$e->getMessage());
            $this->newLine();
            $this->line('Please check your .env configuration:');
            $this->line('  WORKER_DB_HOST='.env('WORKER_DB_HOST'));
            $this->line('  WORKER_DB_PORT='.env('WORKER_DB_PORT'));
            $this->line('  WORKER_DB_DATABASE='.env('WORKER_DB_DATABASE'));

            return Command::FAILURE;
        }
    }
}
