<?php

namespace App\Services;

use App\Models\PayrollSubmission;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Builds the itemised payment breakdown a contractor is billed from.
 *
 * Shared by the admin review screen and the client-facing pages so both read
 * the same figures; passing the already-loaded worker rows avoids a second
 * query where the caller has them.
 */
class ClientBreakdownBuilder
{
    /**
     * Build the client payment breakdown for a submission.
     *
     * Two sources feed this page and they are not interchangeable:
     *
     *  - the worker rows the contractor submitted, which carry basic salary,
     *    OT hours and transactions; and
     *  - the breakdown file the admin uploads at review, whose total becomes
     *    admin_final_amount and is what the client is actually billed.
     *
     * The itemisation from that file is only stored from this release onward,
     * so older submissions fall back to figures computed from the worker rows
     * plus an explicit reconciling adjustment. Nothing here silently plugs a
     * gap between the two — any difference gets its own visible line.
     */
    public function build(PayrollSubmission $submission, ?Collection $workers = null): array
    {
        $calculator = new PaymentCalculatorService;
        $workers = collect($workers ?? $submission->workers()->with(['worker', 'transactions.nplDetails'])->get());
        $transactions = $workers->flatMap(fn ($worker) => $worker->transactions ?? collect());

        $basicSalary = (float) $workers->sum('basic_salary');

        // Submissions fed from the external payroll system leave the derived
        // money columns at zero while still recording OT hours, so fall back
        // to the official rates when a stored figure is missing.
        $overtime = 0.0;
        $overtimeDerived = false;
        $overtimePerWorker = [];

        foreach ($workers as $worker) {
            $stored = (float) $worker->total_ot_pay;

            if ($stored > 0) {
                $overtimePerWorker[$worker->id] = $stored;
                $overtime += $stored;

                continue;
            }

            $hours = (float) $worker->ot_normal_hours
                + (float) $worker->ot_rest_hours
                + (float) $worker->ot_public_hours;

            if ($hours <= 0) {
                $overtimePerWorker[$worker->id] = 0.0;

                continue;
            }

            $derived = $calculator->calculateTotalOvertimePay(
                (float) $worker->basic_salary,
                (float) $worker->ot_normal_hours,
                (float) $worker->ot_rest_hours,
                (float) $worker->ot_public_hours
            );

            $overtimePerWorker[$worker->id] = $derived;
            $overtime += $derived;
            $overtimeDerived = true;
        }

        $additionalEarningsTypes = ['allowance', 'backpay', 'medical_claim'];

        $additionalEarnings = (float) $transactions
            ->whereIn('type', $additionalEarningsTypes)
            ->sum('amount');

        // Needed per worker below: these count as wages for both EPF and
        // SOCSO, unlike overtime which only SOCSO looks at.
        $additionalPerWorker = [];
        foreach ($workers as $worker) {
            $additionalPerWorker[$worker->id] = (float) ($worker->transactions ?? collect())
                ->whereIn('type', $additionalEarningsTypes)
                ->sum('amount');
        }

        $earnings = $basicSalary + $overtime + $additionalEarnings;

        $advancePayment = (float) $transactions->where('type', 'advance_payment')->sum('amount');
        $otherDeductions = (float) $transactions->where('type', 'deduction')->sum('amount');
        $accommodation = (float) $transactions->where('type', 'accommodation')->sum('amount');

        // Unpaid leave is charged on the real number of days in the payroll
        // month. Transactions carrying their own month breakdown are already
        // costed that way; the rest would otherwise fall back to the legacy
        // 26-day divisor, which overstates a 31-day month and understates
        // February.
        $daysInMonth = Carbon::create($submission->year, $submission->month, 1)->daysInMonth;

        $npl = 0.0;
        $nplPerWorker = [];

        foreach ($workers as $worker) {
            $basic = (float) $worker->basic_salary;
            $workerNpl = 0.0;

            foreach (($worker->transactions ?? collect())->where('type', 'npl') as $transaction) {
                $workerNpl += $transaction->nplDetails->isNotEmpty()
                    ? (float) $transaction->nplDetails->sum('amount')
                    : round((float) $transaction->amount * ($basic / $daysInMonth), 2);
            }

            $nplPerWorker[$worker->id] = $workerNpl;
            $npl += $workerNpl;
        }

        // Only advance payment, accommodation and NPL reduce what the client
        // owes — they are the three the approved breakdown file nets off.
        // Plain 'deduction' transactions (the auto-applied contractor
        // templates such as phone topup, and manual ones like rental) are
        // withheld from the worker but retained by CLAB, so the client still
        // pays the full gross on them. Counting them here understated the
        // comparison figure by the whole template amount.
        $deductions = $advancePayment + $accommodation + $npl;

        // Statutory contributions, same fallback story as overtime above.
        $workerEpf = 0.0;
        $workerSocso = 0.0;
        $employerEpf = 0.0;
        $employerSocso = 0.0;
        $statutoryDerived = false;

        foreach ($workers as $worker) {
            $basic = (float) $worker->basic_salary;
            $stored = (float) $worker->epf_employee
                + (float) $worker->socso_employee
                + (float) $worker->epf_employer
                + (float) $worker->socso_employer;

            if ($stored > 0) {
                $workerEpf += (float) $worker->epf_employee;
                $workerSocso += (float) $worker->socso_employee;
                $employerEpf += (float) $worker->epf_employer;
                $employerSocso += (float) $worker->socso_employer;

                continue;
            }

            if ($basic <= 0) {
                continue;
            }

            // Allowances, backpay and claims are wages for both EPF and SOCSO.
            // Overtime is wages for SOCSO only — EPF ignores it. Unpaid leave
            // reduces the EPF base but does not move the SOCSO bracket.
            $additional = $additionalPerWorker[$worker->id] ?? 0.0;
            $gross = $basic + $additional + ($overtimePerWorker[$worker->id] ?? 0.0);
            $epfBase = max(0, $basic + $additional - ($nplPerWorker[$worker->id] ?? 0.0));

            $workerEpf += $calculator->calculateWorkerEPF($epfBase);
            $workerSocso += $calculator->calculateWorkerSOCSO($gross);
            $employerEpf += $calculator->calculateEmployerEPF($epfBase);
            $employerSocso += $calculator->calculateEmployerSOCSO($gross);
            $statutoryDerived = true;
        }

        $file = $submission->admin_breakdown;
        $file = is_array($file) && ! empty($file) ? $file : null;

        $finalAmount = $submission->admin_final_amount;
        // The column defaults to 0.00 rather than NULL, so a null check alone
        // would treat every unreviewed submission as if it had been priced.
        $isReviewed = $finalAmount !== null && (float) $finalAmount > 0;

        // What the worker rows alone say the client owes for payroll.
        $computedPayroll = $earnings - $deductions + $employerEpf + $employerSocso;
        $payrollAmount = $isReviewed ? (float) $finalAmount : $computedPayroll;

        $serviceCharge = (float) $submission->calculated_service_charge;
        $sst = (float) $submission->calculated_sst;

        // Mirror getTotalDueAttribute() exactly: an exempt contractor is never
        // charged a penalty, even if one was stored before the exemption.
        $penalty = 0.0;
        if (! $submission->isPenaltyExempt()) {
            $penalty = $submission->has_penalty && $submission->penalty_amount > 0
                ? (float) $submission->penalty_amount
                : (float) $submission->calculatePenalty();
        }

        return [
            'workers_counted' => $workers->count(),
            'basic_salary' => $basicSalary,
            'overtime' => $overtime,
            'overtime_derived' => $overtimeDerived,
            'additional_earnings' => $additionalEarnings,
            'earnings' => $earnings,

            'advance_payment' => $advancePayment,
            'accommodation' => $accommodation,
            'npl' => $npl,
            'deductions' => $deductions,
            // Withheld from the worker, kept by CLAB: memo only, never a
            // reduction to the client's bill.
            'retained_deductions' => $otherDeductions,

            'worker_epf' => $workerEpf,
            'worker_socso' => $workerSocso,
            'worker_statutory' => $workerEpf + $workerSocso,
            'employer_epf' => $employerEpf,
            'employer_socso' => $employerSocso,
            'employer_statutory' => $employerEpf + $employerSocso,
            'statutory_derived' => $statutoryDerived,

            'file' => $file,
            'is_reviewed' => $isReviewed,
            'computed_payroll' => $computedPayroll,
            'payroll_amount' => $payrollAmount,
            // Difference between the approved figure and what the worker rows
            // add up to. Shown as its own line rather than absorbed anywhere.
            'adjustment' => $isReviewed ? $payrollAmount - $computedPayroll : 0.0,

            'service_charge' => $serviceCharge,
            'sst' => $sst,
            'penalty' => $penalty,
            'total' => $isReviewed
                ? (float) $submission->total_due
                : $payrollAmount + $serviceCharge + $sst + $penalty,
        ];
    }
}
