<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ViewDbStructure extends Command
{
    protected $signature = 'db:structure';

    protected $description = 'View structure of workers and contractors tables';

    public function handle()
    {
        $this->info('📊 Database Structure for worker_db');
        $this->newLine();

        // Contractors table structure
        $this->info('👔 CONTRACTORS TABLE STRUCTURE:');
        $this->line(str_repeat('=', 80));

        try {
            $contractorColumns = DB::connection('worker_db')->select('DESCRIBE contractors');

            $this->table(
                ['Field', 'Type', 'Null', 'Key', 'Default'],
                collect($contractorColumns)->map(fn ($col) => [
                    $col->Field,
                    $col->Type,
                    $col->Null,
                    $col->Key ?? '',
                    $col->Default ?? 'NULL',
                ])
            );

            // Sample data
            $this->newLine();
            $this->info('Sample data (first 3 rows):');
            $sampleContractors = DB::connection('worker_db')->table('contractors')->limit(3)->get();

            if ($sampleContractors->count() > 0) {
                $firstRow = (array) $sampleContractors->first();
                $headers = array_keys($firstRow);

                $this->table(
                    $headers,
                    $sampleContractors->map(fn ($row) => (array) $row)
                );
            }

        } catch (\Exception $e) {
            $this->error('Error: '.$e->getMessage());
        }

        $this->newLine(2);

        // Workers table structure
        $this->info('👷 WORKERS TABLE STRUCTURE:');
        $this->line(str_repeat('=', 80));

        try {
            $workerColumns = DB::connection('worker_db')->select('DESCRIBE workers');

            $this->table(
                ['Field', 'Type', 'Null', 'Key', 'Default'],
                collect($workerColumns)->map(fn ($col) => [
                    $col->Field,
                    $col->Type,
                    $col->Null,
                    $col->Key ?? '',
                    $col->Default ?? 'NULL',
                ])
            );

            // Sample data
            $this->newLine();
            $this->info('Sample data (first 3 rows):');
            $sampleWorkers = DB::connection('worker_db')->table('workers')->limit(3)->get();

            if ($sampleWorkers->count() > 0) {
                $firstRow = (array) $sampleWorkers->first();
                $headers = array_keys($firstRow);

                $this->table(
                    $headers,
                    $sampleWorkers->map(fn ($row) => (array) $row)
                );
            }

        } catch (\Exception $e) {
            $this->error('Error: '.$e->getMessage());
        }

        $this->newLine();
        $this->info('✅ Done!');
    }
}
