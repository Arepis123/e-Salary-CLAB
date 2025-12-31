<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeductionTemplate extends Model
{
    protected $fillable = [
        'name',
        'description',
        'amount',
        'apply_months',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'apply_months' => 'array', // JSON array [1-12]
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
     * Check if this deduction should be applied for a given month
     */
    public function shouldApplyInMonth(int $month): bool
    {
        return $this->is_active && in_array($month, $this->apply_months ?? []);
    }

    /**
     * Scope to get only active templates
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
