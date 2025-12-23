<?php

namespace App\Services;

/**
 * Payment Calculator Service
 *
 * Handles all salary and overtime calculations for the e-payroll system.
 * Based on Malaysian labor regulations for foreign construction workers.
 *
 * Reference: FORMULA PENGIRAAN GAJI DAN OVERTIME.csv
 */
class PaymentCalculatorService
{
    // Constants based on Malaysian regulations
    public const MINIMUM_SALARY = 1700.00; // RM 1,700 minimum for foreign workers

    public const EPF_WORKER_RATE = 0.02;   // 2% EPF deduction from worker

    public const EPF_EMPLOYER_RATE = 0.02; // 2% EPF contribution by employer

    // Overtime calculation constants
    public const WORKING_DAYS_PER_MONTH = 26;

    public const WORKING_HOURS_PER_DAY = 8;

    public const OT_WEEKDAY_MULTIPLIER = 1.5;

    public const OT_REST_DAY_MULTIPLIER = 2.0;

    public const OT_PUBLIC_HOLIDAY_MULTIPLIER = 3.0;

    /**
     * SOCSO Contribution Table based on Akta Keselamatan Sosial Pekerja (Akta 4)
     * Reference: Kadar_Caruman_Akta_4.pdf
     *
     * Structure: [salary_from, salary_to, employee_contribution, employer_contribution]
     */
    public const SOCSO_CONTRIBUTION_TABLE = [
        [0.00, 30.00, 0.10, 0.40],
        [30.01, 50.00, 0.20, 0.70],
        [50.01, 70.00, 0.30, 1.10],
        [70.01, 100.00, 0.40, 1.50],
        [100.01, 140.00, 0.60, 2.10],
        [140.01, 200.00, 0.85, 2.95],
        [200.01, 300.00, 1.25, 4.35],
        [300.01, 400.00, 1.75, 6.15],
        [400.01, 500.00, 2.25, 7.85],
        [500.01, 600.00, 2.75, 9.65],
        [600.01, 700.00, 3.25, 11.35],
        [700.01, 800.00, 3.75, 13.15],
        [800.01, 900.00, 4.25, 14.85],
        [900.01, 1000.00, 4.75, 16.65],
        [1000.01, 1100.00, 5.25, 18.35],
        [1100.01, 1200.00, 5.75, 20.15],
        [1200.01, 1300.00, 6.25, 21.85],
        [1300.01, 1400.00, 6.75, 23.65],
        [1400.01, 1500.00, 7.25, 25.35],
        [1500.01, 1600.00, 7.75, 27.15],
        [1600.01, 1700.00, 8.25, 28.85],
        [1700.01, 1800.00, 8.75, 30.65],
        [1800.01, 1900.00, 9.25, 32.35],
        [1900.01, 2000.00, 9.75, 34.15],
        [2000.01, 2100.00, 10.25, 35.85],
        [2100.01, 2200.00, 10.75, 37.65],
        [2200.01, 2300.00, 11.25, 39.35],
        [2300.01, 2400.00, 11.75, 41.15],
        [2400.01, 2500.00, 12.25, 42.85],
        [2500.01, 2600.00, 12.75, 44.65],
        [2600.01, 2700.00, 13.25, 46.35],
        [2700.01, 2800.00, 13.75, 48.15],
        [2800.01, 2900.00, 14.25, 49.85],
        [2900.01, 3000.00, 14.75, 51.65],
        [3000.01, 3100.00, 15.25, 53.35],
        [3100.01, 3200.00, 15.75, 55.15],
        [3200.01, 3300.00, 16.25, 56.85],
        [3300.01, 3400.00, 16.75, 58.65],
        [3400.01, 3500.00, 17.25, 60.35],
        [3500.01, 3600.00, 17.75, 62.15],
        [3600.01, 3700.00, 18.25, 63.85],
        [3700.01, 3800.00, 18.75, 65.65],
        [3800.01, 3900.00, 19.25, 67.35],
        [3900.01, 4000.00, 19.75, 69.15],
        [4000.01, 4100.00, 20.25, 70.85],
        [4100.01, 4200.00, 20.75, 72.65],
        [4200.01, 4300.00, 21.25, 74.35],
        [4300.01, 4400.00, 21.75, 76.15],
        [4400.01, 4500.00, 22.25, 77.85],
        [4500.01, 4600.00, 22.75, 79.65],
        [4600.01, 4700.00, 23.25, 81.35],
        [4700.01, 4800.00, 23.75, 83.15],
        [4800.01, 4900.00, 24.25, 84.85],
        [4900.01, 5000.00, 24.75, 86.65],
        [5000.01, 5100.00, 25.25, 88.35],
        [5100.01, 5200.00, 25.75, 90.15],
        [5200.01, 5300.00, 26.25, 91.85],
        [5300.01, 5400.00, 26.75, 93.65],
        [5400.01, 5500.00, 27.25, 95.35],
        [5500.01, 5600.00, 27.75, 97.15],
        [5600.01, 5700.00, 28.25, 98.85],
        [5700.01, 5800.00, 28.75, 100.65],
        [5800.01, 5900.00, 29.25, 102.35],
        [5900.01, 6000.00, 29.75, 104.15],
        [6000.01, PHP_FLOAT_MAX, 29.75, 104.15], // Maximum contribution for salaries > RM6,000
    ];

