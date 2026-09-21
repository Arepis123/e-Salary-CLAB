<?php

namespace App\Models;

use App\Casts\SafeDate;
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
 *
 * Contract end dates: `con_end` is the originally agreed end date. When a
 * contract is terminated early — typically a legal local transfer of the
 * worker to another contractor — the managing system writes the real end date
 * to `con_end_new`. Always decide eligibility, billing and proration from the
 * effective end date — `$contract->effective_end` in PHP, the endsOnOrAfter() /
 * endsOnOrBefore() / active() scopes or effectiveEndSql() in queries — never
 * from `con_end` alone, or the previous contractor keeps being billed after the
 * worker has left.
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
        'con_end_new',
        'con_created_by',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'con_start' => SafeDate::class,
        'con_end' => SafeDate::class,
        'con_end_new' => SafeDate::class,
        'con_created_at' => SafeDate::class.':Y-m-d H:i:s',
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
     * SQL expression for the contract's effective end date.
     *
     * Use this in raw query fragments (whereRaw/orderByRaw/selectRaw) so the
     * database applies the same early-termination rule as effectiveEnd().
     * Pass $table to qualify the columns when the query joins another table.
     */
    public static function effectiveEndSql(?string $table = null): string
    {
        $prefix = $table ? $table.'.' : '';

        return "COALESCE({$prefix}con_end_new, {$prefix}con_end)";
    }

    /**
     * The date this contract actually ends: the early-termination date when the
     * contract was cut short, otherwise its original end date.
     */
    public function getEffectiveEndAttribute()
    {
        return $this->con_end_new ?? $this->con_end;
    }

    /**
     * Whether this contract was terminated before its original end date.
     */
    public function endedEarly(): bool
    {
        return $this->con_end_new !== null;
    }

    /**
     * Normalise a date argument for the raw effective-end comparisons.
     */
    protected static function asDateString($date): string
    {
        return $date instanceof \DateTimeInterface ? $date->format('Y-m-d') : (string) $date;
    }

    /**
     * Scope a query to only include active contracts.
     * Active = effective end date is in the future or today
     */
    public function scopeActive($query)
    {
        return $query->endsOnOrAfter(now()->toDateString());
    }

    /**
     * Scope a query to only include expired contracts.
     */
    public function scopeExpired($query)
    {
        return $query->whereRaw(static::effectiveEndSql().' < ?', [now()->toDateString()]);
    }

    /**
     * Scope a query to contracts still running on or after the given date.
     */
    public function scopeEndsOnOrAfter($query, $date)
    {
        return $query->whereRaw(static::effectiveEndSql().' >= ?', [static::asDateString($date)]);
    }

    /**
     * Scope a query to contracts that have ended on or before the given date.
     */
    public function scopeEndsOnOrBefore($query, $date)
    {
        return $query->whereRaw(static::effectiveEndSql().' <= ?', [static::asDateString($date)]);
    }

    /**
     * Order a query by the effective end date.
     */
    public function scopeOrderByEffectiveEnd($query, string $direction = 'asc')
    {
        return $query->orderByRaw(static::effectiveEndSql().' '.(strtolower($direction) === 'desc' ? 'desc' : 'asc'));
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
        $end = $this->effective_end;

        if (! $end) {
            return false;
        }

        return $end->isFuture() || $end->isToday();
    }

    /**
     * Check if contract is expired
     */
    public function isExpired(): bool
    {
        $end = $this->effective_end;

        return $end && $end->isPast() && ! $end->isToday();
    }

    /**
     * Get days remaining in contract
     */
    public function daysRemaining(): int
    {
        if ($this->isExpired()) {
            return 0;
        }

        return now()->diffInDays($this->effective_end, false);
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
     * Accessor for end date (the effective one, so callers cannot miss an
     * early termination)
     */
    public function getEndDateAttribute()
    {
        return $this->effective_end;
    }

    /**
     * Accessor for period
     */
    public function getPeriodAttribute()
    {
        return $this->con_period;
    }
}
