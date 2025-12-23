<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class ContractorWindowSetting extends Model
{
    protected $fillable = [
        'contractor_clab_no',
        'contractor_name',
        'is_window_open',
        'window_opened_at',
        'window_closed_at',
        'custom_start_date',
        'custom_end_date',
        'last_changed_by',
        'last_change_remarks',
    ];

    protected $casts = [
        'is_window_open' => 'boolean',
        'window_opened_at' => 'datetime',
        'window_closed_at' => 'datetime',
        'custom_start_date' => 'date',
        'custom_end_date' => 'date',
    ];

    // Relationships
    public function lastChangedBy()
    {
        return $this->belongsTo(User::class, 'last_changed_by');
    }

    public function logs()
    {
        return $this->hasMany(ContractorWindowLog::class, 'contractor_clab_no', 'contractor_clab_no');
    }

    // Scopes
    public function scopeWindowOpen($query)
    {
        return $query->where('is_window_open', true);
    }

    public function scopeWindowClosed($query)
    {
        return $query->where('is_window_open', false);
    }

    // Accessors
    public function getStatusBadgeColorAttribute(): string
    {
        return $this->is_window_open ? 'green' : 'red';
    }

    public function getStatusTextAttribute(): string
    {
        return $this->is_window_open ? 'Open' : 'Closed';
    }

    // Clear cache when settings change
    protected static function booted()
    {
        static::saved(function ($setting) {
            Cache::forget("contractor_window:{$setting->contractor_clab_no}");
        });
    }
}
