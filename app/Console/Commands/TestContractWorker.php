<?php

namespace App\Console\Commands;

use App\Models\ContractWorker;
use App\Services\ContractWorkerService;
use Illuminate\Console\Command;

class TestContractWorker extends Command
{
    protected $signature = 'test:contract-worker';

    protected $description = 'Test ContractWorker model and service';

    public function handle()
    {
        $this->info('🧪 Testing ContractWorker Model and Service');
        $this->newLine();

        try {
            // Test 1: Get all contracts
            $this->info('Test 1: Get All Contracts');
            $this->line(str_repeat('=', 80));
            $contracts = ContractWorker::all();
            $this->info("Total Contracts: {$contracts->count()}");
            $this->newLine();

            // Test 2: Get active contracts
            $this->info('Test 2: Get Active Contracts');
            $this->line(str_repeat('=', 80));
            $activeContracts = ContractWorker::active()->get();
            $this->info("Active Contracts: {$activeContracts->count()}");
            $this->newLine();

            // Test 3: Get contract with relationships
            if ($contracts->count() > 0) {
                $this->info('Test 3: Get Contract with Relationships');
                $this->line(str_repeat('=', 80));

                $contract = ContractWorker::with(['contractor', 'worker'])->first();

                $this->table(
                    ['Field', 'Value'],
                    [
                        ['Contract ID', $contract->con_id],
                        ['Contractor CLAB', $contract->con_ctr_clab_no],
                        ['Contractor Name', $contract->contractor->company_name ?? 'N/A'],
                        ['Worker ID', $contract->con_wkr_id],
                        ['Worker Name', $contract->worker->name ?? 'N/A'],
                        ['Worker Passport', $contract->con_wkr_passno],
                        ['Period (months)', $contract->con_period],
                        ['Start Date', $contract->con_start],
                        ['End Date', $contract->con_end],
                        ['Is Active?', $contract->isActive() ? 'Yes' : 'No'],
                        ['Days Remaining', $contract->daysRemaining()],
                    ]
                );
                $this->newLine();
            }

            // Test 4: Service - Get Statistics
            $this->info('Test 4: Service - Get Contract Statistics');
            $this->line(str_repeat('=', 80));

            $service = new ContractWorkerService;
            $stats = $service->getContractStatistics();

            $this->table(
                ['Metric', 'Count'],
                [
                    ['Total Contracts', $stats['total_contracts']],
                    ['Active Contracts', $stats['active_contracts']],
                    ['Expired Contracts', $stats['expired_contracts']],
                    ['Expiring Soon (30 days)', $stats['expiring_soon']],
                    ['Active Contractors', $stats['active_contractors']],
                    ['Active Workers', $stats['active_workers']],
                ]
            );
            $this->newLine();

            // Test 5: Service - Get Contractors with Active Contracts
            $this->info('Test 5: Service - Get Contractors with Active Contracts');
            $this->line(str_repeat('=', 80));

            $contractors = $service->getContractorsWithActiveContracts();
            $this->info("Contractors with Active Contracts: {$contractors->count()}");

            if ($contractors->count() > 0) {
                $this->table(
                    ['CLAB Number', 'Company Name', 'Contact Person'],
                    $contractors->map(fn ($c) => [
                        $c->ctr_clab_no,
                        $c->company_name,
                        $c->contact_person ?? 'N/A',
                    ])
                );
            }
            $this->newLine();

            // Test 6: Service - Get Contracted Workers
            if ($contractors->count() > 0) {
                $firstContractor = $contractors->first();

                $this->info("Test 6: Service - Get Contracted Workers for {$firstContractor->ctr_clab_no}");
                $this->line(str_repeat('=', 80));

                $workers = $service->getContractedWorkers($firstContractor->ctr_clab_no);
                $this->info("Contracted Workers: {$workers->count()}");

                if ($workers->count() > 0) {
                    $this->table(
                        ['Worker ID', 'Name', 'Passport', 'Contract Start', 'Contract End', 'Days Left'],
                        $workers->map(fn ($w) => [
                            $w->wkr_id,
                            $w->name,
                            $w->ic_number,
                            $w->contract_info->con_start ?? 'N/A',
                            $w->contract_info->con_end ?? 'N/A',
                            $w->contract_info ? $w->contract_info->daysRemaining() : 'N/A',
                        ])
                    );
                }
                $this->newLine();
            }

            // Test 7: Test Contractor Relationships
            if ($contracts->count() > 0) {
                $this->info('Test 7: Test Contractor Relationships');
                $this->line(str_repeat('=', 80));

                $clabNo = $contracts->first()->con_ctr_clab_no;
                $contractor = \App\Models\Contractor::find($clabNo);

                if ($contractor) {
                    $contractedWorkers = $contractor->contractedWorkers;
                    $activeContractedWorkers = $contractor->activeContractedWorkers;
                    $contractRecords = $contractor->contracts;

                    $this->table(
                        ['Relationship', 'Count'],
                        [
                            ['Contracted Workers (all)', $contractedWorkers->count()],
                            ['Active Contracted Workers', $activeContractedWorkers->count()],
                            ['Contract Records', $contractRecords->count()],
                        ]
                    );
                }
                $this->newLine();
            }

            $this->info('✅ All tests completed successfully!');

        } catch (\Exception $e) {
            $this->error('❌ Test failed: '.$e->getMessage());
            $this->error($e->getTraceAsString());
        }
    }
}
