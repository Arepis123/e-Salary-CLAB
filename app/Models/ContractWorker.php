<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ContractWorker Model
 *
 * READ-ONLY MODEL
 * This table is managed by another system. This payroll system only reads from it.
 * Do not create, update, or delete records through this model.
 *
 * Purpose: Defines which contractor-worker pairs are active in the payroll system
 */
class ContractWorker extends Model
{
    /**
     * The connection name for the model.
     * This points to the second database (worker_db)
     */
    protected $connection = 'worker_db';

    /**
     * The table associated with the model.
     */
    protected $table = 'contract_worker';

    /**
     * The primary key for the model.
     */
    protected $primaryKey = 'con_id';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'con_ctr_clab_no',
        'con_wkr_id',
        'con_wkr_passno',
        'con_period',
        'con_start',
        'con_end',
        'con_created_by',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'con_start' => 'date',
        'con_end' => 'date',
        'con_created_at' => 'datetime',
    ];

    /**
     * Custom timestamps column names
     */
    const CREATED_AT = 'con_created_at';

    const UPDATED_AT = null; // No updated_at column

    /**
     * Get the contractor associated with this contract
     */
    public function contractor(): BelongsTo
    {
        return $this->belongsTo(Contractor::class, 'con_ctr_clab_no', 'ctr_clab_no');
    }

    /**
     * Get the worker associated with this contract
     */
    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class, 'con_wkr_id', 'wkr_id');
    }

    /**
     * Scope a query to only include active contracts.
     * Active = contract end date is in the future or today
     */
    public function scopeActive($query)
    {
        return $query->where('con_end', '>=', now()->toDateString());
    }

    /**
     * Scope a query to only include expired contracts.
     */
    public function scopeExpired($query)
    {
        return $query->where('con_end', '<', now()->toDateString());
    }

    /**
     * Scope a query to filter by contractor CLAB number
     */
    public function scopeByContractor($query, string $clabNo)
    {
        return $query->where('con_ctr_clab_no', $clabNo);
    }

    /**
     * Scope a query to filter by worker ID
     */
    public function scopeByWorker($query, int $workerId)
    {
        return $query->where('con_wkr_id', $workerId);
    }

    /**
     * Check if contract is currently active
     */
    public function isActive(): bool
    {
        if (! $this->con_end) {
            return false;
        }

        return $this->con_end->isFuture() || $this->con_end->isToday();
    }

    /**
     * Check if contract is expired
     */
    public function isExpired(): bool
    {
        return $this->con_end && $this->con_end->isPast() && ! $this->con_end->isToday();
    }

    /**
     * Get days remaining in contract
     */
    public function daysRemaining(): int
    {
        if ($this->isExpired()) {
            return 0;
        }

        return now()->diffInDays($this->con_end, false);
    }

    /**
     * Get contract duration in months
     */
    public function getDurationInMonths(): int
    {
        return $this->con_period ?? 0;
    }

    /**
     * Accessor for contractor ID
     */
    public function getContractorIdAttribute()
    {
        return $this->con_ctr_clab_no;
    }

    /**
     * Accessor for worker ID
     */
    public function getWorkerIdAttribute()
    {
        return $this->con_wkr_id;
    }

    /**
     * Accessor for start date
     */
    public function getStartDateAttribute()
    {
        return $this->con_start;
    }

    /**
     * Accessor for end date
     */
    public function getEndDateAttribute()
    {
        return $this->con_end;
    }

    /**
     * Accessor for period
     */
    public function getPeriodAttribute()
    {
        return $this->con_period;
    }
}
