<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * PayrollException Model
 *
 * Temporarily pauses a worker from payroll for specific months (e.g. worker on
 * long leave). Unlike InactiveWorker, the worker stays active and returns to
 * payroll automatically once the excepted months have passed.
 */
class PayrollException extends Model
{
    public const MAX_MONTHS = 3;

    protected $fillable = [
        'worker_id',
        'worker_name',
        'worker_passport',
        'contractor_clab_no',
        'month',
        'year',
        'remarks',
        'created_by',
    ];

    protected $casts = [
        'month' => 'integer',
        'year' => 'integer',
    ];

    /**
     * Get the user who created this exception
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get worker IDs excepted from payroll for the given month
     */
    public static function getExceptedWorkerIds(int $month, int $year, ?string $clabNo = null): array
    {
        return self::where('month', $month)
            ->where('year', $year)
            ->when($clabNo, fn ($q) => $q->where('contractor_clab_no', $clabNo))
            ->pluck('worker_id')
            ->toArray();
    }

    /**
     * Upcoming excepted months per worker, e.g. ['123' => ['Oct 2026', 'Nov 2026']]
     */
    public static function upcomingMonthsByWorker(?string $clabNo = null): array
    {
        return self::upcoming()
            ->when($clabNo, fn ($q) => $q->where('contractor_clab_no', $clabNo))
            ->orderBy('year')
            ->orderBy('month')
            ->get()
            ->groupBy('worker_id')
            ->map(fn ($rows) => $rows->map->period_label->all())
            ->all();
    }

    /**
     * Payroll months (as 'Y-m' keys) where the client already entered OT hours or
     * transactions for this worker. OT entered in month M-1 is paid in payroll M,
     * so MonthlyOTEntry.submission_month is the payroll month.
     */
    public static function monthsWithOTData(string $workerId): array
    {
        return MonthlyOTEntry::where('worker_id', $workerId)
            ->where(function ($q) {
                $q->where('ot_normal_hours', '>', 0)
                    ->orWhere('ot_rest_hours', '>', 0)
                    ->orWhere('ot_public_hours', '>', 0)
                    ->orWhereHas('transactions');
            })
            ->get(['submission_month', 'submission_year'])
            ->toBase()
            ->map(fn ($e) => sprintf('%04d-%02d', $e->submission_year, $e->submission_month))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Upcoming exceptions where the client still has OT hours or transactions for
     * that payroll month (e.g. entered before the exception existed), keyed by
     * worker, e.g. ['123' => ['Oct 2026']]
     */
    public static function upcomingConflictsByWorker(): array
    {
        return self::upcoming()
            ->whereExists(function ($q) {
                $q->selectRaw('1')
                    ->from('monthly_ot_entries')
                    ->whereColumn('monthly_ot_entries.worker_id', 'payroll_exceptions.worker_id')
                    ->whereColumn('monthly_ot_entries.submission_month', 'payroll_exceptions.month')
                    ->whereColumn('monthly_ot_entries.submission_year', 'payroll_exceptions.year')
                    ->where(function ($q) {
                        $q->where('monthly_ot_entries.ot_normal_hours', '>', 0)
                            ->orWhere('monthly_ot_entries.ot_rest_hours', '>', 0)
                            ->orWhere('monthly_ot_entries.ot_public_hours', '>', 0)
                            ->orWhereExists(fn ($t) => $t->selectRaw('1')
                                ->from('monthly_ot_entry_transactions')
                                ->whereColumn('monthly_ot_entry_transactions.monthly_ot_entry_id', 'monthly_ot_entries.id'));
                    });
            })
            ->orderBy('year')
            ->orderBy('month')
            ->get()
            ->groupBy('worker_id')
            ->map(fn ($rows) => $rows->map->period_label->all())
            ->all();
    }

    /**
     * Check if a worker is excepted from the given payroll month
     */
    public static function isExcepted(string $workerId, int $month, int $year): bool
    {
        return self::where('worker_id', $workerId)
            ->where('month', $month)
            ->where('year', $year)
            ->exists();
    }

    /**
     * Scope to exceptions for the current payroll month onwards
     */
    public function scopeUpcoming($query)
    {
        $now = now();

        return $query->where(function ($q) use ($now) {
            $q->where('year', '>', $now->year)
                ->orWhere(fn ($q) => $q->where('year', $now->year)->where('month', '>=', $now->month));
        });
    }

    /**
     * Human-readable payroll month, e.g. "Oct 2026"
     */
    public function getPeriodLabelAttribute(): string
    {
        return Carbon::create($this->year, $this->month, 1)->format('M Y');
    }
}
