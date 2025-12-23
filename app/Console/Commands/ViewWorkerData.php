<?php

namespace App\Console\Commands;

use App\Models\Contractor;
use App\Models\Worker;
use Illuminate\Console\Command;

class ViewWorkerData extends Command
{
    protected $signature = 'db:view-workers';

    protected $description = 'View workers and contractors from second database';

    public function handle()
    {
        $this->info('📊 Viewing Second Database (worker_db)');
        $this->newLine();

        // View Contractors
        $this->info('👔 CONTRACTORS TABLE:');
        $this->line(str_repeat('=', 80));

        try {
            $totalContractors = Contractor::count();
            $this->info("Total Contractors: {$totalContractors}");
            $this->newLine();

            if ($totalContractors > 0) {
                $contractors = Contractor::limit(10)->get();
                $this->info('Showing first 10 contractors:');
                $this->table(
                    ['ID', 'Company Name', 'Reg Number', 'Contact Person', 'Phone', 'Status'],
                    $contractors->map(fn ($c) => [
                        $c->id,
                        $c->company_name,
                        $c->registration_number ?? 'N/A',
                        $c->contact_person ?? 'N/A',
                        $c->phone ?? 'N/A',
                        $c->status ?? 'N/A',
                    ])
                );
            } else {
                $this->warn('No contractors found in the database.');
            }
        } catch (\Exception $e) {
            $this->error('Error fetching contractors: '.$e->getMessage());
        }

        $this->newLine(2);

        // View Workers
        $this->info('👷 WORKERS TABLE:');
        $this->line(str_repeat('=', 80));

        try {
            $totalWorkers = Worker::count();
            $this->info("Total Workers: {$totalWorkers}");
            $this->newLine();

            if ($totalWorkers > 0) {
                $workers = Worker::limit(10)->get();
                $this->info('Showing first 10 workers:');
                $this->table(
                    ['ID', 'Name', 'IC Number', 'Position', 'Contractor ID', 'Status'],
                    $workers->map(fn ($w) => [
                        $w->id,
                        $w->name,
                        $w->ic_number ?? 'N/A',
                        $w->position ?? 'N/A',
                        $w->contractor_id ?? 'N/A',
                        $w->status ?? 'N/A',
                    ])
                );
            } else {
                $this->warn('No workers found in the database.');
            }
        } catch (\Exception $e) {
            $this->error('Error fetching workers: '.$e->getMessage());
        }

        $this->newLine();
        $this->info('✅ Done viewing second database!');
    }
}
