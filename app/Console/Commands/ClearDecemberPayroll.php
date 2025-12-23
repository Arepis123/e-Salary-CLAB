<?php

namespace App\Console\Commands;

use App\Models\PayrollPayment;
use App\Models\PayrollSubmission;
use App\Models\PayrollWorker;
use App\Models\PayrollWorkerTransaction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ClearDecemberPayroll extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payroll:clear-december {year=2025} {--force : Skip confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear all December payroll data for a specific year';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $year = (int) $this->argument('year');
        $month = 12; // December

        $this->info("Checking December {$year} payroll data...");

        // Find all December submissions
        $submissions = PayrollSubmission::where('month', $month)
            ->where('year', $year)
            ->get();

        if ($submissions->isEmpty()) {
            $this->info("No December {$year} payroll data found.");

            return Command::SUCCESS;
        }

        // Count related records
        $submissionIds = $submissions->pluck('id');
        $workersCount = PayrollWorker::whereIn('payroll_submission_id', $submissionIds)->count();
        $transactionsCount = PayrollWorkerTransaction::whereIn('payroll_worker_id', function ($query) use ($submissionIds) {
            $query->select('id')
                ->from('payroll_workers')
                ->whereIn('payroll_submission_id', $submissionIds);
        })->count();
        $paymentsCount = PayrollPayment::whereIn('payroll_submission_id', $submissionIds)->count();

        // Display what will be deleted
        $this->newLine();
        $this->warn('The following records will be PERMANENTLY DELETED:');
        $this->table(
            ['Type', 'Count'],
            [
                ['Payroll Submissions', $submissions->count()],
                ['Payroll Workers', $workersCount],
                ['Worker Transactions', $transactionsCount],
                ['Payroll Payments', $paymentsCount],
            ]
        );

        // Show submission details
        $this->newLine();
        $this->info('Submission Details:');
        foreach ($submissions as $submission) {
            $this->line("  - ID: {$submission->id} | Contractor: {$submission->contractor_clab_no} | Status: {$submission->status} | Workers: ".$submission->workers()->count());
        }

        // Confirm deletion
        if (! $this->option('force')) {
            if (! $this->confirm('Do you want to proceed with deleting all December payroll data?')) {
                $this->info('Operation cancelled.');

                return Command::SUCCESS;
            }
        }

        $this->newLine();
        $this->info('Deleting December payroll data...');

        try {
            DB::beginTransaction();

            // Delete in correct order (child records first)
            $deletedTransactions = 0;
            $deletedPayments = 0;
            $deletedWorkers = 0;
            $deletedSubmissions = 0;

            foreach ($submissions as $submission) {
                // Get all workers for this submission
                $workers = $submission->workers;

                foreach ($workers as $worker) {
                    // Delete worker transactions
                    $deletedTransactions += $worker->transactions()->delete();
                }

                // Delete payments for this submission
                $deletedPayments += $submission->payments()->delete();

                // Delete workers
                $deletedWorkers += $submission->workers()->delete();

                // Delete submission
                $submission->delete();
                $deletedSubmissions++;
            }

            DB::commit();

            $this->newLine();
            $this->info('December payroll data cleared successfully!');
            $this->table(
                ['Type', 'Deleted'],
                [
                    ['Payroll Submissions', $deletedSubmissions],
                    ['Payroll Workers', $deletedWorkers],
                    ['Worker Transactions', $deletedTransactions],
                    ['Payroll Payments', $deletedPayments],
                ]
            );

            return Command::SUCCESS;

        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('Failed to clear December payroll data: '.$e->getMessage());

            return Command::FAILURE;
        }
    }
}
