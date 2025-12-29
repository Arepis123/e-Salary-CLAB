<?php

namespace App\Console\Commands;

use App\Models\PayrollPayment;
use App\Services\BillplzService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncPaymentStatus extends Command
{
    protected $signature = 'payment:sync-status {billId : The Billplz bill ID to sync}';

    protected $description = 'Sync payment status from Billplz API (use when webhook failed)';

    protected BillplzService $billplzService;

    public function __construct(BillplzService $billplzService)
    {
        parent::__construct();
        $this->billplzService = $billplzService;
    }

    public function handle(): int
    {
        $billId = $this->argument('billId');

        $this->info("Syncing payment status for bill: {$billId}");
        $this->newLine();

        // Find payment in database
        $payment = PayrollPayment::where('billplz_bill_id', $billId)->first();

        if (!$payment) {
            $this->error("Payment not found in database with bill ID: {$billId}");
            return self::FAILURE;
        }

        $this->info("Payment found:");
        $this->line("  ID: {$payment->id}");
        $this->line("  Current Status: {$payment->status}");
        $this->line("  Amount: RM " . number_format($payment->amount, 2));
        $this->line("  Created: {$payment->created_at}");
        $this->newLine();

        // Check if already completed
        if ($payment->status === 'completed') {
            $this->warn("Payment is already marked as completed!");
            $this->line("  Completed At: {$payment->completed_at}");
            return self::SUCCESS;
        }

        // Fetch bill status from Billplz
        $this->info("Fetching bill status from Billplz API...");
        $bill = $this->billplzService->getBill($billId);

        if (!$bill) {
            $this->error("Failed to retrieve bill from Billplz API!");
            $this->error("Please check:");
            $this->line("  - API credentials are correct");
            $this->line("  - Bill ID is valid");
            $this->line("  - Network connection");
            return self::FAILURE;
        }

        $this->newLine();
        $this->info("Billplz API Response:");
        $this->line("  Bill ID: {$bill['id']}");
        $this->line("  Paid: " . ($bill['paid'] ? 'YES' : 'NO'));
        $this->line("  State: {$bill['state']}");
        $this->line("  Amount: RM " . number_format($bill['amount'] / 100, 2));

        if (isset($bill['paid_at'])) {
            $this->line("  Paid At: {$bill['paid_at']}");
        }

        $this->newLine();

        // Check if bill is paid
        // Billplz returns state='paid' for successful payments, state='due' for pending
        if (!$bill['paid']) {
            $this->warn("Bill is NOT paid according to Billplz!");
            $this->line("  Paid: false");
            $this->line("  State: {$bill['state']}");
            $this->newLine();

            if (!$this->confirm('Payment is not successful on Billplz. Mark as failed?', false)) {
                $this->info("No changes made.");
                return self::SUCCESS;
            }

            $payment->update([
                'status' => 'failed',
                'payment_response' => json_encode($bill),
            ]);

            $this->warn("Payment marked as failed.");
            return self::SUCCESS;
        }

        // Payment is successful, confirm before updating
        $this->newLine();
        $this->warn("⚠ You are about to update LIVE payment data!");
        $this->line("This will:");
        $this->line("  1. Mark payment #{$payment->id} as COMPLETED");
        $this->line("  2. Update submission status to PAID");
        $this->line("  3. Set paid_at timestamp");
        $this->newLine();

        if (!$this->confirm('Proceed with updating payment status?', false)) {
            $this->info("Operation cancelled. No changes made.");
            return self::SUCCESS;
        }

        // Update payment status in a transaction
        try {
            DB::beginTransaction();

            $payment->update([
                'status' => 'completed',
                'completed_at' => $bill['paid_at'] ?? now(),
                'payment_response' => json_encode($bill),
                'transaction_id' => $bill['id'],
            ]);

            // Update submission status
            $submission = $payment->payrollSubmission;
            $submission->update([
                'status' => 'paid',
                'paid_at' => $bill['paid_at'] ?? now(),
            ]);

            DB::commit();

            $this->newLine();
            $this->info("✓ Payment status synced successfully!");
            $this->line("  Payment ID: {$payment->id}");
            $this->line("  Status: completed");
            $this->line("  Submission: {$submission->unique_id} → paid");
            $this->newLine();

            Log::info("Payment status manually synced from Billplz", [
                'payment_id' => $payment->id,
                'bill_id' => $billId,
                'submission_id' => $submission->id,
                'synced_by' => 'Artisan Command',
                'billplz_response' => $bill,
            ]);

            return self::SUCCESS;
        } catch (\Exception $e) {
            DB::rollBack();

            $this->error("Failed to update payment status!");
            $this->error("Error: {$e->getMessage()}");

            Log::error("Failed to sync payment status", [
                'payment_id' => $payment->id,
                'bill_id' => $billId,
                'error' => $e->getMessage(),
            ]);

            return self::FAILURE;
        }
    }
}