    /**
     * Find SOCSO contribution bracket for a given salary
     *
     * @param  float  $salary  Monthly salary
     * @return array|null Returns [employee_contribution, employer_contribution] or null if not found
     */
    private function getSOCSObracket(float $salary): ?array
    {
        foreach (self::SOCSO_CONTRIBUTION_TABLE as $bracket) {
            [$salaryFrom, $salaryTo, $employeeContribution, $employerContribution] = $bracket;

            if ($salary >= $salaryFrom && $salary <= $salaryTo) {
                return [
                    'employee' => $employeeContribution,
                    'employer' => $employerContribution,
                ];
            }
        }

        // If salary is beyond table range, return maximum contribution
        return [
            'employee' => 29.75,
            'employer' => 104.15,
        ];
    }

    /**
     * Calculate worker's EPF deduction (2%)
     */
    public function calculateWorkerEPF(float $basicSalary): float
    {
        return round($basicSalary * self::EPF_WORKER_RATE, 2);
    }

    /**
     * Calculate employer's EPF contribution (2%)
     */
    public function calculateEmployerEPF(float $basicSalary): float
    {
        return round($basicSalary * self::EPF_EMPLOYER_RATE, 2);
    }

    /**
     * Calculate worker's SOCSO deduction based on contribution table
     * Uses official SOCSO table from Kadar_Caruman_Akta_4.pdf
     */
    public function calculateWorkerSOCSO(float $basicSalary): float
    {
        $bracket = $this->getSOCSObracket($basicSalary);

        return round($bracket['employee'], 2);
    }

    /**
     * Calculate employer's SOCSO contribution based on contribution table
     * Uses official SOCSO table from Kadar_Caruman_Akta_4.pdf
     */
    public function calculateEmployerSOCSO(float $basicSalary): float
    {
        $bracket = $this->getSOCSObracket($basicSalary);

        return round($bracket['employer'], 2);
    }

    /**
     * Calculate total worker deductions (EPF + SOCSO)
     */
    public function calculateTotalWorkerDeductions(float $basicSalary): float
    {
        return $this->calculateWorkerEPF($basicSalary) + $this->calculateWorkerSOCSO($basicSalary);
    }

    /**
     * Calculate total employer contributions (EPF + SOCSO)
     */
    public function calculateTotalEmployerContributions(float $basicSalary): float
    {
        return $this->calculateEmployerEPF($basicSalary) + $this->calculateEmployerSOCSO($basicSalary);
    }

