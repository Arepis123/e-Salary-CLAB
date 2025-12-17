<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\PayrollSubmission;
use App\Models\PayrollPayment;
use App\Services\BillplzService;
use App\Traits\LogsActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    use LogsActivity;

    protected BillplzService $billplzService;

    public function __construct(BillplzService $billplzService)
    {
        $this->billplzService = $billplzService;
    }

    /**
     * Create Billplz bill and redirect to payment
     */
    public function createPayment(Request $request, $submissionId)
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

        // Block payment if not approved by admin
        if (!$submission->canCreatePayment()) {
            return redirect()->route('timesheet')
                ->with('error', 'This submission must be approved by admin before payment can be created.');
        }

        // Check if there's a recent pending payment (within last 2 hours)
        $recentPendingPayment = null;
        if ($submission->payment && $submission->payment->status === 'pending') {
            $paymentAge = $submission->payment->created_at->diffInMinutes(now());

            if ($paymentAge < 120) {
                // Payment is recent (less than 2 hours old), redirect to existing bill
                $recentPendingPayment = $submission->payment;
            }
        }

        if ($recentPendingPayment) {
            Log::info('Redirecting to existing pending payment', [
                'payment_id' => $recentPendingPayment->id,
                'payment_age_minutes' => $paymentAge,
                'submission_id' => $submission->id,
            ]);

            $url = $this->billplzService->getDirectPaymentUrl($recentPendingPayment->billplz_url);
            return redirect($url);
        }

        // If there's an old/expired payment (pending for >2 hours, failed, or cancelled),
        // mark it as cancelled and create a new attempt
        if ($submission->payment && in_array($submission->payment->status, ['failed', 'cancelled', 'pending'])) {
            // Mark old payment as cancelled if it's pending
            if ($submission->payment->status === 'pending') {
                $submission->payment->update([
                    'status' => 'cancelled',
                    'payment_response' => json_encode([
                        'reason' => 'Payment expired or user initiated new payment',
                        'cancelled_at' => now()->toDateTimeString(),
                    ]),
                ]);

                Log::info('Marked old pending payment as cancelled', [
                    'old_payment_id' => $submission->payment->id,
                    'submission_id' => $submission->id,
                ]);
            }

            // Try to delete the old Billplz bill
            try {
                if ($submission->payment->billplz_bill_id) {
                    $this->billplzService->deleteBill($submission->payment->billplz_bill_id);
                    Log::info('Deleted previous Billplz bill before retry', [
                        'old_bill_id' => $submission->payment->billplz_bill_id,
                        'old_payment_id' => $submission->payment->id,
                        'submission_id' => $submission->id,
                    ]);
                }
            } catch (\Exception $e) {
                Log::warning('Could not delete previous Billplz bill', [
                    'bill_id' => $submission->payment->billplz_bill_id ?? 'unknown',
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Check for penalty (overdue)
        $submission->updatePenalty();
        $submission->refresh();

        // Calculate total amount to pay (includes payroll + service charge + SST + penalty if overdue)
        // Use client_total which includes: admin_final_amount + service_charge + SST
        $baseAmount = $submission->client_total;
        $totalAmount = $baseAmount;

        if ($submission->isOverdue()) {
            $penalty = $baseAmount * 0.08; // 8% penalty on the client total
            $totalAmount += $penalty;

            // Update the submission with penalty info if not already set
            if (!$submission->has_penalty) {
                $submission->update([
                    'has_penalty' => true,
                    'penalty_amount' => $penalty,
                    'total_with_penalty' => $totalAmount,
                ]);
            }
        }

        // Create Billplz bill
        $billData = [
            'email' => $request->user()->email,
            'name' => $request->user()->name ?? $request->user()->company_name,
            'amount' => $totalAmount,
            'callback_url' => route('billplz.callback'),
            'redirect_url' => route('client.payment.return', ['submission' => $submissionId]),
            'description' => "Payroll Payment - {$submission->month_year}",
            'reference_1_label' => 'Payroll ID',
            'reference_1' => $submission->id,
        ];

        // Attempt to create Billplz bill
        $bill = $this->billplzService->createBill($billData);

        if (!$bill) {
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
            description: "Initiated payment of RM " . number_format($totalAmount, 2) . " for payroll {$submission->month_year}",
            payment: $payment,
            properties: [
                'submission_id' => $submission->id,
                'amount' => $totalAmount,
                'period' => $submission->month_year,
                'billplz_bill_id' => $bill['id'],
            ]
        );

        // Update submission status
        $submission->update(['status' => 'pending_payment']);

        // Redirect to Billplz payment page with auto-submit
        $paymentUrl = $this->billplzService->getDirectPaymentUrl($bill['url']);

        return redirect($paymentUrl);
    }

    /**
     * Handle Billplz callback webhook
     */
    public function callback(Request $request)
    {
        // Validate signature
        $billplzId = $request->input('id');
        $xSignature = $request->header('X-Signature');

        if (!$this->billplzService->validateSignature($billplzId, $xSignature)) {
            Log::warning('Billplz callback signature validation failed', [
                'bill_id' => $billplzId,
                'x_signature' => $xSignature,
            ]);

            return response('Invalid signature', 403);
        }

        // Find payment by billplz_bill_id
        $payment = PayrollPayment::where('billplz_bill_id', $billplzId)->first();

        if (!$payment) {
            Log::error('Payment not found for Billplz callback', [
                'bill_id' => $billplzId,
            ]);

            return response('Payment not found', 404);
        }

        // Check if already processed
        if ($payment->status === 'completed') {
            return response('OK');
        }

        // Get payment details
        $paid = $request->input('paid') === 'true';
        $state = $request->input('state');
        $amount = $request->input('paid_amount');
        $billplzBillId = $request->input('id'); // Billplz Bill ID is the transaction identifier
        $transactionStatus = $request->input('transaction_status');

        // Store callback response and transaction ID (use Billplz Bill ID as transaction ID)
        $payment->payment_response = json_encode($request->all());
        $payment->transaction_id = $billplzBillId; // Billplz Bill ID is the transaction reference
        $payment->save(); // Save transaction_id before marking as completed

        if ($paid && $state === 'active' && $transactionStatus === 'completed') {
            // Payment successful
            $payment->markAsCompleted($request->all());

            Log::info('Billplz payment completed', [
                'payment_id' => $payment->id,
                'submission_id' => $payment->payroll_submission_id,
                'bill_id' => $billplzId,
                'transaction_id' => $billplzBillId,
                'amount' => $amount,
            ]);

            // Log activity (without user context as this is a webhook)
            $submission = $payment->payrollSubmission;
            ActivityLog::create([
                'user_id' => $submission->user_id ?? null,
                'contractor_clab_no' => $submission->contractor_clab_no,
                'user_name' => $submission->user ? ($submission->user->name ?? $submission->user->company_name) : 'Client ' . $submission->contractor_clab_no,
                'user_email' => $submission->user?->email,
                'module' => 'payment',
                'action' => 'completed',
                'description' => "Payment of RM " . number_format($amount, 2) . " completed for payroll {$submission->month_year}",
                'subject_type' => get_class($payment),
                'subject_id' => $payment->id,
                'properties' => [
                    'submission_id' => $submission->id,
                    'amount' => $amount,
                    'transaction_id' => $billplzBillId,
                    'period' => $submission->month_year,
                ],
                'ip_address' => $request->ip(),
                'user_agent' => 'Billplz Webhook',
                'url' => $request->fullUrl(),
                'method' => $request->method(),
            ]);
        } else {
            // Payment failed - this will be logged as a separate attempt
            $payment->markAsFailed($request->all());

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
                'user_name' => $submission->user ? ($submission->user->name ?? $submission->user->company_name) : 'Client ' . $submission->contractor_clab_no,
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

        // Payment still pending, check with Billplz first
        $billPaid = false;
        if ($submission->payment && $submission->payment->billplz_bill_id && $submission->payment->status === 'pending') {
            $bill = $this->billplzService->getBill($submission->payment->billplz_bill_id);

            if ($bill && $bill['paid']) {
                // Update payment status
                $submission->payment->markAsCompleted($bill);
                $submission->refresh();
                $billPaid = true;
            }
        }

        // Check if user is authenticated
        if (!auth()->check()) {
            // User session expired, show guest views without requiring login
            if ($submission->payment && $submission->payment->status === 'completed') {
                return view('client.payment-success-guest', compact('submission'));
            } elseif ($submission->payment && $submission->payment->status === 'failed') {
                return redirect()->route('login')
                    ->with('error', 'Payment failed. Please login to try again.');
            } elseif ($submission->payment && $submission->payment->status === 'pending' && !$billPaid) {
                // User returned without paying (cancelled)
                return redirect()->route('login')
                    ->with('warning', 'Payment was not completed. Please login to try again.');
            } else {
                return redirect()->route('login')
                    ->with('info', 'Payment is being processed. Please login to check status.');
            }
        }

        // User is authenticated, show the appropriate view
        if ($submission->payment && $submission->payment->status === 'completed') {
            return view('client.payment-success', compact('submission'));
        } elseif ($submission->payment && $submission->payment->status === 'failed') {
            return view('client.payment-failed', compact('submission'));
        } elseif ($submission->payment && $submission->payment->status === 'pending' && !$billPaid) {
            // User returned without completing payment (cancelled)
            return view('client.payment-cancelled', compact('submission'));
        } else {
            // Payment is actually being processed (rare case)
            return view('client.payment-pending', compact('submission'));
        }
    }
}
