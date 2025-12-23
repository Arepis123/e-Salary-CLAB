<?php

namespace App\Console\Commands;

use App\Models\NotificationLog;
use App\Models\NotificationTemplate;
use App\Models\PayrollSubmission;
use App\Services\NotificationService;
use Illuminate\Console\Command;

class SendPaymentReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reminders:payment';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send automated payment reminders to contractors at 14, 7, and 3 days before due date';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $reminderDays = [14, 7, 3];
        $totalSent = 0;

        $this->info('Checking for payment reminders to send...');

        foreach ($reminderDays as $days) {
            $targetDate = now()->addDays($days)->startOfDay();

            // Find submissions due on target date
            $submissions = PayrollSubmission::whereDate('payment_deadline', $targetDate)
                ->whereNotIn('status', ['paid', 'draft'])
                ->with('user')
                ->get();

            $this->info("Found {$submissions->count()} submission(s) due in {$days} days");

            foreach ($submissions as $submission) {
                // Check if reminder already sent for this specific day
                $alreadySent = NotificationLog::where('reference_type', PayrollSubmission::class)
                    ->where('reference_id', $submission->id)
                    ->where('subject', 'LIKE', "%{$days} day%")
                    ->exists();

                if ($alreadySent) {
                    $this->line("  - Skipped INV-{$submission->id} ({$days} days reminder already sent)");

                    continue;
                }

                // Get or create template
                $template = NotificationTemplate::where('trigger_type', 'auto_payment_deadline')
                    ->where('trigger_days_before', $days)
                    ->where('is_active', true)
                    ->first();

                if (! $template) {
                    $this->warn("  - No active template found for {$days} days before deadline");

                    continue;
                }

                if (! $submission->user) {
                    $this->warn("  - Submission INV-{$submission->id} has no associated user");

                    continue;
                }

                // Send the notification
                try {
                    $notificationService = app(NotificationService::class);
                    $notificationService->sendFromTemplate(
                        $template,
                        $submission->user,
                        [
                            'client_name' => $submission->user->name,
                            'invoice_number' => 'INV-'.str_pad($submission->id, 4, '0', STR_PAD_LEFT),
                            'amount' => number_format($submission->grand_total, 2),
                            'due_date' => $submission->payment_deadline->format('M d, Y'),
                            'period' => $submission->month_year,
                            'days_remaining' => $days,
                        ],
                        null,
                        PayrollSubmission::class,
                        $submission->id
                    );

                    $totalSent++;
                    $this->info("  ✓ Sent {$days}-day reminder to {$submission->user->email} for INV-{$submission->id}");
                } catch (\Exception $e) {
                    $this->error("  ✗ Failed to send reminder for INV-{$submission->id}: {$e->getMessage()}");
                }
            }
        }

        $this->info("\nCompleted! Sent {$totalSent} reminder(s) total.");

        return Command::SUCCESS;
    }
}
