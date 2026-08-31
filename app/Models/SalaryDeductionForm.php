<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalaryDeductionForm extends Model
{
    protected $table = 'salary_deduction_forms';

    protected $fillable = [
        'contractor_clab_no',
        'entry_month',
        'entry_year',
        'file_path',
        'file_name',
        'file_size',
        'mime_type',
        'workers_count',
        'uploaded_by',
        'uploaded_at',
    ];

    protected $casts = [
        'entry_month' => 'integer',
        'entry_year' => 'integer',
        'file_size' => 'integer',
        'workers_count' => 'integer',
        'uploaded_at' => 'datetime',
    ];

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * The signed form for one contractor's entry period, if any.
     */
    public static function forPeriod(string $clabNo, int $month, int $year): ?self
    {
        return static::where('contractor_clab_no', $clabNo)
            ->where('entry_month', $month)
            ->where('entry_year', $year)
            ->first();
    }

    public function getEntryPeriodAttribute(): string
    {
        return Carbon::create($this->entry_year, $this->entry_month, 1)->format('F Y');
    }

    /**
     * Human-readable file size, e.g. "1.2 MB".
     */
    public function getFileSizeForHumansAttribute(): string
    {
        $bytes = (int) $this->file_size;

        if ($bytes < 1024) {
            return $bytes.' B';
        }

        if ($bytes < 1048576) {
            return round($bytes / 1024, 1).' KB';
        }

        return round($bytes / 1048576, 1).' MB';
    }
}
