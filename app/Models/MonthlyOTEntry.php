<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class MonthlyOTEntry extends Model
{
    protected $table = 'monthly_ot_entries';

    protected $fillable = [
        'contractor_clab_no',
        'worker_id',
        'worker_name',
        'worker_passport',
        'entry_month',
        'entry_year',
        'submission_month',
        'submission_year',
        'ot_normal_hours',
        'ot_rest_hours',
        'ot_public_hours',
        'status',
        'submitted_at',
        'locked_at',
    ];

    protected $casts = [
        'ot_normal_hours' => 'decimal:2',
        'ot_rest_hours' => 'decimal:2',
        'ot_public_hours' => 'decimal:2',
        'submitted_at' => 'datetime',
        'locked_at' => 'datetime',
    ];

    /**
     * Get the transactions for this OT entry
     */
    public function transactions()
    {
        return $this->hasMany(MonthlyOTEntryTransaction::class, 'monthly_ot_entry_id');
    }

    /**
     * Get the entry period as formatted string (e.g., "November 2025")
     */
    public function getEntryPeriodAttribute(): string
    {
        return Carbon::create($this->entry_year, $this->entry_month, 1)->format('F Y');
    }

    /**
     * Get the submission period as formatted string (e.g., "December 2025")
     */
    public function getSubmissionPeriodAttribute(): string
    {
        return Carbon::create($this->submission_year, $this->submission_month, 1)->format('F Y');
    }

    /**
     * Check if this entry is locked (after 15th or manually locked)
     */
    public function isLocked(): bool
    {
        return $this->status === 'locked' || ! is_null($this->locked_at);
    }

    /**
     * Check if this entry has been submitted
     */
    public function isSubmitted(): bool
    {
        return $this->status === 'submitted' || $this->status === 'locked';
    }

    /**
     * Check if this entry is still in draft status
     */
    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    /**
     * Get total OT hours
     */
    public function getTotalOTHoursAttribute(): float
    {
        return $this->ot_normal_hours + $this->ot_rest_hours + $this->ot_public_hours;
    }

    /**
     * Scope: Get entries for specific contractor and entry period
     */
    public function scopeForContractorPeriod($query, string $clabNo, int $month, int $year)
    {
        return $query->where('contractor_clab_no', $clabNo)
            ->where('entry_month', $month)
            ->where('entry_year', $year);
    }

    /**
     * Scope: Get only draft entries
     */
    public function scopeDrafts($query)
    {
        return $query->where('status', 'draft');
    }

    /**
     * Scope: Get submitted entries (submitted or locked)
     */
    public function scopeSubmitted($query)
    {
        return $query->whereIn('status', ['submitted', 'locked']);
    }
}
