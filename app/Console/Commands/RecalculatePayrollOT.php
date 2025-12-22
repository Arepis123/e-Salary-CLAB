<?php

namespace App\Console\Commands;

use App\Models\PayrollWorker;
use Illuminate\Console\Command;

class RecalculatePayrollOT extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payroll:recalculate-ot
                            {--submission= : Only recalculate for specific submission ID}
                            {--force : Recalculate even if OT pay already exists}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recalculate OT pay for payroll workers who have OT hours but no OT pay calculated';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting OT recalculation...');

        // Build query
        $query = PayrollWorker::whereHas('payrollSubmission', function($q) {
            $q->where('status', '!=', 'draft');
        });

        // Filter by submission if specified
        if ($submissionId = $this->option('submission')) {
            $query->where('payroll_submission_id', $submissionId);
            $this->info("Filtering by submission ID: {$submissionId}");
        }

        // Filter by workers with OT hours but no pay (unless --force)
        if (!$this->option('force')) {
            $query->where(function($q) {
                $q->where(function($q) {
                    $q->where('ot_normal_hours', '>', 0)
                      ->where('ot_normal_pay', 0);
                })
                ->orWhere(function($q) {
                    $q->where('ot_rest_hours', '>', 0)
                      ->where('ot_rest_pay', 0);
                })
                ->orWhere(function($q) {
                    $q->where('ot_public_hours', '>', 0)
                      ->where('ot_public_pay', 0);
                });
            });
        }

        $workers = $query->with('payrollSubmission')->get();

        if ($workers->isEmpty()) {
            $this->warn('No workers found that need OT recalculation.');
            return 0;
        }

        $this->info("Found {$workers->count()} workers to recalculate.");

        $bar = $this->output->createProgressBar($workers->count());
        $bar->start();

        $recalculated = 0;
        $errors = 0;

        foreach ($workers as $worker) {
            try {
                // Store old values for comparison
                $oldNormalPay = $worker->ot_normal_pay;
                $oldRestPay = $worker->ot_rest_pay;
                $oldPublicPay = $worker->ot_public_pay;
                $oldTotalPay = $worker->total_ot_pay;

                // Recalculate
                $worker->calculateSalary();
                $worker->save();

                // Log changes if verbose
                if ($this->output->isVerbose()) {
                    $this->newLine();
                    $this->line("Worker: {$worker->worker_name} ({$worker->payrollSubmission->month_year})");
                    $this->line("  Normal: {$oldNormalPay} -> {$worker->ot_normal_pay}");
                    $this->line("  Rest: {$oldRestPay} -> {$worker->ot_rest_pay}");
                    $this->line("  Public: {$oldPublicPay} -> {$worker->ot_public_pay}");
                    $this->line("  Total: {$oldTotalPay} -> {$worker->total_ot_pay}");
                }

                $recalculated++;
            } catch (\Exception $e) {
                $this->error("Error recalculating worker ID {$worker->id}: {$e->getMessage()}");
                $errors++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("Recalculation complete!");
        $this->table(
            ['Status', 'Count'],
            [
                ['Recalculated', $recalculated],
                ['Errors', $errors],
                ['Total', $workers->count()],
            ]
        );

        return 0;
    }
}
