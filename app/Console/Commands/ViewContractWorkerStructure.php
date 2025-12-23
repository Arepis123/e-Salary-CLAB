<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ViewContractWorkerStructure extends Command
{
    protected $signature = 'db:view-contract-worker';

    protected $description = 'View contract_worker table structure and sample data';

    public function handle()
    {
        $this->info('📊 Contract Worker Table Structure');
        $this->newLine();

        try {
            // Table structure
            $this->info('TABLE STRUCTURE:');
            $this->line(str_repeat('=', 80));

            $columns = DB::connection('worker_db')->select('DESCRIBE contract_worker');

            $this->table(
                ['Field', 'Type', 'Null', 'Key', 'Default'],
                collect($columns)->map(fn ($col) => [
                    $col->Field,
                    $col->Type,
                    $col->Null,
                    $col->Key ?? '',
                    $col->Default ?? 'NULL',
                ])
            );

            $this->newLine();

            // Count
            $count = DB::connection('worker_db')->table('contract_worker')->count();
            $this->info("Total Records: {$count}");
            $this->newLine();

            // Sample data
            if ($count > 0) {
                $this->info('SAMPLE DATA (first 10 rows):');
                $this->line(str_repeat('=', 80));

                $samples = DB::connection('worker_db')->table('contract_worker')->limit(10)->get();

                $firstRow = (array) $samples->first();
                $headers = array_keys($firstRow);

                $this->table(
                    $headers,
                    $samples->map(fn ($row) => (array) $row)
                );
            } else {
                $this->warn('No records found in contract_worker table.');
            }

        } catch (\Exception $e) {
            $this->error('Error: '.$e->getMessage());
        }

        $this->newLine();
        $this->info('✅ Done!');
    }
}
