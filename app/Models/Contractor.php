<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Contractor Model
 *
 * READ-ONLY MODEL
 * This table is from the second database (worker_db) managed by another system.
 * This payroll system only reads contractor data. Do not create, update, or delete records.
 */
class Contractor extends Model
{
    /**
     * The connection name for the model.
     * This points to the second database (worker_db)
     */
    protected $connection = 'worker_db';

    /**
     * The table associated with the model.
     */
    protected $table = 'contractors';

    /**
     * The primary key for the model.
     */
    protected $primaryKey = 'ctr_clab_no';

    /**
     * Indicates if the IDs are auto-incrementing.
     */
    public $incrementing = false;

    /**
     * The data type of the primary key.
     */
    protected $keyType = 'string';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'ctr_clab_no',
        'ctr_comp_name',
        'ctr_comp_regno',
        'ctr_contact_name',
        'ctr_contact_mobileno',
        'ctr_telno',
        'ctr_email',
        'ctr_addr1',
        'ctr_addr2',
        'ctr_addr3',
        'ctr_pcode',
        'ctr_state',
        'ctr_status',
        'ctr_cidb_regno',
        'ctr_grade',
        'ctr_datereg',
        'ctr_clabexp_date',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'ctr_datereg' => 'date',
        'ctr_validdate' => 'date',
        'ctr_clabexp_date' => 'date',
        'ctr_cidbexp_date' => 'date',
    ];

    /**
     * Get the cache key for this contractor
     */
    public function getCacheKey(): string
    {
        return "contractor:{$this->ctr_clab_no}";
    }

    /**
     * Scope a query to only include active contractors.
     * Status: 1 = Active, 2 = Inactive
     */
    public function scopeActive($query)
    {
        return $query->where('ctr_status', '2');
    }

    /**
     * Get all workers associated with this contractor (from worker_db)
     * This returns ALL workers currently employed, not just contracted ones
     */
    public function workers(): HasMany
    {
        return $this->hasMany(Worker::class, 'wkr_currentemp', 'ctr_clab_no');
    }

    /**
     * Get contracted workers through contract_worker table
     * This returns ONLY workers with active contracts in the payroll system
     */
    public function contractedWorkers()
    {
        return $this->hasManyThrough(
            Worker::class,
            ContractWorker::class,
            'con_ctr_clab_no', // Foreign key on contract_worker table
            'wkr_id',          // Foreign key on workers table
            'ctr_clab_no',     // Local key on contractors table
            'con_wkr_id'       // Local key on contract_worker table
        );
    }

    /**
     * Get contract worker records for this contractor
     */
    public function contracts(): HasMany
    {
        return $this->hasMany(ContractWorker::class, 'con_ctr_clab_no', 'ctr_clab_no');
    }

    /**
     * Get only active contracted workers
     */
    public function activeContractedWorkers()
    {
        return $this->hasManyThrough(
            Worker::class,
            ContractWorker::class,
            'con_ctr_clab_no',
            'wkr_id',
            'ctr_clab_no',
            'con_wkr_id'
        )->whereDate('con_end', '>=', now());
    }

    /**
     * Accessor for company name
     */
    public function getCompanyNameAttribute()
    {
        return $this->ctr_comp_name;
    }

    /**
     * Accessor for registration number
     */
    public function getRegistrationNumberAttribute()
    {
        return $this->ctr_comp_regno;
    }

    /**
     * Accessor for contact person
     */
    public function getContactPersonAttribute()
    {
        return $this->ctr_contact_name;
    }

    /**
     * Accessor for phone
     */
    public function getPhoneAttribute()
    {
        return $this->ctr_contact_mobileno ?? $this->ctr_telno;
    }

    /**
     * Accessor for email
     */
    public function getEmailAttribute()
    {
        return $this->ctr_email;
    }

    /**
     * Accessor for status
     */
    public function getStatusAttribute()
    {
        return $this->ctr_status == '2' ? 'active' : 'inactive';
    }

    /**
     * The trading name reduced to something that can sit in a filename.
     *
     * What identifies a client at a glance is the trading name, so the legal
     * suffix (Sdn. Bhd., Berhad, Enterprise and friends) is dropped, as is the
     * "(M)" / "(Malaysia)" registration flourish that would otherwise leave a
     * stray "_M_" behind. Whatever survives is collapsed to underscore-joined
     * alphanumerics.
     *
     * "ADVATIS TECHNOLOGIES (M) SDN BHD" becomes "ADVATIS_TECHNOLOGIES".
     *
     * Returns an empty string when nothing usable is left; callers decide what
     * to fall back to (usually the CLAB number).
     */
    public static function fileNameSlug(?string $companyName): string
    {
        $shortName = preg_split(
            '/\s*\b(sdn\.?\s*bhd\.?|sendirian\s+berhad|berhad|bhd\.?|enterprise)\b/i',
            (string) $companyName
        )[0];

        $shortName = preg_replace('/\s*\((?:m|malaysia)\)/i', '', $shortName);

        return trim(preg_replace('/[^A-Za-z0-9]+/', '_', $shortName), '_');
    }

    /**
     * This contractor's own filename slug. See fileNameSlug().
     */
    public function getFileNameSlugAttribute(): string
    {
        return static::fileNameSlug($this->ctr_comp_name);
    }
}
