<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\PayrollSubmission;

class ApplyOverduePenalties extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'penalties:apply-overdue';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Apply 8% late penalty to all overdue payroll submissions';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking for overdue payroll submissions...');

        // Get all overdue submissions that don't have penalty applied yet
        $overdueSubmissions = PayrollSubmission::overdue()
            ->where('has_penalty', false)
            ->get();

        if ($overdueSubmissions->isEmpty()) {
            $this->info('No overdue submissions found.');
            return 0;
        }

        $this->info("Found {$overdueSubmissions->count()} overdue submission(s).");

        $penaltyAppliedCount = 0;

        foreach ($overdueSubmissions as $submission) {
            $this->line("Processing submission #{$submission->id} - {$submission->month_year}");
            $this->line("  Contractor: {$submission->contractor_clab_no}");
            $this->line("  Deadline: {$submission->payment_deadline->format('Y-m-d')}");
            $this->line("  Days overdue: " . now()->diffInDays($submission->payment_deadline));

            // Apply penalty
            $submission->updatePenalty();
            $submission->refresh();

            if ($submission->has_penalty) {
                $this->info("  ✓ Penalty applied: RM " . number_format($submission->penalty_amount, 2));
                $this->info("  New total: RM " . number_format($submission->total_with_penalty, 2));
                $penaltyAppliedCount++;
            } else {
                $this->warn("  ✗ Failed to apply penalty");
            }

            $this->newLine();
        }

        $this->info("Completed! Penalties applied to {$penaltyAppliedCount} submission(s).");

        return 0;
    }
}
