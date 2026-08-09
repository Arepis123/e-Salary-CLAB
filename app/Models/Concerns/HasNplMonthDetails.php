<?php

namespace App\Models\Concerns;

use App\Models\NplMonthDetail;
use App\Services\NplCalculatorService;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Shared NPL behaviour for the two transaction models.
 *
 * A No-Pay Leave transaction stores its day count in `amount` and, since the
 * per-month enhancement, an `nplDetails` breakdown charging those days against
 * the months the leave was actually taken.
 *
 * Transactions saved before that enhancement have no breakdown and are still
 * valued at the old flat "salary / 26" rate.
 */
trait HasNplMonthDetails
{
    /**
     * Per-month NPL breakdown, oldest month first.
     */
    public function nplDetails(): MorphMany
    {
        return $this->morphMany(NplMonthDetail::class, 'nplable')
            ->orderBy('npl_year')
            ->orderBy('npl_month');
    }

    /**
     * Whether this transaction carries a per-month breakdown.
     */
    public function hasNplBreakdown(): bool
    {
        return $this->type === 'npl' && $this->nplDetails()->exists();
    }

    /**
     * Ringgit value of this NPL transaction.
     *
     * Uses the stored per-month breakdown when present; otherwise falls back to
     * the legacy flat divisor so historical payroll totals stay unchanged.
     */
    public function nplAmount(float $monthlySalary): float
    {
        if ($this->type !== 'npl') {
            return 0.0;
        }

        $details = $this->relationLoaded('nplDetails')
            ? $this->nplDetails
            : $this->nplDetails()->get();

        if ($details->isNotEmpty()) {
            return round((float) $details->sum('amount'), 2);
        }

        return round(
            ($monthlySalary / NplCalculatorService::LEGACY_DIVISOR) * (float) $this->amount,
            2
        );
    }
}
