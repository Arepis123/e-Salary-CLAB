<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContractorConfiguration extends Model
{
    protected $fillable = [
        'contractor_clab_no',
        'contractor_name',
        'service_charge_exempt',
        'updated_by',
    ];

    protected $casts = [
        'service_charge_exempt' => 'boolean',
    ];

    /**
     * Get the user who last updated this configuration
     */
    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Get enabled deduction templates for this contractor
     */
    public function deductions()
    {
        return $this->belongsToMany(DeductionTemplate::class, 'contractor_deductions')
            ->withPivot('enabled_by', 'enabled_at')
            ->withTimestamps();
    }

    /**
     * Get enabled deductions that should apply in a given month
     */
    public function getDeductionsForMonth(int $month)
    {
        return $this->deductions()
            ->active()
            ->get()
            ->filter(function ($deduction) use ($month) {
                return $deduction->shouldApplyInMonth($month);
            });
    }
}
