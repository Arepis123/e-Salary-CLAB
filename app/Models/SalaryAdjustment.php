<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalaryAdjustment extends Model
{
    protected $fillable = [
        'worker_id',
        'worker_name',
        'worker_passport',
        'old_salary',
        'new_salary',
        'adjusted_by',
        'remarks',
    ];

    protected $casts = [
        'old_salary' => 'decimal:2',
        'new_salary' => 'decimal:2',
    ];

    /**
     * Get the user who made the adjustment
     */
    public function adjustedBy()
    {
        return $this->belongsTo(User::class, 'adjusted_by');
    }

    /**
     * Get the salary difference
     */
    public function getDifferenceAttribute(): float
    {
        return $this->new_salary - $this->old_salary;
    }

    /**
     * Get the percentage change
     */
    public function getPercentageChangeAttribute(): float
    {
        if ($this->old_salary == 0) {
            return 0;
        }

        return (($this->new_salary - $this->old_salary) / $this->old_salary) * 100;
    }
}
