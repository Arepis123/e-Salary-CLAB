<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class PayrollSubmission extends Model
{
    protected $fillable = [
        'contractor_clab_no',
        'month',
        'year',
        'payment_deadline',
        'status',
        'has_penalty',
        'penalty_amount',
        'total_amount',
        'service_charge',
        'sst',
        'grand_total',
        'total_with_penalty',
        'total_workers',
        'submitted_at',
        'paid_at',
    ];

    protected $casts = [
        'payment_deadline' => 'datetime',
        'has_penalty' => 'boolean',
        'penalty_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'service_charge' => 'decimal:2',
        'sst' => 'decimal:2',
        'grand_total' => 'decimal:2',
        'total_with_penalty' => 'decimal:2',
        'submitted_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    /**
     * Get the user (contractor/client) for this submission
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'contractor_clab_no', 'contractor_clab_no');
    }

    /**
     * Get the workers in this payroll submission
     */
    public function workers()
    {
        return $this->hasMany(PayrollWorker::class);
    }

    /**
     * Get the payment record for this submission (latest/active payment)
     */
    public function payment()
    {
        return $this->hasOne(PayrollPayment::class)->latestOfMany();
    }

    /**
     * Get all payment attempts for this submission
     */
    public function payments()
    {
        return $this->hasMany(PayrollPayment::class)->orderBy('created_at', 'desc');
    }

    /**
     * Check if payment deadline has passed
     */
    public function isOverdue(): bool
    {
        return $this->payment_deadline->isPast() && $this->status !== 'paid';
    }

    /**
     * Calculate penalty if overdue (8% of grand total)
     */
    public function calculatePenalty(): float
    {
        if ($this->isOverdue()) {
            return $this->grand_total * 0.08;
        }
        return 0;
    }

    /**
     * Update penalty if overdue
     */
    public function updatePenalty(): void
    {
        if ($this->isOverdue() && !$this->has_penalty) {
            $penalty = $this->calculatePenalty();
            $this->update([
                'has_penalty' => true,
                'penalty_amount' => $penalty,
                'total_with_penalty' => $this->grand_total + $penalty,
            ]);
        }
    }

    /**
     * Get the total amount including penalty (dynamic calculation)
     */
    public function getTotalDueAttribute(): float
    {
        // If penalty was already applied and saved, use it
        if ($this->has_penalty && $this->penalty_amount > 0) {
            return $this->grand_total + $this->penalty_amount;
        }

        // Otherwise, calculate dynamically if overdue
        if ($this->isOverdue() && $this->status !== 'paid') {
            $penalty = $this->calculatePenalty();
            return $this->grand_total + $penalty;
        }

        return $this->grand_total;
    }

    /**
     * Get days until deadline
     */
    public function daysUntilDeadline(): int
    {
        return now()->diffInDays($this->payment_deadline, false);
    }

    /**
     * Scope to filter by contractor
     */
    public function scopeByContractor($query, string $clabNo)
    {
        return $query->where('contractor_clab_no', $clabNo);
    }

    /**
     * Scope to filter by month and year
     */
    public function scopeForMonth($query, int $month, int $year)
    {
        return $query->where('month', $month)->where('year', $year);
    }

    /**
     * Scope to get overdue submissions
     */
    public function scopeOverdue($query)
    {
        return $query->where('payment_deadline', '<', now())
                    ->whereNotIn('status', ['paid']);
    }

    /**
     * Calculate service charge (RM200 per worker)
     */
    public function calculateServiceCharge(): float
    {
        return $this->total_workers * 200;
    }

    /**
     * Calculate SST (8% of service charge)
     */
    public function calculateSST(): float
    {
        return $this->calculateServiceCharge() * 0.08;
    }

    /**
     * Calculate grand total (total amount + service charge + SST)
     */
    public function calculateGrandTotal(): float
    {
        return $this->total_amount + $this->calculateServiceCharge() + $this->calculateSST();
    }

    /**
     * Get formatted month/year
     */
    public function getMonthYearAttribute(): string
    {
        return Carbon::create($this->year, $this->month, 1)->format('F Y');
    }
}
