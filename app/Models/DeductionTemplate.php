<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeductionTemplate extends Model
{
    protected $fillable = [
        'name',
        'description',
        'amount',
        'type', // 'contractor' or 'worker'
        'apply_months',
        'apply_periods', // Target payroll periods for worker-level deductions
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'apply_months' => 'array', // JSON array [1-12]
        'apply_periods' => 'array', // JSON array [1, 2, 7, etc.] for target payroll periods
        'is_active' => 'boolean',
    ];

    /**
     * Get the user who created this template
     */
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated this template
     */
    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Get contractors that have this deduction enabled
     */
    public function contractors()
    {
        return $this->belongsToMany(ContractorConfiguration::class, 'contractor_deductions')
            ->withPivot('enabled_by', 'enabled_at')
            ->withTimestamps();
    }

    /**
     * Get worker assignments for worker-level deductions
     */
    public function workerAssignments()
    {
        return $this->hasMany(WorkerDeduction::class);
    }

    /**
     * Check if this is a worker-level deduction
     */
    public function isWorkerLevel(): bool
    {
        return $this->type === 'worker';
    }

    /**
     * Check if this is a contractor-level deduction
     */
    public function isContractorLevel(): bool
    {
        return $this->type === 'contractor';
    }

    /**
     * Check if this deduction should be applied for a given month
     * Returns true if template is active AND (no months specified OR month matches)
     */
    public function shouldApplyInMonth(int $month): bool
    {
        if (! $this->is_active) {
            return false;
        }

        // If no months specified, apply in all months
        if (empty($this->apply_months)) {
            return true;
        }

        return in_array($month, $this->apply_months);
    }

    /**
     * Check if this deduction should be applied for a given payroll period
     * Contractor-level deductions always return true (no period restriction)
     * Worker-level deductions check if the period is in apply_periods array
     */
    public function shouldApplyInPeriod(int $periodCount): bool
    {
        // Contractor-level deductions don't have period requirements
        if ($this->isContractorLevel()) {
            return true;
        }

        // Worker-level: check if period count matches (empty array means all periods)
        return empty($this->apply_periods) || in_array($periodCount, $this->apply_periods ?? []);
    }

    /**
     * Scope to get only active templates
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get only contractor-level templates
     */
    public function scopeContractorLevel($query)
    {
        return $query->where('type', 'contractor');
    }

    /**
     * Scope to get only worker-level templates
     */
    public function scopeWorkerLevel($query)
    {
        return $query->where('type', 'worker');
    }
}
