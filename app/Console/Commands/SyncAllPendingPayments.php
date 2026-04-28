<?php

namespace App\Console\Commands;

use App\Models\PayrollPayment;
use App\Models\PayrollSubmission;
use App\Services\BillplzService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncAllPendingPayments extends Command
{
    protected $signature = 'payment:sync-all-pending
                            {--fix-receipts : Also fix missing receipt numbers, paid dates, and transaction IDs on paid submissions}
                            {--dry-run : Preview what would be updated without making changes}';

    protected $description = 'Sync all pending Billplz payments and optionally fix missing receipt data on paid submissions';

    public function __construct(protected BillplzService $billplzService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun     = $this->option('dry-run');
        $fixReceipts = $this->option('fix-receipts');

        $this->info('========== Sync All Pending Payments' . ($dryRun ? ' [DRY RUN]' : '') . ' ==========');
        $this->newLine();

        $exitCode = $this->syncPendingPayments($dryRun);

        if ($fixReceipts) {
            $this->newLine();
            $receiptCode = $this->fixMissingReceipts($dryRun);
            $exitCode = max($exitCode, $receiptCode);
        }

        $this->newLine();
        $this->info('========== Done ==========');

        return $exitCode;
    }

    private function syncPendingPayments(bool $dryRun): int
    {
        $this->info('--- Step 1: Sync pending payments from Billplz ---');

        $cutoff = now()->subDays(30);

        $pendingPayments = PayrollPayment::where('status', 'pending')
            ->whereNotNull('billplz_bill_id')
            ->with('payrollSubmission')
            ->get();

        if ($pendingPayments->isEmpty()) {
            $this->line('No pending payments found.');
            return self::SUCCESS;
        }

        $total       = $pendingPayments->count();
        $updated     = 0;
        $cancelled   = 0;
        $stillPending = 0;
        $failed      = 0;

        $this->line("Found {$total} pending payment(s).");
        $this->newLine();

        foreach ($pendingPayments as $payment) {
            $this->line("  Payment #{$payment->id} | Bill: {$payment->billplz_bill_id}");

            try {
                // Cancel bills older than 30 days
                if ($payment->created_at->lt($cutoff)) {
                    $this->warn("    → Expired (older than 30 days). " . ($dryRun ? '[DRY RUN: would cancel]' : 'Cancelling...'));

                    if (! $dryRun) {
                        $this->billplzService->deleteBill($payment->billplz_bill_id);
                        $payment->update(['status' => 'cancelled']);

                        Log::info('Expired Billplz bill cancelled during scheduled sync', [
                            'payment_id'  => $payment->id,
                            'bill_id'     => $payment->billplz_bill_id,
                            'created_at'  => $payment->created_at,
                            'synced_by'   => 'Scheduled Command',
                        ]);
                    }

                    $cancelled++;
                    continue;
                }

                // Fetch bill status from Billplz
                $bill = $this->billplzService->getBill($payment->billplz_bill_id);

                if (! $bill) {
                    $this->error("    → Could not retrieve bill from Billplz API.");
                    $failed++;
                    continue;
                }

                if ($bill['paid']) {
                    $paidAt = $bill['paid_at'] ?? now();

                    $this->info("    → Paid! " . ($dryRun ? '[DRY RUN: would mark completed]' : 'Marking completed...'));

                    if (! $dryRun) {
                        DB::beginTransaction();

                        $payment->update([
                            'status'           => 'completed',
                            'completed_at'     => $paidAt,
                            'payment_response' => json_encode($bill),
                            'transaction_id'   => $bill['id'] ?? $payment->billplz_bill_id,
                        ]);

                        $submission = $payment->payrollSubmission;

                        if (! $submission) {
                            throw new \Exception("Submission not found for payment {$payment->id}");
                        }

                        $submission->update([
                            'status'  => 'paid',
                            'paid_at' => $paidAt,
                        ]);

                        if (! $submission->hasTaxInvoice()) {
                            $submission->generateTaxInvoiceNumber();
                        }

                        DB::commit();

                        Log::info('Payment auto-synced via scheduled command', [
                            'payment_id'    => $payment->id,
                            'bill_id'       => $payment->billplz_bill_id,
                            'submission_id' => $submission->id,
                            'synced_by'     => 'Scheduled Command',
                        ]);
                    }

                    $updated++;
                } else {
                    $this->line("    → Still pending on Billplz.");
                    $stillPending++;
                }
            } catch (\Exception $e) {
                if (DB::transactionLevel() > 0) {
                    DB::rollBack();
                }

                $this->error("    → Error: {$e->getMessage()}");
                $failed++;

                Log::error('Failed to sync payment during scheduled sync', [
                    'payment_id' => $payment->id,
                    'bill_id'    => $payment->billplz_bill_id,
                    'error'      => $e->getMessage(),
                ]);
            }
        }

        $this->newLine();
        $this->line("Summary: {$updated} updated, {$stillPending} still pending, {$cancelled} cancelled (expired), {$failed} failed");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function fixMissingReceipts(bool $dryRun): int
    {
        $this->info('--- Step 2: Fix missing receipt data on paid submissions ---');

        $submissions = PayrollSubmission::where('status', 'paid')
            ->with(['payments' => fn ($q) => $q->where('status', 'completed')])
            ->get();

        if ($submissions->isEmpty()) {
            $this->line('No paid submissions found.');
            return self::SUCCESS;
        }

        $fixedReceipts     = 0;
        $fixedDates        = 0;
        $fixedTransactions = 0;

        foreach ($submissions as $submission) {
            $changes           = [];
            $completedPayment  = $submission->payments->first();

            if (! $submission->tax_invoice_number) {
                $this->line("  Submission #{$submission->id}: missing receipt number. " . ($dryRun ? '[DRY RUN: would generate]' : 'Generating...'));
                if (! $dryRun) {
                    $submission->generateTaxInvoiceNumber();
                }
                $fixedReceipts++;
                $changes[] = 'tax_invoice_number';
            }

            if (! $submission->paid_at && $completedPayment) {
                $paidAt = $completedPayment->completed_at;

                if (! $paidAt && $completedPayment->payment_response) {
                    $response = is_array($completedPayment->payment_response)
                        ? $completedPayment->payment_response
                        : json_decode($completedPayment->payment_response, true);
                    $paidAt = $response['paid_at'] ?? null;
                }

                if ($paidAt) {
                    $this->line("  Submission #{$submission->id}: missing paid_at. " . ($dryRun ? '[DRY RUN: would set]' : 'Setting...'));
                    if (! $dryRun) {
                        $submission->update(['paid_at' => $paidAt]);
                    }
                    $fixedDates++;
                    $changes[] = 'paid_at';
                }
            }

            if ($completedPayment && ! $completedPayment->transaction_id) {
                $transactionId = null;

                if ($completedPayment->payment_response) {
                    $response = is_array($completedPayment->payment_response)
                        ? $completedPayment->payment_response
                        : json_decode($completedPayment->payment_response, true);
                    $transactionId = $response['id'] ?? null;
                }

                if (! $transactionId && $completedPayment->billplz_bill_id) {
                    $transactionId = $completedPayment->billplz_bill_id;
                }

                if ($transactionId) {
                    $this->line("  Submission #{$submission->id}: missing transaction_id. " . ($dryRun ? '[DRY RUN: would set]' : 'Setting...'));
                    if (! $dryRun) {
                        $completedPayment->update(['transaction_id' => $transactionId]);
                    }
                    $fixedTransactions++;
                    $changes[] = 'transaction_id';
                }
            }

            if (! empty($changes) && ! $dryRun) {
                Log::info('Fixed missing submission data via scheduled command', [
                    'submission_id'      => $submission->id,
                    'fixed_fields'       => $changes,
                    'tax_invoice_number' => $submission->tax_invoice_number,
                    'paid_at'            => $submission->paid_at,
                ]);
            }
        }

        $this->newLine();
        $this->line("Fixed: {$fixedReceipts} receipt(s), {$fixedDates} paid date(s), {$fixedTransactions} transaction ID(s)");

        return self::SUCCESS;
    }
}
