<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Worker Model
 *
 * READ-ONLY MODEL
 * This table is from the second database (worker_db) managed by another system.
 * This payroll system only reads worker data. Do not create, update, or delete records.
 */
class Worker extends Model
{
    /**
     * The connection name for the model.
     * This points to the second database (worker_db)
     */
    protected $connection = 'worker_db';

    /**
     * The table associated with the model.
     */
    protected $table = 'workers';

    /**
     * The primary key for the model.
     */
    protected $primaryKey = 'wkr_id';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'wkr_passno',
        'wkr_name',
        'wkr_email',
        'wkr_dob',
        'wkr_wtrade',
        'wkr_salary',
        'wkr_status',
        'wkr_tel',
        'wkr_address1',
        'wkr_address2',
        'wkr_address3',
        'wkr_pcode',
        'wkr_state',
        'wkr_currentemp',
        'wkr_nationality',
        'wkr_gender',
        'wkr_passexp',
        'wkr_permitexp',
        'wkr_next_of_kin',
        'wkr_relationship',
        'wkr_homeaddr',
        'wkr_sosco_id',
        'wkr_kwsp',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'wkr_salary' => 'decimal:2',
        'wkr_dob' => 'date',
        'wkr_passexp' => 'date',
        'wkr_permitexp' => 'date',
        'wkr_contractexp' => 'date',
        'wkr_entrydate' => 'date',
        'wkr_createddate' => 'datetime',
        'wkr_modifieddate' => 'datetime',
    ];

    /**
     * Get the cache key for this worker
     */
    public function getCacheKey(): string
    {
        return "worker:{$this->wkr_id}";
    }

    /**
     * Scope a query to only include active workers.
     * Status: 1 = Active, 2 = Inactive, etc.
     */
    public function scopeActive($query)
    {
        return $query->where('wkr_status', '2');
    }

    /**
     * Scope a query to only include workers by position/trade.
     */
    public function scopePosition($query, string $position)
    {
        return $query->where('wkr_wtrade', 'LIKE', "%{$position}%");
    }

    /**
     * Get the contractor that owns this worker (current employer)
     */
    public function contractor()
    {
        return $this->belongsTo(Contractor::class, 'wkr_currentemp', 'ctr_clab_no');
    }

    /**
     * Get the country/nationality of this worker
     */
    public function country()
    {
        return $this->belongsTo(Country::class, 'wkr_country', 'cty_id');
    }

    /**
     * Get the work trade/position of this worker
     */
    public function workTrade()
    {
        return $this->belongsTo(WorkTrade::class, 'wkr_wtrade', 'trade_id');
    }

    /**
     * Get contract worker records for this worker
     */
    public function contracts()
    {
        return $this->hasMany(ContractWorker::class, 'con_wkr_id', 'wkr_id');
    }

    /**
     * Get the active contract for this worker
     */
    public function activeContract()
    {
        return $this->hasOne(ContractWorker::class, 'con_wkr_id', 'wkr_id')
            ->whereDate('con_end', '>=', now()->toDateString())
            ->latest('con_start');
    }

    /**
     * Check if worker has an active contract in the system
     */
    public function hasActiveContract(): bool
    {
        return $this->activeContract()->exists();
    }

    /**
     * Accessor for name
     */
    public function getNameAttribute()
    {
        return $this->wkr_name;
    }

    /**
     * Accessor for IC number (passport number)
     */
    public function getIcNumberAttribute()
    {
        return $this->wkr_passno;
    }

    /**
     * Accessor for position
     */
    public function getPositionAttribute()
    {
        return $this->workTrade?->trade_desc ?? $this->wkr_wtrade;
    }

    /**
     * Accessor for basic salary
     */
    public function getBasicSalaryAttribute()
    {
        return $this->wkr_salary;
    }

    /**
     * Accessor for status
     */
    public function getStatusAttribute()
    {
        return $this->wkr_status == '2' ? 'active' : 'inactive';
    }

    /**
     * Accessor for phone
     */
    public function getPhoneAttribute()
    {
        return $this->wkr_tel;
    }

    /**
     * Accessor for email
     */
    public function getEmailAttribute()
    {
        return $this->wkr_email;
    }

    /**
     * Accessor for contractor ID
     */
    public function getContractorIdAttribute()
    {
        return $this->wkr_currentemp;
    }
}
