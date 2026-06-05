<?php

namespace App\Services;

use App\Models\ContractWorker;
use App\Models\PayrollSubmission;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Detects a contractor's outstanding payroll periods so client pages can enforce
 * "settle the oldest period first" blocking consistently.
 *
 * A period is outstanding when it is a past-month draft, an overdue (unpaid)
 * payment, or a past month with workers that were never submitted. The current
 * month and any already-paid month are always excluded.
 */
class OutstandingPayrollService
{
    public function __construct(
        protected SalaryProratingService $proratingService
    ) {}

    /**
     * Collect all outstanding payroll periods for a contractor, sorted oldest-first.
     */
    public function getOutstandingPeriods(string $clabNo): Collection
    {
        $currentMonth = now()->month;
        $currentYear = now()->year;
        $currentDate = now()->startOfMonth();

        $periods = collect();

        // 1. Draft submissions (excluding current month)
        $drafts = PayrollSubmission::where('contractor_clab_no', $clabNo)
            ->where('status', 'draft')
            ->where(function ($query) use ($currentMonth, $currentYear) {
                $query->where('year', '<', $currentYear)
                    ->orWhere(function ($q) use ($currentMonth, $currentYear) {
                        $q->where('year', '=', $currentYear)
                            ->where('month', '<', $currentMonth);
                    });
            })
            ->get();

        foreach ($drafts as $draft) {
            $periods->push([
                'type' => 'draft',
                'month' => $draft->month,
                'year' => $draft->year,
                'month_year' => $draft->month_year,
                'data' => $draft,
                'sort_key' => $draft->year * 100 + $draft->month,
            ]);
        }

        // 2. Overdue payments (excluding current month)
        $overdue = PayrollSubmission::where('contractor_clab_no', $clabNo)
            ->overdue()
            ->where(function ($query) use ($currentMonth, $currentYear) {
                $query->where('year', '<', $currentYear)
                    ->orWhere(function ($q) use ($currentMonth, $currentYear) {
                        $q->where('year', '=', $currentYear)
                            ->where('month', '<', $currentMonth);
                    });
            })
            ->get();

        foreach ($overdue as $payment) {
            $periods->push([
                'type' => 'overdue',
                'month' => $payment->month,
                'year' => $payment->year,
                'month_year' => $payment->month_year,
                'data' => $payment,
                'sort_key' => $payment->year * 100 + $payment->month,
            ]);
        }

        // 3. Missing submissions (past 6 months, unsubmitted workers, excluding paid months)
        for ($i = 1; $i <= 6; $i++) {
            $checkDate = $currentDate->copy()->subMonths($i);
            $month = $checkDate->month;
            $year = $checkDate->year;

            $activeWorkerIds = ContractWorker::where('con_ctr_clab_no', $clabNo)
                ->where('con_end', '>=', $checkDate->copy()->startOfMonth()->toDateString())
                ->where('con_start', '<=', $checkDate->copy()->endOfMonth()->toDateString())
                ->get(['con_wkr_id', 'con_start'])
                // Waive a worker's contract-start month when it starts on/after the
                // monthly cut-off — those days are paid in the following month, so the
                // start month should not block the queue as a "missing" period.
                ->reject(fn ($contract) => $this->proratingService->isFirstMonthWaived(
                    Carbon::parse($contract->con_start),
                    $month,
                    $year
                ))
                ->pluck('con_wkr_id')
                ->unique();

            if ($activeWorkerIds->isEmpty()) {
                continue;
            }

            $allSubmissionsForPeriod = PayrollSubmission::where('contractor_clab_no', $clabNo)
                ->where('month', $month)
                ->where('year', $year)
                ->with('workers')
                ->get();

            // Skip months that are already paid — only show payroll that hasn't been paid
            if ($allSubmissionsForPeriod->contains(fn ($s) => $s->status === 'paid')) {
                continue;
            }

            $submittedWorkerIds = $allSubmissionsForPeriod->flatMap(function ($submission) {
                return $submission->workers->pluck('worker_id');
            })->unique()->toArray();

            $unsubmittedWorkerIds = $activeWorkerIds->diff($submittedWorkerIds);

            if ($unsubmittedWorkerIds->count() > 0) {
                $periods->push([
                    'type' => 'missing',
                    'month' => $month,
                    'year' => $year,
                    'month_year' => $checkDate->format('F Y'),
                    'total_workers' => $unsubmittedWorkerIds->count(),
                    'sort_key' => $year * 100 + $month,
                ]);
            }
        }

        return $periods->sortBy('sort_key')->values();
    }

    /**
     * Count of DISTINCT outstanding months. A single month can appear under more
     * than one type (e.g. overdue + missing) but is still one month remaining.
     */
    public function countOutstandingMonths(Collection $periods): int
    {
        return $periods->unique(fn ($period) => $period['year'].'-'.$period['month'])->count();
    }

    /**
     * Build the redirect target + action text for the oldest outstanding period.
     */
    public function buildBlockReason(array $oldest): array
    {
        $redirectUrl = route('timesheet', ['month' => $oldest['month'], 'year' => $oldest['year']]);
        $actionText = 'Go to '.$oldest['month_year'].' Payroll';

        if ($oldest['type'] === 'overdue') {
            // Overdue payments: send the client to the invoice to settle it
            $redirectUrl = route('invoices.client', ['year' => $oldest['year']]);
            $actionText = 'Pay '.$oldest['month_year'].' Invoice';
        } elseif ($oldest['type'] === 'draft' && isset($oldest['data'])) {
            $redirectUrl = route('timesheet.edit', $oldest['data']->id);
            $actionText = 'Complete '.$oldest['month_year'].' Draft';
        }

        return [
            'type' => $oldest['type'],
            'message' => 'Please complete payroll submissions in chronological order. The next period to complete is '.$oldest['month_year'].'.',
            'redirect_month' => $oldest['month'],
            'redirect_year' => $oldest['year'],
            'redirect_url' => $redirectUrl,
            'action_text' => $actionText,
        ];
    }
}
