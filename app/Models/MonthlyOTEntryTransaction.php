<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MonthlyOTEntryTransaction extends Model
{
    protected $table = 'monthly_ot_entry_transactions';

    protected $fillable = [
        'monthly_ot_entry_id',
        'type',
        'amount',
        'remarks',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    /**
     * Get the monthly OT entry that owns this transaction
     */
    public function monthlyOTEntry()
    {
        return $this->belongsTo(MonthlyOTEntry::class, 'monthly_ot_entry_id');
    }

    /**
     * Check if this is a deduction type transaction
     */
    public function isDeduction(): bool
    {
        return in_array($this->type, ['advance_payment', 'deduction', 'npl']);
    }

    /**
     * Check if this is an earning type transaction
     */
    public function isEarning(): bool
    {
        return $this->type === 'allowance';
    }
}
