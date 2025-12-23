<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EntryUnlockLog extends Model
{
    protected $fillable = [
        'monthly_ot_entry_id',
        'previous_status',
        'new_status',
        'unlocked_by',
        'unlock_reason',
    ];

    public function monthlyOTEntry()
    {
        return $this->belongsTo(MonthlyOTEntry::class);
    }

    public function unlockedBy()
    {
        return $this->belongsTo(User::class, 'unlocked_by');
    }
}
