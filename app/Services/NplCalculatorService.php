<?php

namespace App\Services;

use App\Models\PayrollWorker;
use Carbon\Carbon;

/**
 * No-Pay Leave (NPL) calculation.
 *
 * NPL is charged against the month the leave was actually taken, using that
 * month's own daily rate:
 *
 *     NPL Deduction = (Monthly Salary / Days in NPL Month) x NPL Days
 *
 * The divisor is the real length of that calendar month (28/29/30/31), so the
 * same single day of leave costs more in February than in March. Deliberately
 * NOT the payroll month: a January payroll run may carry NPL for February.
 *
 * Legacy NPL transactions (those with no per-month breakdown) predate this rule
 * and are still valued at the old flat "salary / 26" rate. See
 * PayrollWorker::getTotalNplAttribute().
 */
class NplCalculatorService
{
    /**
     * Divisor used by NPL transactions recorded before the per-month rule.
     */
    public const LEGACY_DIVISOR = 26;

    /**
     * How many months back a user may charge NPL against.
     */
    public const LOOKBACK_MONTHS = 6;

    /**
     * Fallback monthly salary (statutory minimum for foreign construction
     * workers) when payroll history has nothing to offer.
     */
    public const DEFAULT_MONTHLY_SALARY = 1700.0;

    /**
     * Actual number of days in the given month (handles leap years).
     */
    public function daysInMonth(int $year, int $month): int
    {
        return (int) Carbon::create($year, $month, 1)->daysInMonth;
    }

    /**
     * Daily rate for one NPL month, rounded to sen.
     */
    public function dailyRate(float $monthlySalary, int $year, int $month): float
    {
        $days = $this->daysInMonth($year, $month);

        if ($days <= 0) {
            return 0.0;
        }

        return round($monthlySalary / $days, 2);
    }

    /**
     * Calculate one month's NPL deduction.
     *
     * @return array{npl_year:int, npl_month:int, month_label:string, days_in_month:int, npl_days:float, monthly_salary:float, daily_rate:float, amount:float}
     */
    public function calculateMonth(float $monthlySalary, int $year, int $month, float $nplDays): array
    {
        $daysInMonth = $this->daysInMonth($year, $month);
        $dailyRate = $this->dailyRate($monthlySalary, $year, $month);

        return [
            'npl_year' => $year,
            'npl_month' => $month,
            'month_label' => Carbon::create($year, $month, 1)->format('F Y'),
            'days_in_month' => $daysInMonth,
            'npl_days' => round($nplDays, 1),
            'monthly_salary' => round($monthlySalary, 2),
            'daily_rate' => $dailyRate,
            // Round per month before summing, so the stored per-month figures
            // always add up to the displayed total.
            'amount' => round($dailyRate * $nplDays, 2),
        ];
    }

    /**
     * Calculate a full multi-month NPL transaction.
     *
     * @param  array<string, float|string>  $daysByMonth  keyed "YYYY-MM" => days
     * @return array{rows:array<int, array>, total_days:float, total_amount:float}
     */
    public function calculate(float $monthlySalary, array $daysByMonth): array
    {
        $rows = [];

        ksort($daysByMonth);

        foreach ($daysByMonth as $monthKey => $days) {
            $days = (float) $days;

            if ($days <= 0) {
                continue;
            }

            [$year, $month] = $this->parseMonthKey($monthKey);

            if ($year === null) {
                continue;
            }

            $rows[] = $this->calculateMonth($monthlySalary, $year, $month, $days);
        }

        return [
            'rows' => $rows,
            'total_days' => round(array_sum(array_column($rows, 'npl_days')), 1),
            'total_amount' => round(array_sum(array_column($rows, 'amount')), 2),
        ];
    }

    /**
     * Months a user may charge NPL against: the anchor month itself plus the
     * previous LOOKBACK_MONTHS, newest first.
     *
     * @return array<int, array{key:string, year:int, month:int, label:string, days_in_month:int}>
     */
    public function selectableMonths(?Carbon $anchor = null, ?int $lookback = null): array
    {
        $anchor = ($anchor ?? Carbon::now())->copy()->startOfMonth();
        $lookback = $lookback ?? self::LOOKBACK_MONTHS;

        $months = [];

        for ($i = 0; $i <= $lookback; $i++) {
            $date = $anchor->copy()->subMonths($i);

            $months[] = [
                'key' => $date->format('Y-m'),
                'year' => (int) $date->year,
                'month' => (int) $date->month,
                'label' => $date->format('F Y'),
                'days_in_month' => (int) $date->daysInMonth,
            ];
        }

        return $months;
    }

    /**
     * Resolve a worker's monthly salary from their most recent payroll record,
     * falling back to the statutory minimum.
     */
    public function resolveMonthlySalary(int $workerId, ?string $clabNo = null): float
    {
        $salary = PayrollWorker::query()
            ->where('worker_id', $workerId)
            ->where('basic_salary', '>', 0)
            ->when($clabNo, function ($query) use ($clabNo) {
                $query->whereHas('payrollSubmission', function ($q) use ($clabNo) {
                    $q->where('contractor_clab_no', $clabNo);
                });
            })
            ->latest('id')
            ->value('basic_salary');

        return $salary !== null ? (float) $salary : self::DEFAULT_MONTHLY_SALARY;
    }

