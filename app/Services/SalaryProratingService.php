<?php

namespace App\Services;

use Carbon\Carbon;

/**
 * Service for calculating pro-rated basic salary for workers
 * based on contract start and end dates within a payroll period.
 *
 * Uses calendar days (not working days) for calculations.
 */
class SalaryProratingService
{
    /**
     * Minimum basic salary for foreign construction workers in Malaysia.
     */
    public const MINIMUM_SALARY = 1700.00;

    /**
     * Calculate pro-rated basic salary for a worker in a specific payroll month.
     *
     * Formula: (baseSalary / daysInMonth) × daysWorked
     *
     * Examples:
     * - Worker starts 15/12/2025 in December (31 days): (1700 / 31) × 17 = RM 933.87
     * - Worker ends 15/11/2025 in November (30 days): (1700 / 30) × 15 = RM 850.00
     * - Worker works full month: No pro-rating, returns full salary
     *
     * @param  Carbon  $contractStart  Contract start date
     * @param  Carbon|null  $contractEnd  Contract end date (null if ongoing)
     * @param  int  $month  Payroll month (1-12)
     * @param  int  $year  Payroll year
     * @param  float  $baseSalary  Full monthly salary (default: RM 1,700)
     * @return array ['pro_rated_salary' => float, 'days_worked' => int, 'total_days' => int, 'is_pro_rated' => bool, 'notes' => string]
     */
    public function calculateProratedSalary(
        Carbon $contractStart,
        ?Carbon $contractEnd,
        int $month,
        int $year,
        float $baseSalary = self::MINIMUM_SALARY
    ): array {
        // Check if worker was active during the payroll period
        if (! $this->wasWorkerActiveInMonth($contractStart, $contractEnd, $month, $year)) {
            return [
                'pro_rated_salary' => 0.00,
                'days_worked' => 0,
                'total_days' => Carbon::create($year, $month, 1)->daysInMonth,
                'is_pro_rated' => false,
                'notes' => 'Worker not active during this payroll period',
            ];
        }

        // Get month boundaries
        $boundaries = $this->getMonthBoundaries($month, $year);
        $monthStart = $boundaries['start'];
        $monthEnd = $boundaries['end'];
        $totalDaysInMonth = $monthStart->daysInMonth;

        // Calculate days worked in the month
        $daysWorked = $this->calculateDaysWorkedInMonth($contractStart, $contractEnd, $month, $year);

        // Determine if pro-rating is needed
        $isProRated = $daysWorked < $totalDaysInMonth;

        // Calculate pro-rated salary
        $dailyRate = $baseSalary / $totalDaysInMonth;
        $proratedSalary = round($dailyRate * $daysWorked, 2);

        // Generate human-readable notes
        $notes = $this->generateProratingNotes($contractStart, $contractEnd, $monthStart, $monthEnd, $daysWorked, $totalDaysInMonth);

        return [
            'pro_rated_salary' => $proratedSalary,
            'days_worked' => $daysWorked,
            'total_days' => $totalDaysInMonth,
            'is_pro_rated' => $isProRated,
            'notes' => $notes,
        ];
    }

    /**
     * Calculate the number of calendar days a worker was active in a specific month.
     *
     * Accounts for contract start and end dates that fall within or outside the month.
     *
     * @param  Carbon  $contractStart  Contract start date
     * @param  Carbon|null  $contractEnd  Contract end date (null if ongoing)
     * @param  int  $month  Payroll month (1-12)
     * @param  int  $year  Payroll year
     * @return int Number of days worked in the month (1-31)
     */
    private function calculateDaysWorkedInMonth(
        Carbon $contractStart,
        ?Carbon $contractEnd,
        int $month,
        int $year
    ): int {
        $boundaries = $this->getMonthBoundaries($month, $year);
        $monthStart = $boundaries['start'];
        $monthEnd = $boundaries['end'];

        // Determine the actual work start date (later of contract start or month start)
        $workStart = $contractStart->greaterThan($monthStart) ? $contractStart : $monthStart;

        // Determine the actual work end date (earlier of contract end or month end)
        // If contract end is null (ongoing), use month end
        $workEnd = $contractEnd && $contractEnd->lessThan($monthEnd) ? $contractEnd : $monthEnd;

        // Calculate days inclusive (add 1 because both start and end dates are included)
        $daysWorked = $workStart->diffInDays($workEnd) + 1;

        return max(0, $daysWorked);
    }

    /**
     * Get the first and last day of a specific month.
     *
     * @param  int  $month  Month (1-12)
     * @param  int  $year  Year
     * @return array ['start' => Carbon, 'end' => Carbon]
     */
    private function getMonthBoundaries(int $month, int $year): array
    {
        $monthStart = Carbon::create($year, $month, 1)->startOfDay();
        $monthEnd = $monthStart->copy()->endOfMonth()->startOfDay();

        return [
            'start' => $monthStart,
            'end' => $monthEnd,
        ];
    }

    /**
     * Check if a worker was active on any day of the given payroll month.
     *
     * A worker is considered active if their contract overlaps with any part of the month.
     *
     * @param  Carbon  $contractStart  Contract start date
     * @param  Carbon|null  $contractEnd  Contract end date (null if ongoing)
     * @param  int  $month  Payroll month (1-12)
     * @param  int  $year  Payroll year
     * @return bool True if worker was active during the month
     */
    public function wasWorkerActiveInMonth(
        Carbon $contractStart,
        ?Carbon $contractEnd,
        int $month,
        int $year
    ): bool {
        $boundaries = $this->getMonthBoundaries($month, $year);
        $monthStart = $boundaries['start'];
        $monthEnd = $boundaries['end'];

        // Contract must start on or before month end
        $startedBeforeOrDuringMonth = $contractStart->lessThanOrEqualTo($monthEnd);

        // Contract must end on or after month start (or be ongoing)
        $endedDuringOrAfterMonth = $contractEnd === null || $contractEnd->greaterThanOrEqualTo($monthStart);

        return $startedBeforeOrDuringMonth && $endedDuringOrAfterMonth;
    }

    /**
     * Generate human-readable notes explaining the pro-rating calculation.
     *
     * @param  Carbon  $contractStart  Contract start date
     * @param  Carbon|null  $contractEnd  Contract end date
     * @param  Carbon  $monthStart  First day of payroll month
     * @param  Carbon  $monthEnd  Last day of payroll month
     * @param  int  $daysWorked  Days worked in the month
     * @param  int  $totalDays  Total days in the month
     * @return string Human-readable explanation
     */
    private function generateProratingNotes(
        Carbon $contractStart,
        ?Carbon $contractEnd,
        Carbon $monthStart,
        Carbon $monthEnd,
        int $daysWorked,
        int $totalDays
    ): string {
        // Full month worked
        if ($daysWorked === $totalDays) {
            return 'Full month salary';
        }

        $notes = [];

        // Contract started mid-month
        if ($contractStart->greaterThan($monthStart)) {
            $notes[] = 'Started '.$contractStart->format('d/m/Y');
        }

        // Contract ended mid-month
        if ($contractEnd && $contractEnd->lessThan($monthEnd)) {
            $notes[] = 'Ended '.$contractEnd->format('d/m/Y');
        }

        // Build the final note
        if (empty($notes)) {
            return sprintf('Worked %d of %d days', $daysWorked, $totalDays);
        }

        return implode(', ', $notes).sprintf(' (%d of %d days)', $daysWorked, $totalDays);
    }
}
