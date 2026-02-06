<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

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
        'tax_invoice_number',
        'tax_invoice_generated_at',
        'admin_reviewed_by',
        'admin_reviewed_at',
        'admin_final_amount',
        'admin_notes',
        'breakdown_file_path',
        'breakdown_file_name',
        'payslip_file_path',
        'payslip_file_name',
        'is_legacy_submission',
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
        'tax_invoice_generated_at' => 'datetime',
        'admin_reviewed_at' => 'datetime',
        'admin_final_amount' => 'decimal:2',
        'is_legacy_submission' => 'boolean',
    ];

    /**
     * Get the user (contractor/client) for this submission
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'contractor_clab_no', 'contractor_clab_no');
    }

    /**
     * Get the admin user who reviewed this submission
     */
    public function adminReviewer()
    {
        return $this->belongsTo(User::class, 'admin_reviewed_by');
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
     * Penalty applies starting from the NEXT DAY after deadline (1st of new month)
     * Example: If deadline is Nov 30, penalty applies from Dec 1 onwards
     */
    public function isOverdue(): bool
    {
        // Check if we're AFTER the end of the deadline day (i.e., next day)
        return now()->isAfter($this->payment_deadline->endOfDay()) && $this->status !== 'paid';
    }

    /**
     * Check if contractor is exempt from penalty
     */
    public function isPenaltyExempt(): bool
    {
        $configService = app(\App\Services\ContractorConfigurationService::class);

        return $configService->isPenaltyExempt($this->contractor_clab_no);
    }

    /**
     * Calculate penalty if overdue (8% of client total)
     * Returns 0 if contractor is exempt from penalty
     */
    public function calculatePenalty(): float
    {
        // Check if contractor is exempt from penalty
        if ($this->isPenaltyExempt()) {
            return 0;
        }

        if ($this->isOverdue()) {
            return $this->client_total * 0.08;
        }

        return 0;
    }

    /**
     * Update penalty if overdue
     * Does not apply penalty if contractor is exempt
     */
    public function updatePenalty(): void
    {
        // Skip if contractor is exempt from penalty
        if ($this->isPenaltyExempt()) {
            return;
        }

        if ($this->isOverdue() && ! $this->has_penalty) {
            $penalty = $this->calculatePenalty();
            $this->update([
                'has_penalty' => true,
                'penalty_amount' => $penalty,
                'total_with_penalty' => $this->client_total + $penalty,
            ]);
        }
    }

    /**
     * Get the count of billable workers (excludes workers with ended contracts who only have OT)
     * Workers are excluded from billing if:
     * - Their contract ended before the payroll period AND
     * - They only have OT hours (no basic salary)
     */
    public function getBillableWorkersCountAttribute(): int
    {
        // Get all workers for this submission
        $workers = $this->workers()->get();

        // Count billable workers
        $billableCount = 0;
        foreach ($workers as $worker) {
            // If worker is not excluded from billing, count them
            if (! $worker->isExcludedFromBilling()) {
                $billableCount++;
            }
        }

        return $billableCount;
    }

    /**
     * Calculate service charge (RM 200 per billable worker)
     * Excludes workers with ended contracts who only have OT hours
     * Returns 0 if contractor is exempt from service charges
     */
    public function getCalculatedServiceChargeAttribute(): float
    {
        // Check if contractor is exempt from service charges
        $configService = app(\App\Services\ContractorConfigurationService::class);

        if ($configService->isServiceChargeExempt($this->contractor_clab_no)) {
            return 0;
        }

        return $this->billable_workers_count * 200;
    }

    /**
     * Calculate SST (8% of service charge)
     */
    public function getCalculatedSstAttribute(): float
    {
        return $this->calculated_service_charge * 0.08;
    }

    /**
     * Get client total (payroll + service charge + SST)
     * This is what the client actually pays
     */
    public function getClientTotalAttribute(): float
    {
        // Use admin_final_amount if reviewed, otherwise use grand_total (legacy)
        $payrollAmount = $this->admin_final_amount ?? $this->grand_total;

        return $payrollAmount + $this->calculated_service_charge + $this->calculated_sst;
    }

    /**
     * Get the total amount including penalty (dynamic calculation)
     * Returns base amount without penalty if contractor is exempt
     */
    public function getTotalDueAttribute(): float
    {
        $baseAmount = $this->client_total;

        // Skip penalty if contractor is exempt
        if ($this->isPenaltyExempt()) {
            return $baseAmount;
        }

        // If penalty was already applied and saved, use it
        if ($this->has_penalty && $this->penalty_amount > 0) {
            return $baseAmount + $this->penalty_amount;
        }

        // Otherwise, calculate dynamically if overdue
        if ($this->isOverdue() && $this->status !== 'paid') {
            $penalty = $baseAmount * 0.08;

            return $baseAmount + $penalty;
        }

        return $baseAmount;
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
     * Only considers submissions overdue AFTER the end of deadline day (next day)
     * Example: If deadline is Nov 30, only overdue from Dec 1 onwards
     */
    public function scopeOverdue($query)
    {
        return $query->whereDate('payment_deadline', '<', now()->startOfDay())
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
     * Generate tax invoice number for this submission
     * Format: OR-P-YYMMNNNN (e.g., OR-P-26010001 for January 2026, invoice #1)
     * - OR-P: Prefix
     * - YY: Year (last 2 digits)
     * - MM: Month (2 digits)
     * - NNNN: Running number (4 digits)
     */
    public function generateTaxInvoiceNumber(): string
    {
        // Return existing number if already generated
        if ($this->tax_invoice_number) {
            return $this->tax_invoice_number;
        }

        // Use database transaction with locking to prevent race conditions
        return \DB::transaction(function () {
            // Re-check within transaction (double-check locking pattern)
            $freshSelf = static::lockForUpdate()->find($this->id);
            if ($freshSelf->tax_invoice_number) {
                return $freshSelf->tax_invoice_number;
            }

            // Get the latest tax invoice number for this year and month
            $year = $this->year;
            $month = $this->month;
            $yearShort = substr((string) $year, -2); // Last 2 digits of year
            $monthPadded = str_pad($month, 2, '0', STR_PAD_LEFT); // 2-digit month

            // Find the latest invoice number for this year-month period with lock
            $latestInvoice = static::whereNotNull('tax_invoice_number')
                ->where('year', $year)
                ->where('month', $month)
                ->where('tax_invoice_number', 'LIKE', 'OR-P-'.$yearShort.$monthPadded.'%')
                ->orderByRaw("CAST(SUBSTRING(tax_invoice_number, -4) AS UNSIGNED) DESC")
                ->lockForUpdate()
                ->first();

            if ($latestInvoice && $latestInvoice->tax_invoice_number) {
                // Extract the running number from format OR-P-YYMMNNNN
                $invoiceNumber = $latestInvoice->tax_invoice_number;
                // Get last 4 digits (running number)
                $lastNumber = (int) substr($invoiceNumber, -4);
                $nextNumber = $lastNumber + 1;
            } else {
                $nextNumber = 1;
            }

            // Format: OR-P-YYMMNNNN
            $taxInvoiceNumber = sprintf('OR-P-%s%s%04d', $yearShort, $monthPadded, $nextNumber);

            // Save the tax invoice number and generation timestamp
            $freshSelf->update([
                'tax_invoice_number' => $taxInvoiceNumber,
                'tax_invoice_generated_at' => now(),
            ]);

            // Update current instance as well
            $this->tax_invoice_number = $taxInvoiceNumber;
            $this->tax_invoice_generated_at = now();

            return $taxInvoiceNumber;
        });
    }

    /**
     * Check if tax invoice has been generated
     */
    public function hasTaxInvoice(): bool
    {
        return ! empty($this->tax_invoice_number) && ! empty($this->tax_invoice_generated_at);
    }

    /**
     * Calculate grand total (total amount + service charge + SST)
     *
     * @deprecated Use client_total accessor instead
     */
    public function calculateGrandTotal(): float
    {
        // Use admin_final_amount instead of deprecated total_amount
        return ($this->admin_final_amount ?? 0) + $this->calculateServiceCharge() + $this->calculateSST();
    }

    /**
     * Get formatted month/year
     */
    public function getMonthYearAttribute(): string
    {
        return Carbon::create($this->year, $this->month, 1)->format('F Y');
    }

    /**
     * Check if submission has been reviewed by admin
     */
    public function hasAdminReview(): bool
    {
        // Check for actual admin review indicators, not just admin_final_amount
        // because admin_final_amount is set automatically during client submission
        return ! is_null($this->admin_reviewed_at) || ! is_null($this->admin_reviewed_by);
    }

    /**
     * Check if submission has a breakdown file attached
     */
    public function hasBreakdownFile(): bool
    {
        return ! is_null($this->breakdown_file_path);
    }

    /**
     * Check if submission can be reviewed by admin
     */
    public function canBeReviewed(): bool
    {
        return in_array($this->status, ['submitted']);
    }

    /**
     * Check if payment can be created for this submission
     * Allows payment for reviewed submissions that are not yet paid
     */
    public function canCreatePayment(): bool
    {
        // Must have admin review (final amount calculated)
        if (! $this->hasAdminReview()) {
            return false;
        }

        // Allow payment for any unpaid status after admin review
        return in_array($this->status, ['approved', 'submitted', 'pending_payment', 'overdue']);
    }

    /**
     * Get the URL for downloading the breakdown file
     */
    public function getBreakdownFileUrl(): ?string
    {
        if (! $this->hasBreakdownFile()) {
            return null;
        }

        return route('payroll.breakdown.download', $this->id);
    }

    /**
     * Check if submission has a payslip file attached
     */
    public function hasPayslipFile(): bool
    {
        return ! is_null($this->payslip_file_path);
    }

    /**
     * Get the URL for downloading the payslip file
     */
    public function getPayslipFileUrl(): ?string
    {
        if (! $this->hasPayslipFile()) {
            return null;
        }

        return route('payroll.payslip.download', $this->id);
    }

    /**
     * Scope to filter submissions awaiting admin review
     */
    public function scopeAwaitingReview($query)
    {
        return $query->where('status', 'submitted');
    }

    /**
     * Scope to filter approved submissions
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }
}
