<?php

namespace App\Models;

use App\Services\PaymentCalculatorService;
use Illuminate\Database\Eloquent\Model;

class PayrollWorker extends Model
{
    protected $fillable = [
        'payroll_submission_id',
        'worker_id',
        'worker_name',
        'worker_passport',
        'basic_salary',
        'is_pro_rated',
        'days_worked',
        'total_days_in_month',
        'prorating_notes',
        'regular_hours',
        'ot_normal_hours',
        'ot_rest_hours',
        'ot_public_hours',
        'regular_pay',
        'ot_normal_pay',
        'ot_rest_pay',
        'ot_public_pay',
        'total_ot_pay',
        'gross_salary',
        'advance_payment',
        'advance_payment_remarks',
        'deduction',
        'deduction_remarks',
        'epf_employee',
        'socso_employee',
        'total_deductions',
        'epf_employer',
        'socso_employer',
        'total_employer_contribution',
        'net_salary',
        'total_payment',
    ];

    protected $casts = [
        'basic_salary' => 'decimal:2',
        'is_pro_rated' => 'boolean',
        'days_worked' => 'integer',
        'total_days_in_month' => 'integer',
        'regular_hours' => 'decimal:2',
        'ot_normal_hours' => 'decimal:2',
        'ot_rest_hours' => 'decimal:2',
        'ot_public_hours' => 'decimal:2',
        'regular_pay' => 'decimal:2',
        'ot_normal_pay' => 'decimal:2',
        'ot_rest_pay' => 'decimal:2',
        'ot_public_pay' => 'decimal:2',
        'total_ot_pay' => 'decimal:2',
        'gross_salary' => 'decimal:2',
        'advance_payment' => 'decimal:2',
        'deduction' => 'decimal:2',
        'epf_employee' => 'decimal:2',
        'socso_employee' => 'decimal:2',
        'total_deductions' => 'decimal:2',
        'epf_employer' => 'decimal:2',
        'socso_employer' => 'decimal:2',
        'total_employer_contribution' => 'decimal:2',
        'net_salary' => 'decimal:2',
        'total_payment' => 'decimal:2',
    ];

    /**
     * Get the payroll submission this worker belongs to
     */
    public function payrollSubmission()
    {
        return $this->belongsTo(PayrollSubmission::class);
    }

    /**
     * Alias for payrollSubmission relationship
     */
    public function submission()
    {
        return $this->payrollSubmission();
    }

    /**
     * Get the worker details from the worker_db database
     */
    public function worker()
    {
        return $this->belongsTo(Worker::class, 'worker_id', 'wkr_id');
    }

    /**
     * Get all transactions for this payroll worker
     */
    public function transactions()
    {
        return $this->hasMany(PayrollWorkerTransaction::class);
    }

    /**
     * Automatically trim worker_id to prevent spaces causing matching issues
     */
    public function setWorkerIdAttribute($value)
    {
        $this->attributes['worker_id'] = trim($value);
    }

    /**
     * Get total advance payment from all transactions
     */
    public function getTotalAdvancePaymentAttribute(): float
    {
        return $this->transactions()
            ->where('type', 'advance_payment')
            ->sum('amount') ?? 0;
    }

    /**
     * Get total deductions from all transactions
     */
    public function getTotalDeductionAttribute(): float
    {
        return $this->transactions()
            ->where('type', 'deduction')
            ->sum('amount') ?? 0;
    }

    /**
     * Get total NPL (No-Pay Leave) amount from all transactions
     * NPL is stored as days in 'amount' field and calculated as: (Basic Salary / 26) × Days
     */
    public function getTotalNplAttribute(): float
    {
        $nplDays = $this->transactions()
            ->where('type', 'npl')
            ->sum('amount') ?? 0;

        return round(($this->basic_salary / 26) * $nplDays, 2);
    }

    /**
     * Get total allowance from all transactions
     */
    public function getTotalAllowanceAttribute(): float
    {
        return $this->transactions()
            ->where('type', 'allowance')
            ->sum('amount') ?? 0;
    }

