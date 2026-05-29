<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
     * Mark payment as completed
     */
    public function markAsCompleted(array $response = []): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
            'payment_response' => json_encode($response),
        ]);

        // Update payroll submission status
        $this->payrollSubmission->update([
            'status' => 'paid',
            'paid_at' => now(),
        ]);
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