    /**
     * Calculate net salary (after deductions)
     * This is what the worker receives
     */
    public function calculateNetSalary(float $basicSalary): float
    {
        return $basicSalary - $this->calculateTotalWorkerDeductions($basicSalary);
    }

    /**
     * Calculate total amount employer must pay to CLAB
     * Formula: Basic Salary + Employer Contributions
     */
    public function calculateTotalPaymentToCLAB(float $basicSalary): float
    {
        return $basicSalary + $this->calculateTotalEmployerContributions($basicSalary);
    }

    /**
     * Calculate daily rate (ORP - Ordinary Rate of Pay per day)
     * Formula: Basic Salary / 26 working days
     */
    public function calculateDailyRate(float $basicSalary): float
    {
        return round($basicSalary / self::WORKING_DAYS_PER_MONTH, 2);
    }

    /**
     * Calculate hourly rate (HRP - Hourly Rate of Pay)
     * Formula: Daily Rate / 8 working hours
     */
    public function calculateHourlyRate(float $basicSalary): float
    {
        $dailyRate = $this->calculateDailyRate($basicSalary);

        return round($dailyRate / self::WORKING_HOURS_PER_DAY, 2);
    }

    /**
     * Calculate weekday overtime rate
     * Formula: Hourly Rate × 1.5
     */
    public function calculateWeekdayOTRate(float $basicSalary): float
    {
        $hourlyRate = $this->calculateHourlyRate($basicSalary);

        return round($hourlyRate * self::OT_WEEKDAY_MULTIPLIER, 2);
    }

    /**
     * Calculate rest day overtime rate
     * Formula: Hourly Rate × 2.0
     */
    public function calculateRestDayOTRate(float $basicSalary): float
    {
        $hourlyRate = $this->calculateHourlyRate($basicSalary);

        return round($hourlyRate * self::OT_REST_DAY_MULTIPLIER, 2);
    }

    /**
     * Calculate public holiday overtime rate
     * Formula: Hourly Rate × 3.0
     */
    public function calculatePublicHolidayOTRate(float $basicSalary): float
    {
        $hourlyRate = $this->calculateHourlyRate($basicSalary);

        return round($hourlyRate * self::OT_PUBLIC_HOLIDAY_MULTIPLIER, 2);
    }

    /**
     * Calculate total overtime pay
     *
     * @param  float  $weekdayOTHours  Number of overtime hours on weekdays
     * @param  float  $restDayOTHours  Number of overtime hours on rest days
     * @param  float  $publicHolidayOTHours  Number of overtime hours on public holidays
     * @return float Total overtime amount
     */
    public function calculateTotalOvertimePay(
        float $basicSalary,
        float $weekdayOTHours = 0,
        float $restDayOTHours = 0,
        float $publicHolidayOTHours = 0
    ): float {
        $weekdayOT = $this->calculateWeekdayOTRate($basicSalary) * $weekdayOTHours;
        $restDayOT = $this->calculateRestDayOTRate($basicSalary) * $restDayOTHours;
        $publicHolidayOT = $this->calculatePublicHolidayOTRate($basicSalary) * $publicHolidayOTHours;

        return round($weekdayOT + $restDayOT + $publicHolidayOT, 2);
    }