    /**
     * Calculate all salary components using PaymentCalculatorService
     *
     * NEW SYSTEM: Contractor enters PREVIOUS month's OT hours in current month's payroll
     * - Example: In November payroll, contractor enters October's OT hours
     * - The OT is calculated and paid in the same month (November)
     *
     * This system collects: Basic Salary + Employer Contributions (EPF + SOCSO) + OT
     * Worker receives: Basic Salary - Worker Deductions (EPF + SOCSO) + OT
     *
     * Formula (from FORMULA PENGIRAAN GAJI DAN OVERTIME.csv):
     * - Basic Salary: RM 1,700 minimum
     * - EPF Worker: 2% of BASIC SALARY (OT NOT included) | EPF Employer: 2% of BASIC SALARY
     * - SOCSO Worker: (Follow table, based on Gross Salary) | SOCSO Employer: (Follow table, based on Gross Salary)
     * - Daily Rate: Basic / 26 days
     * - Hourly Rate: Daily / 8 hours
     * - Weekday OT: Hourly × 1.5
     * - Rest Day OT: Hourly × 2.0
     * - Public Holiday OT: Hourly × 3.0
     *
     * IMPORTANT: EPF vs SOCSO Calculation Base
     * - EPF (both worker & employer): Calculated on BASIC SALARY only
     * - SOCSO (both worker & employer): Calculated on GROSS SALARY (Basic + OT - Advance - Deduction)
     *
     * @param  float  $additionalOtPay  Additional OT amount (legacy parameter, kept for compatibility)
     */
    public function calculateSalary(float $additionalOtPay = 0): void
    {
        // Skip if auto-calculation disabled (admin review workflow)
        if (! config('payroll.use_auto_calculations', false)) {
            return;
        }

        $calculator = app(PaymentCalculatorService::class);

        // Calculate OT for PREVIOUS month (entered this month, paid this month)
        // Example: In November payroll, these hours are October's OT
        $this->ot_normal_pay = round($calculator->calculateWeekdayOTRate($this->basic_salary) * $this->ot_normal_hours, 2);
        $this->ot_rest_pay = round($calculator->calculateRestDayOTRate($this->basic_salary) * $this->ot_rest_hours, 2);
        $this->ot_public_pay = round($calculator->calculatePublicHolidayOTRate($this->basic_salary) * $this->ot_public_hours, 2);
        $this->total_ot_pay = $this->ot_normal_pay + $this->ot_rest_pay + $this->ot_public_pay;

        // Regular pay is the basic salary
        $this->regular_pay = $this->basic_salary;

        // Get transaction totals
        $totalAdvancePayment = $this->exists ? $this->total_advance_payment : ($this->advance_payment ?? 0);
        $totalDeduction = $this->exists ? $this->total_deduction : ($this->deduction ?? 0);
        $totalNpl = $this->exists ? $this->total_npl : 0;
        $totalAllowance = $this->exists ? $this->total_allowance : 0;

        // Total deductions = Advances + Deductions + NPL
        $totalTransactionDeductions = $totalAdvancePayment + $totalDeduction + $totalNpl;

        // Gross salary = Basic + OT + Allowance (before any deductions)
        // This is the total earnings before statutory and transaction deductions
        $this->gross_salary = $this->basic_salary + $this->total_ot_pay + $totalAllowance + $additionalOtPay;

        // If gross salary is 0 (e.g., worker ended contract), no statutory contributions
        if ($this->gross_salary <= 0) {
            $this->epf_employee = 0;
            $this->socso_employee = 0;
            $this->total_deductions = 0;
            $this->epf_employer = 0;
            $this->socso_employer = 0;
            $this->total_employer_contribution = 0;
        } else {
            // Employee statutory deductions
            // EPF: Calculated on BASIC SALARY only (OT NOT included)
            // SOCSO: Calculated on GROSS SALARY (Basic + OT, before deductions)
            $this->epf_employee = $calculator->calculateWorkerEPF($this->basic_salary);
            $this->socso_employee = $calculator->calculateWorkerSOCSO($this->gross_salary);
            $this->total_deductions = $this->epf_employee + $this->socso_employee;

            // Employer contributions
            // EPF: Calculated on BASIC SALARY only (OT NOT included)
            // SOCSO: Calculated on GROSS SALARY (Basic + OT, before deductions)
            $this->epf_employer = $calculator->calculateEmployerEPF($this->basic_salary);
            $this->socso_employer = $calculator->calculateEmployerSOCSO($this->gross_salary);
            $this->total_employer_contribution = $this->epf_employer + $this->socso_employer;
        }

        // Final amounts
        // Net salary = Gross - Statutory Deductions - Transaction Deductions (Advances/Deductions)
        // Calculation flow: Gross -> minus Statutory -> minus Advances/Deductions = Net
        $this->net_salary = $this->gross_salary - $this->total_deductions - $totalTransactionDeductions;

        // Total payment = What system collects from contractor
        // Formula: Gross + Employer Contributions - Transaction Deductions
        // Transaction deductions are subtracted because contractor already paid these amounts
        $this->total_payment = $this->gross_salary + $this->total_employer_contribution - $totalTransactionDeductions;
    }

    /**
     * Get total overtime hours
     */
    public function getTotalOvertimeHoursAttribute(): float
    {
        return $this->ot_normal_hours + $this->ot_rest_hours + $this->ot_public_hours;
    }

    /**
     * Get payment breakdown using calculator service
     */
    public function getPaymentBreakdown(): array
    {
        $calculator = app(PaymentCalculatorService::class);

        return $calculator->calculateWorkerPayment(
            $this->basic_salary,
            $this->ot_normal_hours,
            $this->ot_rest_hours,
            $this->ot_public_hours,
            $this->advance_payment ?? 0,
            $this->deduction ?? 0
        );
    }

    /**
     * Check if the worker's contract has ended for the payroll submission period
     * Contract is considered ended if con_end date is before the payroll submission month
     */
    public function hasContractEnded(): bool
    {
        // Get the worker's contract information from worker_db
        $contract = \DB::connection('worker_db')
            ->table('contract_worker')
            ->where('con_wkr_id', $this->worker_id)
            ->where('con_ctr_clab_no', $this->payrollSubmission->contractor_clab_no)
            ->orderBy('con_end', 'desc')
            ->first();

        if (! $contract || ! $contract->con_end) {
            return false;
        }

        // Get the payroll submission period (month/year)
        $submission = $this->payrollSubmission;
        $payrollPeriodStart = \Carbon\Carbon::create($submission->year, $submission->month, 1)->startOfMonth();

        // Contract has ended if con_end is before the start of the payroll period
        $contractEndDate = \Carbon\Carbon::parse($contract->con_end);

        return $contractEndDate->isBefore($payrollPeriodStart);
    }

    /**
     * Check if this worker should be excluded from service charge billing
     * Excluded if: contract ended AND only has OT hours (no basic salary)
     */
    public function isExcludedFromBilling(): bool
    {
        return $this->hasContractEnded() && $this->basic_salary == 0;
    }
}
