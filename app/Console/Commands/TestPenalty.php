<?php

namespace App\Console\Commands;

use App\Models\PayrollSubmission;
use Illuminate\Console\Command;

class TestPenalty extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:penalty {submission_id?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test penalty system by setting a submission deadline to the past';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $submissionId = $this->argument('submission_id');

        if ($submissionId) {
            // Test specific submission
            $submission = PayrollSubmission::find($submissionId);

            if (! $submission) {
                $this->error("Submission #{$submissionId} not found!");

                return 1;
            }

            $this->testSubmission($submission);
        } else {
            // List all pending submissions
            $submissions = PayrollSubmission::whereIn('status', ['draft', 'pending_payment'])
                ->orderBy('created_at', 'desc')
                ->get();

            if ($submissions->isEmpty()) {
                $this->error('No pending submissions found!');

                return 1;
            }

            $this->info("Found {$submissions->count()} pending submission(s):");
            $this->newLine();

            foreach ($submissions as $submission) {
                $this->line("ID: {$submission->id} | {$submission->month_year} | Status: {$submission->status} | Amount: RM ".number_format($submission->total_amount, 2));
            }

            $this->newLine();
            $selectedId = $this->ask('Enter submission ID to test penalty');

            $selected = PayrollSubmission::find($selectedId);

            if (! $selected) {
                $this->error("Submission #{$selectedId} not found!");

                return 1;
            }

            $this->testSubmission($selected);
        }

        return 0;
    }

    private function testSubmission(PayrollSubmission $submission)
    {
        $this->info("Testing penalty for Submission #{$submission->id}");
        $this->line("Period: {$submission->month_year}");
        $this->line("Status: {$submission->status}");
        $this->line('Total Amount: RM '.number_format($submission->total_amount, 2));
        $this->line("Current Deadline: {$submission->payment_deadline->format('Y-m-d')}");

        $this->newLine();

        if ($submission->status === 'paid') {
            $this->error('This submission is already paid! Cannot apply penalty.');

            return;
        }

        // Set deadline to 5 days ago
        $pastDate = now()->subDays(5);

        $this->warn("Setting deadline to: {$pastDate->format('Y-m-d')} (5 days ago)");

        $submission->update([
            'payment_deadline' => $pastDate,
            'status' => 'overdue',
        ]);

        $this->info('Calculating penalty...');
        $submission->updatePenalty();
        $submission->refresh();

        $this->newLine();
        $this->info('✓ Penalty Applied!');
        $this->line('Penalty Amount (8%): RM '.number_format($submission->penalty_amount, 2));
        $this->line('Total with Penalty: RM '.number_format($submission->total_with_penalty, 2));
        $this->line("Status: {$submission->status}");

        $this->newLine();
        $this->comment('You can now view this submission in the dashboard/invoices to see the penalty.');
    }
}