    /**
     * Replace a transaction's per-month breakdown with the given rows.
     *
     * Accepts either MonthlyOTEntryTransaction or PayrollWorkerTransaction (both
     * use the HasNplMonthDetails trait). Passing no rows leaves the transaction
     * with no breakdown, which is how legacy NPL records are represented.
     *
     * @param  array<int, array>  $rows  as produced by calculate()
     */
    public function syncDetails($transaction, array $rows): void
    {
        if ($transaction->type !== 'npl') {
            return;
        }

        $transaction->nplDetails()->delete();

        foreach ($rows as $row) {
            if (empty($row['npl_year']) || empty($row['npl_month'])) {
                continue;
            }

            $transaction->nplDetails()->create([
                'npl_year' => $row['npl_year'],
                'npl_month' => $row['npl_month'],
                'days_in_month' => $row['days_in_month'],
                'npl_days' => $row['npl_days'],
                'monthly_salary' => $row['monthly_salary'],
                'daily_rate' => $row['daily_rate'],
                'amount' => $row['amount'],
            ]);
        }
    }

    /**
     * Delete a transaction relation together with any NPL breakdown rows.
     *
     * A plain `$relation->delete()` is a mass delete: it fires no model events,
     * so the polymorphic detail rows would be left orphaned.
     *
     * @param  \Illuminate\Database\Eloquent\Relations\Relation  $relation
     */
    public function deleteTransactionsWithDetails($relation): void
    {
        foreach ((clone $relation)->where('type', 'npl')->get() as $transaction) {
            $transaction->nplDetails()->delete();
        }

        $relation->delete();
    }

    /**
     * Parse an NPL month as typed into the Excel import.
     *
     * The documented format is "Jul-2025", but spreadsheets are permissive and
     * Excel silently converts month-year text into a real date, so a raw serial
     * number or a full date string can arrive here too.
     *
     * @param  mixed  $value  cell value, already trimmed by the caller
     * @return array{0:?int, 1:?int} [year, month], or [null, null] if unparseable
     */
    public function parseNplMonthInput($value): array
    {
        if ($value === null || $value === '') {
            return [null, null];
        }

        // Excel stored it as a date serial (e.g. 45839) rather than text.
        if (is_numeric($value) && (float) $value > 1000) {
            try {
                $date = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $value);

                return [(int) $date->format('Y'), (int) $date->format('n')];
            } catch (\Exception $e) {
                return [null, null];
            }
        }

        $text = trim((string) $value);

        // Normalise separators: "Jul/2025" and "Jul 2025" behave like "Jul-2025".
        $text = preg_replace('/[\s\/\.]+/', '-', $text);

        // Already a "YYYY-MM" key.
        if (preg_match('/^(\d{4})-(\d{1,2})$/', $text, $m)) {
            $month = (int) $m[2];

            return ($month >= 1 && $month <= 12) ? [(int) $m[1], $month] : [null, null];
        }

        // Numeric "MM-YYYY".
        if (preg_match('/^(\d{1,2})-(\d{4})$/', $text, $m)) {
            $month = (int) $m[1];

            return ($month >= 1 && $month <= 12) ? [(int) $m[2], $month] : [null, null];
        }

        // Month name with a 2- or 4-digit year: "Jul-2025", "July-25".
        if (preg_match('/^([A-Za-z]+)-(\d{2,4})$/', $text, $m)) {
            $month = $this->monthNumberFromName($m[1]);

            if ($month === null) {
                return [null, null];
            }

            $year = (int) $m[2];

            if ($year < 100) {
                $year += 2000;
            }

            return [$year, $month];
        }

        // Anything else Carbon can make sense of (e.g. a full date).
        try {
            $date = Carbon::parse($text);

            return [(int) $date->year, (int) $date->month];
        } catch (\Exception $e) {
            return [null, null];
        }
    }

    /**
     * Month number from an English month name or abbreviation.
     */
    protected function monthNumberFromName(string $name): ?int
    {
        $name = strtolower(substr($name, 0, 3));

        $months = [
            'jan' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4,
            'may' => 5, 'jun' => 6, 'jul' => 7, 'aug' => 8,
            'sep' => 9, 'oct' => 10, 'nov' => 11, 'dec' => 12,
        ];

        return $months[$name] ?? null;
    }

    /**
     * Split a "YYYY-MM" key into its parts.
     *
     * @return array{0:?int, 1:?int}
     */
    public function parseMonthKey(string $monthKey): array
    {
        if (! preg_match('/^(\d{4})-(\d{1,2})$/', $monthKey, $matches)) {
            return [null, null];
        }

        $month = (int) $matches[2];

        if ($month < 1 || $month > 12) {
            return [null, null];
        }

        return [(int) $matches[1], $month];
    }
}
