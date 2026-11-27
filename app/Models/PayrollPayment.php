<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PayrollPayment extends Model
{
    protected $fillable = [
        'payroll_submission_id',
        'payment_method',
        'payment_type',
        'bank_name',
        'billplz_bill_id',
        'billplz_url',
        'transaction_id',
        'status',
        'amount',
        'payment_response',
        'completed_at',
        'payment_proof_path',
        'payment_proof_name',
        'recorded_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'completed_at' => 'datetime',
    ];

    /**
     * Get the payroll submission this payment belongs to
     */
    public function payrollSubmission()
    {
        return $this->belongsTo(PayrollSubmission::class);
    }

    /**
     * Alias for payrollSubmission relationship
     */
    public function submission()
    {
        return $this->belongsTo(PayrollSubmission::class, 'payroll_submission_id');
    }

    /**
     * The admin/finance user who manually recorded this payment (if any)
     */
    public function recordedBy()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * Whether this payment was recorded manually (off-platform), e.g. a direct
     * bank transfer, rather than collected automatically through Billplz FPX.
     */
    public function isManual(): bool
    {
        return $this->payment_method !== 'billplz';
    }

    /**
     * Whether a proof-of-payment file is attached
     */
    public function hasProof(): bool
    {
        return ! empty($this->payment_proof_path);
    }

    /**
     * Check if payment is completed
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if payment is pending
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if payment failed
     */
    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Mark payment as completed and its payroll as paid.
     *
     * A payroll is paid once. If it is already paid when this payment comes
     * in, the money was still taken, so this payment is recorded as completed
     * but flagged with `duplicate_of_payment_id` for a refund, and the payroll
     * is left as it is. Returns false in that case, true otherwise.
     *
     * @param  array  $attributes  Extra columns to save (transaction_id, bank_name, completed_at...)
     */
    public function markAsCompleted(array $response = [], array $attributes = []): bool
    {
        return DB::transaction(function () use ($response, $attributes) {
            // Lock the payroll row so two completions for it run one at a time.
            $submission = PayrollSubmission::whereKey($this->payroll_submission_id)
                ->lockForUpdate()
                ->first();

            $this->refresh();

            if ($this->status === 'completed') {
                return true;
            }

            $earlierPaymentId = static::where('payroll_submission_id', $this->payroll_submission_id)
                ->whereKeyNot($this->id)
                ->where('status', 'completed')
                ->value('id');

            $isDuplicate = $earlierPaymentId !== null || $submission?->status === 'paid';

            if ($isDuplicate) {
                $response['duplicate_of_payment_id'] = $earlierPaymentId;
                $response['refund_required'] = true;
            }

            $completedAt = $attributes['completed_at'] ?? now();

            $this->update(array_merge($attributes, [
                'status' => 'completed',
                'completed_at' => $completedAt,
                'payment_response' => json_encode($response),
            ]));

            if ($isDuplicate) {
                Log::critical('Duplicate payment received for a payroll that is already paid — refund required', [
                    'payment_id' => $this->id,
                    'duplicate_of_payment_id' => $earlierPaymentId,
                    'submission_id' => $this->payroll_submission_id,
                    'amount' => $this->amount,
                    'billplz_bill_id' => $this->billplz_bill_id,
                ]);

                return false;
            }

            $submission?->update([
                'status' => 'paid',
                'paid_at' => $completedAt,
            ]);

            return true;
        });
    }

    /**
     * Whether this payment was taken for a payroll that was already paid.
     */
    public function isDuplicate(): bool
    {
        return (bool) ($this->payment_response['refund_required'] ?? false);
    }

    /**
     * Mark payment as failed
     */
    public function markAsFailed(array $response = []): void
    {
        $this->update([
            'status' => 'failed',
            'payment_response' => json_encode($response),
        ]);
    }

    /**
     * Get payment response as array
     */
    public function getPaymentResponseAttribute($value)
    {
        return $value ? json_decode($value, true) : [];
    }
}
