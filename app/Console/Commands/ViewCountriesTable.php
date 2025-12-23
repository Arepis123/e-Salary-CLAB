<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ViewCountriesTable extends Command
{
    protected $signature = 'db:view-countries';

    protected $description = 'View mst_countries table structure and sample data';

    public function handle()
    {
        $this->info('📊 MST_COUNTRIES Table Structure');
        $this->newLine();

        try {
            // Table structure
            $columns = DB::connection('worker_db')->select('DESCRIBE mst_countries');

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

            // Sample data
            $countries = DB::connection('worker_db')->table('mst_countries')->limit(10)->get();
            $this->info('Sample Countries (first 10):');

            if ($countries->count() > 0) {
                $firstRow = (array) $countries->first();
                $headers = array_keys($firstRow);

                $this->table(
                    $headers,
                    $countries->map(fn ($row) => (array) $row)
                );
            }

        } catch (\Exception $e) {
            $this->error('Error: '.$e->getMessage());
        }

        $this->newLine();
        $this->info('✅ Done!');
    }
}
