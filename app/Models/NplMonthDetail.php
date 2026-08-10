<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Per-month breakdown of a No-Pay Leave (NPL) transaction.
 *
 * Each row charges NPL days against one specific month, using that month's own
 * daily rate (monthly salary / actual days in that month). The amounts are
 * snapshots taken at save time, not recomputed on read.
 */
class NplMonthDetail extends Model
{
    protected $table = 'npl_month_details';

    protected $fillable = [
        'npl_year',
        'npl_month',
        'days_in_month',
        'npl_days',
        'monthly_salary',
        'daily_rate',
        'amount',
    ];

    protected $casts = [
        'npl_year' => 'integer',
        'npl_month' => 'integer',
        'days_in_month' => 'integer',
        'npl_days' => 'decimal:1',
        'monthly_salary' => 'decimal:2',
        'daily_rate' => 'decimal:2',
        'amount' => 'decimal:2',
    ];

    /**
     * The transaction this breakdown belongs to (a MonthlyOTEntryTransaction
     * during client entry, or a PayrollWorkerTransaction once submitted).
     */
    public function nplable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * "Februari 2026" style label for the month this row charges.
     */
    public function getMonthLabelAttribute(): string
    {
        return Carbon::create($this->npl_year, $this->npl_month, 1)->format('F Y');
    }

    /**
     * "Feb 2026" style label, for table cells where the full month name is too wide.
     */
    public function getShortMonthLabelAttribute(): string
    {
        return Carbon::create($this->npl_year, $this->npl_month, 1)->format('M Y');
    }

    /**
     * Sortable YYYY-MM key.
     */
    public function getMonthKeyAttribute(): string
    {
        return sprintf('%04d-%02d', $this->npl_year, $this->npl_month);
    }
}
