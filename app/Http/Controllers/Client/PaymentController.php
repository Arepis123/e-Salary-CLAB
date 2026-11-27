<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\PayrollPayment;
use App\Models\PayrollSubmission;
use App\Services\BillplzService;
use App\Services\ContractorConfigurationService;
use App\Traits\LogsActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    use LogsActivity;

    protected BillplzService $billplzService;

    protected ContractorConfigurationService $configService;

    public function __construct(BillplzService $billplzService, ContractorConfigurationService $configService)
    {
        $this->billplzService = $billplzService;
        $this->configService = $configService;
    }

    /**
     * Create Billplz bill and redirect to payment
     */
    public function createPayment(Request $request, $submissionId)
    {
        // One payment set-up per payroll at a time: two quick clicks must not
        // both get past the checks below and issue two bills.
        $lock = Cache::lock('payroll-payment:'.(int) $submissionId, 60);

        if (! $lock->get()) {
            return redirect()->route('timesheet')
                ->with('error', 'A payment for this payroll is already being prepared. Please wait a moment and try again.');
        }

        try {
            return $this->startPayment($request, $submissionId);
        } finally {
            $lock->release();
        }
    }

    /**
     * Close every earlier Billplz bill for this payroll so it can no longer be
     * paid. Returns a response when a new bill must not be issued, or null
     * when it is safe to go ahead.
     *
     * Payment rows are kept as they are for attempt tracking; only pending
     * rows whose bill has now been closed are marked cancelled.
     */
    protected function closeOpenBills(PayrollSubmission $submission)
    {
        $billIds = PayrollPayment::where('payroll_submission_id', $submission->id)
            ->whereIn('status', ['pending', 'failed', 'cancelled'])
            ->whereNotNull('billplz_bill_id')
            ->distinct()
            ->pluck('billplz_bill_id');

        foreach ($billIds as $billId) {
            $result = $this->billplzService->closeUnpaidBill($billId);

            if ($result['status'] === 'closed') {
                PayrollPayment::where('payroll_submission_id', $submission->id)
                    ->where('billplz_bill_id', $billId)
                    ->where('status', 'pending')
                    ->update([
                        'status' => 'cancelled',
                        'payment_response' => json_encode([
                            'reason' => 'Bill closed before a new bill was issued',
                            'cancelled_at' => now()->toDateTimeString(),
                        ]),
                    ]);

                continue;
            }

            if ($result['status'] === 'paid') {
                // The client already paid on this bill; the callback may not
                // have arrived yet. Record it instead of billing them again.
                $payment = PayrollPayment::where('payroll_submission_id', $submission->id)
                    ->where('billplz_bill_id', $billId)
                    ->where('status', '!=', 'redirected')
                    ->orderBy('id')
                    ->first();

                if ($payment && $this->billplzService->isBillPaidInFull($result['bill'], (float) $payment->amount)) {
                    $payment->markAsCompleted(['verified_bill' => $result['bill']], ['transaction_id' => $billId]);

                    return redirect()->route('timesheet')
                        ->with('success', 'This payroll has already been paid.');
                }

                Log::critical('Earlier bill was paid but not in full — new bill blocked', [
                    'submission_id' => $submission->id,
                    'bill_id' => $billId,
                    'payment_id' => $payment?->id,
                    'paid_amount_sen' => $result['bill']['paid_amount'] ?? null,
                ]);

                return redirect()->route('timesheet')
                    ->with('error', 'A payment was received on an earlier bill for this payroll. Please contact support before paying again.');
            }

            Log::warning('Could not confirm an earlier bill is closed — new bill blocked', [
                'submission_id' => $submission->id,
                'bill_id' => $billId,
            ]);

            return redirect()->route('timesheet')
                ->with('error', 'We could not confirm the status of your previous payment attempt. Please try again in a few minutes.');
        }

        return null;
    }

    protected function startPayment(Request $request, $submissionId)
    {
        $clabNo = $request->user()->contractor_clab_no;

        // Get payroll submission
        $submission = PayrollSubmission::with(['workers', 'payment'])
            ->where('id', $submissionId)
            ->where('contractor_clab_no', $clabNo)
            ->firstOrFail();

        // Check if already paid
        if ($submission->status === 'paid') {
            return redirect()->route('timesheet')
                ->with('error', 'This payroll has already been paid.');
        }

        // Block payment if the admin has locked payments for this contractor
        // (e.g. while a wrong payroll calculation is being regenerated).
        // Show a Cloudflare-style block page so the hold is unmistakable.
        if (! $this->configService->isPaymentEnabled($clabNo)) {
            Log::info('Payment blocked — contractor payments are disabled by admin', [
                'submission_id' => $submission->id,
                'contractor_clab_no' => $clabNo,
                'user_id' => $request->user()->id,
            ]);

            return response()->view('client.payment-blocked', [
                'title' => 'Internal server error',
                'errorCode' => 500,
                'time' => now()->utc()->format('Y-m-d H:i:s').' UTC',
                'host' => $request->getHost(),
                'cloudflareLocation' => 'Kuala Lumpur',
                'rayId' => strtolower(bin2hex(random_bytes(8))).'-KUL',
            ], 403);
        }

        // Block payment if not approved by admin
        if (! $submission->canCreatePayment()) {
            return redirect()->route('timesheet')
                ->with('error', 'This submission must be approved by admin before payment can be created.');
        }

        // Check if there's a recent pending payment (within last 2 hours).
        // NOTE: $submission->payment only returns completed payments, so we query directly.
        $recentPendingPayment = null;
        $latestPendingPayment = PayrollPayment::where('payroll_submission_id', $submission->id)
            ->where('status', 'pending')
            ->latest()
            ->first();

        if ($latestPendingPayment) {
            $paymentAge = $latestPendingPayment->created_at->diffInMinutes(now());

            if ($paymentAge < 120) {
                // Payment is recent (less than 2 hours old), redirect to existing bill
                $recentPendingPayment = $latestPendingPayment;
            }
        }

        if ($recentPendingPayment) {
            // Verify the bill still exists on Billplz before redirecting
            $billExists = false;

            try {
                $bill = $this->billplzService->getBill($recentPendingPayment->billplz_bill_id);

                if ($bill && ($bill['state'] ?? null) === 'due') {
                    $billExists = true;

                    Log::info('Redirecting to existing pending payment', [
                        'payment_id' => $recentPendingPayment->id,
                        'payment_age_minutes' => $paymentAge,
                        'submission_id' => $submission->id,
                        'bill_verified' => true,
                    ]);
                } else {
                    Log::warning('Pending bill is no longer payable on Billplz, will close it and create a new bill', [
                        'payment_id' => $recentPendingPayment->id,
                        'bill_id' => $recentPendingPayment->billplz_bill_id,
                        'submission_id' => $submission->id,
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('Failed to verify bill existence on Billplz', [
                    'payment_id' => $recentPendingPayment->id,
                    'bill_id' => $recentPendingPayment->billplz_bill_id,
                    'error' => $e->getMessage(),
                ]);
            }

            if ($billExists) {
                // Bill exists on Billplz, safe to redirect

                // Log this attempt even though we're redirecting
                // This helps admin track how many times client attempted payment
                $attemptLog = PayrollPayment::create([
                    'payroll_submission_id' => $submission->id,
                    'payment_method' => 'billplz',
                    'billplz_bill_id' => $recentPendingPayment->billplz_bill_id,
                    'billplz_url' => $recentPendingPayment->billplz_url,
                    'amount' => $recentPendingPayment->amount,
                    'status' => 'redirected',
                    'payment_response' => json_encode([
                        'redirected_to_payment_id' => $recentPendingPayment->id,
                        'reason' => 'Redirected to existing pending payment within 2-hour window',
                        'payment_age_minutes' => $paymentAge,
                        'redirected_at' => now()->toDateTimeString(),
                    ]),
                ]);

                Log::info('Payment attempt logged as redirected', [
                    'attempt_id' => $attemptLog->id,
                    'original_payment_id' => $recentPendingPayment->id,
                    'submission_id' => $submission->id,
                ]);

                // Log activity
                $this->logPaymentActivity(
                    action: 'redirected',
                    description: "Client attempted payment but was redirected to existing pending payment for payroll {$submission->month_year}",
                    payment: $attemptLog,
                    properties: [
                        'submission_id' => $submission->id,
                        'amount' => $recentPendingPayment->amount,
                        'period' => $submission->month_year,
                        'original_payment_id' => $recentPendingPayment->id,
                        'payment_age_minutes' => $paymentAge,
                    ]
                );

                $url = $this->billplzService->getDirectPaymentUrl($recentPendingPayment->billplz_url);

                return redirect($url);
            }

            // The bill could not be confirmed as payable. Don't assume it is gone:
            // the close step below checks it properly before a new bill is issued.
        }

        // Close every earlier bill for this payroll before issuing a new one, so
        // only one bill can ever be paid. Earlier code left failed bills open on
        // Billplz, which let a client pay the same payroll twice.
        if ($blocked = $this->closeOpenBills($submission)) {
            return $blocked;
        }

        // Check for penalty (overdue) and update if needed
        $submission->updatePenalty();
        $submission->refresh();

        // Use the model's total_due attribute which handles all penalty logic
        // This ensures consistency across the application
        $totalAmount = $submission->total_due;

        // TESTING OVERRIDE: Use RM1.00 for payment testing — REMOVE AFTER TEST
        // $totalAmount = 1.00;

        // Create Billplz bill
        // Set expiry based on whether penalty has been applied:
        // - Before deadline: expire at deadline (prevents paying late without penalty)
        // - After deadline: penalty already included, give 14 days to pay
        $dueAt = $submission->has_penalty
            ? now()->addDays(14)->format('Y-m-d')  // Penalty applied, 14 days to pay
            : $submission->payment_deadline->format('Y-m-d');  // No penalty, expire at deadline

        $billData = [
            'email' => $request->user()->email,  // TESTING OVERRIDE — 'email' => 'hafiz@clab.com.my',
            'name' => $request->user()->name ?? $request->user()->company_name,
            'amount' => $totalAmount,
            'callback_url' => route('billplz.callback'),
            'redirect_url' => route('client.payment.return', ['submission' => $submissionId]),
            'description' => "Payroll Payment - {$submission->month_year}",
            'reference_1_label' => 'Payroll ID',
            'reference_1' => $submission->id,
            'due_at' => $dueAt,
        ];

        // Attempt to create Billplz bill
        $bill = $this->billplzService->createBill($billData);

        if (! $bill) {
            // Log the failure
            Log::error('Failed to create Billplz bill', [
                'submission_id' => $submission->id,
                'amount' => $totalAmount,
            ]);

            // Create payment record anyway to track the attempt
            $payment = PayrollPayment::create([
                'payroll_submission_id' => $submission->id,
                'payment_method' => 'billplz',
                'billplz_bill_id' => null,
                'billplz_url' => null,
                'amount' => $totalAmount,
                'status' => 'failed',
                'payment_response' => json_encode(['error' => 'Failed to create Billplz bill']),
            ]);

            Log::info('Payment attempt logged as failed', [
                'payment_id' => $payment->id,
                'submission_id' => $submission->id,
            ]);

            return back()->with('error', 'Failed to create payment. Please try again or contact support.');
        }

        // Create payment record for successful bill creation
        $payment = PayrollPayment::create([
            'payroll_submission_id' => $submission->id,
            'payment_method' => 'billplz',
            'billplz_bill_id' => $bill['id'],
            'billplz_url' => $bill['url'],
            'amount' => $totalAmount,
            'status' => 'pending',
        ]);

        // Log successful payment attempt creation
        Log::info('New payment attempt created successfully', [
            'submission_id' => $submission->id,
            'payment_id' => $payment->id,
            'bill_id' => $bill['id'],
            'amount' => $totalAmount,
        ]);

        // Log activity
        $this->logPaymentActivity(
            action: 'initiated',
            description: 'Initiated payment of RM '.number_format($totalAmount, 2)." for payroll {$submission->month_year}",
            payment: $payment,
            properties: [
                'submission_id' => $submission->id,
                'amount' => $totalAmount,
                'period' => $submission->month_year,
                'billplz_bill_id' => $bill['id'],
            ]
        );

        // Update submission status — only while it is still admin-reviewed. An
        // admin re-sync can withdraw the approval while the bill above is being
        // created; that bill is at the old amount and must not be paid.
        $stillReviewed = PayrollSubmission::whereKey($submission->id)
            ->whereNotNull('admin_reviewed_at')
            ->whereIn('status', ['approved', 'submitted', 'pending_payment', 'overdue'])
            ->update(['status' => 'pending_payment']);

        if (! $stillReviewed) {
            $payment->update([
                'status' => 'cancelled',
                'payment_response' => json_encode([
                    'reason' => 'Approval withdrawn while payment was being created',
                    'cancelled_at' => now()->toDateTimeString(),
                ]),
            ]);

            try {
                $this->billplzService->deleteBill($bill['id']);
            } catch (\Exception $e) {
                Log::warning('Could not delete Billplz bill after approval was withdrawn', [
                    'bill_id' => $bill['id'],
                    'submission_id' => $submission->id,
                    'error' => $e->getMessage(),
                ]);
            }

            return redirect()->route('timesheet')
                ->with('error', 'This submission is being re-reviewed by admin. Please try again once it is approved.');
        }

        // Redirect to Billplz payment page with auto-submit
        $paymentUrl = $this->billplzService->getDirectPaymentUrl($bill['url']);

        return redirect($paymentUrl);
    }

    /**
     * Handle Billplz callback webhook
     */
    public function callback(Request $request)
    {
        $billplzId = (string) $request->input('id');

        // Bill IDs are short alphanumeric strings. Anything else is not from
        // Billplz, and must never reach the API URL below.
        if (! preg_match('/^[A-Za-z0-9_-]{1,64}$/', $billplzId)) {
            return response('Invalid bill', 400);
        }

        // The signature is logged for monitoring only. The decision below never
        // trusts the POST body: anyone can send this request, so payment status
        // and amount are read from the Billplz API with our own API key.
        if (! $this->billplzService->validateSignature($request->except('x_signature'), (string) $request->input('x_signature'))) {
            Log::warning('Billplz callback signature mismatch — verifying with Billplz API instead', [
                'bill_id' => $billplzId,
                'ip' => $request->ip(),
            ]);
        }

        // Find payment by billplz_bill_id. 'redirected' rows share the bill ID but
        // only log attempts; the original row is the one that holds the bill.
        $payment = PayrollPayment::where('billplz_bill_id', $billplzId)
            ->where('status', '!=', 'redirected')
            ->orderBy('id')
            ->first();

        if (! $payment) {
            Log::error('Payment not found for Billplz callback', [
                'bill_id' => $billplzId,
                'ip' => $request->ip(),
            ]);

            return response('Payment not found', 404);
        }

        // Check if already processed
        if ($payment->status === 'completed') {
            return response('OK');
        }

        $bill = $this->billplzService->getBill($billplzId);

        if (! $bill || ($bill['id'] ?? null) !== $billplzId) {
            Log::error('Billplz callback: could not verify bill with Billplz API', [
                'bill_id' => $billplzId,
                'payment_id' => $payment->id,
            ]);

            // Non-2xx so Billplz retries the callback later.
            return response('Unable to verify bill', 503);
        }

        $paid = $this->billplzService->isBillPaidInFull($bill, (float) $payment->amount);
        $state = $bill['state'] ?? null;
        $amount = isset($bill['paid_amount']) ? ((int) $bill['paid_amount']) / 100 : 0;
        $billplzBillId = $billplzId; // Billplz Bill ID is the transaction identifier
        $transactionStatus = $request->input('transaction_status');

        if (($bill['paid'] ?? false) === true && ! $paid) {
            Log::critical('Billplz bill paid with an amount that does not match the payment record', [
                'bill_id' => $billplzId,
                'payment_id' => $payment->id,
                'expected_amount' => $payment->amount,
                'paid_amount_sen' => $bill['paid_amount'] ?? null,
            ]);
        }

        // Capture payment type and bank information from Billplz callback
        // Billplz sends bank_code and other bank details in the callback
        $bankCode = $request->input('bank_code');
        $bankName = $request->input('bank_name') ?? $bankCode;

        // Determine payment type: B2B or B2C
        // B2B banks typically have specific codes (e.g., corporate/business FPX)
        // NOTE: This list should be updated based on actual Billplz responses
        // Common B2B bank codes in Malaysia FPX system:
        $b2bBankCodes = ['ABB0234', 'ABMB0213', 'AGRO01', 'BIMB0340', 'BKRM0602', 'BMMB0342',
            'BSN0601', 'CIT0219', 'HLB0224', 'HSBC0223', 'KFH0346', 'MB2U0227',
            'MBB0228', 'OCBC0229', 'PBB0233', 'RHB0218', 'SCB0216', 'UOB0226'];

        // If no bank_code provided or empty, default to B2C (most common for individuals)
        $paymentType = $bankCode && in_array($bankCode, $b2bBankCodes) ? 'B2B' : 'B2C';

        if ($paid) {
            // Payment successful (verified against the Billplz API). markAsCompleted
            // refuses to mark the payroll paid twice and flags a second payment.
            $firstPayment = $payment->markAsCompleted(
                ['callback' => $request->except('x_signature'), 'verified_bill' => $bill],
                [
                    'transaction_id' => $billplzBillId,
                    'payment_type' => $paymentType,
                    'bank_name' => $bankName,
                ]
            );

            if (! $firstPayment) {
                $submission = $payment->payrollSubmission;
                $payment->refresh();

                ActivityLog::create([
                    'user_id' => $submission->user_id ?? null,
                    'contractor_clab_no' => $submission->contractor_clab_no,
                    'user_name' => $submission->user ? ($submission->user->name ?? $submission->user->company_name) : 'Client '.$submission->contractor_clab_no,
                    'user_email' => $submission->user?->email,
                    'module' => 'payment',
                    'action' => 'duplicate',
                    'description' => 'Duplicate payment of RM '.number_format($amount, 2)." received for payroll {$submission->month_year}, which was already paid. Refund required.",
                    'subject_type' => get_class($payment),
                    'subject_id' => $payment->id,
                    'properties' => [
                        'submission_id' => $submission->id,
                        'amount' => $amount,
                        'transaction_id' => $billplzBillId,
                        'duplicate_of_payment_id' => $payment->payment_response['duplicate_of_payment_id'] ?? null,
                        'period' => $submission->month_year,
                    ],
                    'ip_address' => $request->ip(),
                    'user_agent' => 'Billplz Webhook',
                    'url' => $request->fullUrl(),
                    'method' => $request->method(),
                ]);

                return response('OK');
            }

            Log::info('Billplz payment completed', [
                'payment_id' => $payment->id,
                'submission_id' => $payment->payroll_submission_id,
                'bill_id' => $billplzId,
                'transaction_id' => $billplzBillId,
                'amount' => $amount,
                'payment_type' => $paymentType,
                'bank_name' => $bankName,
            ]);

            // Log activity (without user context as this is a webhook)
            $submission = $payment->payrollSubmission;
            ActivityLog::create([
                'user_id' => $submission->user_id ?? null,
                'contractor_clab_no' => $submission->contractor_clab_no,
                'user_name' => $submission->user ? ($submission->user->name ?? $submission->user->company_name) : 'Client '.$submission->contractor_clab_no,
                'user_email' => $submission->user?->email,
                'module' => 'payment',
                'action' => 'completed',
                'description' => 'Payment of RM '.number_format($amount, 2)." completed for payroll {$submission->month_year} via {$paymentType} ({$bankName})",
                'subject_type' => get_class($payment),
                'subject_id' => $payment->id,
                'properties' => [
                    'submission_id' => $submission->id,
                    'amount' => $amount,
                    'transaction_id' => $billplzBillId,
                    'period' => $submission->month_year,
                    'payment_type' => $paymentType,
                    'bank_name' => $bankName,
                ],
                'ip_address' => $request->ip(),
                'user_agent' => 'Billplz Webhook',
                'url' => $request->fullUrl(),
                'method' => $request->method(),
            ]);
        } else {
            // Payment failed - save all payment details including transaction info
            $payment->update([
                'status' => 'failed',
                'payment_response' => json_encode($request->all()),
                'transaction_id' => $billplzBillId,
                'payment_type' => $paymentType,
                'bank_name' => $bankName,
            ]);

            Log::warning('Billplz payment failed - attempt logged', [
                'payment_id' => $payment->id,
                'submission_id' => $payment->payroll_submission_id,
                'bill_id' => $billplzId,
                'paid' => $paid,
                'state' => $state,
                'transaction_status' => $transactionStatus,
                'reason' => 'Payment attempt failed and logged for retry',
            ]);

            // Log activity for failed payment
            $submission = $payment->payrollSubmission;
            ActivityLog::create([
                'user_id' => $submission->user_id ?? null,
                'contractor_clab_no' => $submission->contractor_clab_no,
                'user_name' => $submission->user ? ($submission->user->name ?? $submission->user->company_name) : 'Client '.$submission->contractor_clab_no,
                'user_email' => $submission->user?->email,
                'module' => 'payment',
                'action' => 'failed',
                'description' => "Payment attempt failed for payroll {$submission->month_year}",
                'subject_type' => get_class($payment),
                'subject_id' => $payment->id,
                'properties' => [
                    'submission_id' => $submission->id,
                    'amount' => $payment->amount,
                    'paid' => $paid,
                    'state' => $state,
                    'transaction_status' => $transactionStatus,
                    'period' => $submission->month_year,
                ],
                'ip_address' => $request->ip(),
                'user_agent' => 'Billplz Webhook',
                'url' => $request->fullUrl(),
                'method' => $request->method(),
            ]);
        }

        return response('OK');
    }

    /**
     * Handle return from Billplz payment page
     */
    public function return(Request $request, $submissionId)
    {
        $submission = PayrollSubmission::with('payment')->findOrFail($submissionId);

        // Get the bill ID from Billplz redirect parameters
        $billplzParams = $request->input('billplz');
        $billplzBillId = is_array($billplzParams) ? ($billplzParams['id'] ?? null) : null;

        // Find the specific payment for this bill (not just latest payment)
        $payment = null;
        if ($billplzBillId) {
            $payment = PayrollPayment::where('payroll_submission_id', $submissionId)
                ->where('billplz_bill_id', $billplzBillId)
                ->where('status', '!=', 'redirected')
                ->orderBy('id')
                ->first();
        }

        // Fall back to latest payment if bill ID not found in params
        if (! $payment) {
            $payment = $submission->payment;
        }

        // Payment still pending, check with Billplz first
        $billPaid = false;
        if ($payment && $payment->billplz_bill_id && in_array($payment->status, ['pending', 'failed', 'cancelled'])) {
            $bill = $this->billplzService->getBill($payment->billplz_bill_id);

            if ($bill && $this->billplzService->isBillPaidInFull($bill, (float) $payment->amount)) {
                // Update payment status
                $payment->markAsCompleted($bill);
                $submission->refresh();
                $billPaid = true;
            }
        }

        // Check if user is authenticated
        if (! auth()->check()) {
            // Session was lost during the BillPlz redirect (cross-site POST drops SameSite=lax cookies).
            // Redirect to login with appropriate message; after login, go to dashboard.
            session()->put('url.intended', route('dashboard'));

            if ($payment && ($payment->status === 'completed' || $billPaid)) {
                return redirect()->route('login')
                    ->with('success', 'Your payment was successful! Please login to view your dashboard.');
            } elseif ($payment && $payment->status === 'cancelled') {
                return redirect()->route('login')
                    ->with('warning', 'This payment was cancelled. Please login to check details and make a new payment if required.');
            } elseif ($payment && $payment->status === 'failed') {
                return redirect()->route('login')
                    ->with('error', 'Payment failed. Please login to try again.');
            } elseif ($payment && $payment->status === 'pending' && ! $billPaid) {
                return redirect()->route('login')
                    ->with('warning', 'Payment was not completed. Please login to try again.');
            } else {
                return redirect()->route('login')
                    ->with('info', 'Payment is being processed. Please login to check status.');
            }
        }

        // A logged-in client may only see the outcome of their own company's payroll.
        $viewer = auth()->user();
        if (! $viewer->hasAdminAccess() && $viewer->contractor_clab_no !== $submission->contractor_clab_no) {
            abort(404);
        }

        // User is authenticated, show the appropriate view
        if ($payment && $payment->status === 'completed') {
            return view('client.payment-success', compact('submission'));
        } elseif ($payment && $payment->status === 'cancelled') {
            // Payment was cancelled by admin, redirect to timesheet with message
            return redirect()->route('timesheet')
                ->with('warning', 'This payment was cancelled. The payroll amount may have been updated. Please check and make a new payment if required.');
        } elseif ($payment && $payment->status === 'failed') {
            return view('client.payment-failed', compact('submission'));
        } elseif ($payment && $payment->status === 'pending' && ! $billPaid) {
            // User returned without completing payment (cancelled/abandoned)
            // Log this abandonment for admin visibility
            Log::info('Client abandoned payment without completing', [
                'payment_id' => $payment->id,
                'submission_id' => $submission->id,
                'user_id' => auth()->id(),
            ]);

            // Log activity for abandoned payment
            $this->logPaymentActivity(
                action: 'abandoned',
                description: "Client returned from payment page without completing payment for payroll {$submission->month_year}",
                payment: $payment,
                properties: [
                    'submission_id' => $submission->id,
                    'amount' => $payment->amount,
                    'period' => $submission->month_year,
                    'billplz_bill_id' => $payment->billplz_bill_id,
                ]
            );

            return view('client.payment-cancelled', compact('submission'));
        } else {
            // Payment is actually being processed (rare case)
            return view('client.payment-pending', compact('submission'));
        }
    }
}