    /**
     * Calculate complete worker payment breakdown
     *
     * @param  float  $basicSalary  Worker's basic salary
     * @param  float  $weekdayOTHours  Weekday overtime hours
     * @param  float  $restDayOTHours  Rest day overtime hours
     * @param  float  $publicHolidayOTHours  Public holiday overtime hours
     * @param  float  $advancePayment  Advance payment to worker (loan)
     * @param  float  $deduction  Deduction from worker's salary
     * @return array Complete payment breakdown
     */
    public function calculateWorkerPayment(
        float $basicSalary,
        float $weekdayOTHours = 0,
        float $restDayOTHours = 0,
        float $publicHolidayOTHours = 0,
        float $advancePayment = 0,
        float $deduction = 0
    ): array {
        // Basic salary calculations
        $workerEPF = $this->calculateWorkerEPF($basicSalary);
        $workerSOCSO = $this->calculateWorkerSOCSO($basicSalary);
        $employerEPF = $this->calculateEmployerEPF($basicSalary);
        $employerSOCSO = $this->calculateEmployerSOCSO($basicSalary);

        $totalWorkerDeductions = $workerEPF + $workerSOCSO;
        $totalEmployerContributions = $employerEPF + $employerSOCSO;

        $netBasicSalary = $basicSalary - $totalWorkerDeductions;

        // Overtime calculations
        $overtimePay = $this->calculateTotalOvertimePay(
            $basicSalary,
            $weekdayOTHours,
            $restDayOTHours,
            $publicHolidayOTHours
        );

        // Total calculations
        // Formula: Jumlah Gaji = Basic + OT - Advance Payment - Deduction
        $totalGrossPay = $basicSalary + $overtimePay - $advancePayment - $deduction;
        $totalNetPay = $netBasicSalary + $overtimePay - $advancePayment - $deduction;
        $totalPaymentToCLAB = $basicSalary + $totalEmployerContributions + $overtimePay;

        return [
            // Basic salary components
            'basic_salary' => $basicSalary,
            'worker_epf' => $workerEPF,
            'worker_socso' => $workerSOCSO,
            'employer_epf' => $employerEPF,
            'employer_socso' => $employerSOCSO,
            'total_worker_deductions' => $totalWorkerDeductions,
            'total_employer_contributions' => $totalEmployerContributions,
            'net_basic_salary' => $netBasicSalary,

            // Overtime components
            'weekday_ot_hours' => $weekdayOTHours,
            'rest_day_ot_hours' => $restDayOTHours,
            'public_holiday_ot_hours' => $publicHolidayOTHours,
            'weekday_ot_rate' => $this->calculateWeekdayOTRate($basicSalary),
            'rest_day_ot_rate' => $this->calculateRestDayOTRate($basicSalary),
            'public_holiday_ot_rate' => $this->calculatePublicHolidayOTRate($basicSalary),
            'total_overtime_pay' => $overtimePay,

            // Additional adjustments
            'advance_payment' => $advancePayment,
            'deduction' => $deduction,

            // Totals
            'total_gross_pay' => $totalGrossPay,
            'total_net_pay' => $totalNetPay,
            'total_payment_to_clab' => $totalPaymentToCLAB,

            // Rate information
            'hourly_rate' => $this->calculateHourlyRate($basicSalary),
            'daily_rate' => $this->calculateDailyRate($basicSalary),
        ];
    }

    /**
     * Get payment summary for display
     */
    public function getPaymentSummary(array $paymentBreakdown): array
    {
        return [
            'Basic Salary' => 'RM '.number_format($paymentBreakdown['basic_salary'], 2),
            'Worker Deductions (EPF + SOCSO)' => 'RM '.number_format($paymentBreakdown['total_worker_deductions'], 2),
            'Net Basic Salary' => 'RM '.number_format($paymentBreakdown['net_basic_salary'], 2),
            'Overtime Pay' => 'RM '.number_format($paymentBreakdown['total_overtime_pay'], 2),
            'Advance Payment' => 'RM '.number_format($paymentBreakdown['advance_payment'], 2),
            'Deduction' => 'RM '.number_format($paymentBreakdown['deduction'], 2),
            'Total Net Pay (Worker Receives)' => 'RM '.number_format($paymentBreakdown['total_net_pay'], 2),
            'Employer Contributions' => 'RM '.number_format($paymentBreakdown['total_employer_contributions'], 2),
            'Total Payment to CLAB' => 'RM '.number_format($paymentBreakdown['total_payment_to_clab'], 2),
        ];
    }
}
