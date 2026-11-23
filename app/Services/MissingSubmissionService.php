<?php

namespace App\Services;

use App\Models\ContractWorker;
use App\Models\PayrollException;
use App\Models\PayrollWorker;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Single source of truth for "which contractors still have workers missing
 * from payroll" in a period.
 *
 * Used by the No Submissions page and the admin dashboard's Pending
 * Submissions card, so the two can never disagree.
 *
 * A worker counts for a period when their contract overlaps the month, the
 * worker record is active (wkr_status = 1), they are not manually deactivated,
 * and they have no payroll exception that month. A worker whose contract
 * starts on/after the cutoff day is paid with next month's payroll, so their
 * absence this month is expected and not counted.
 */
class MissingSubmissionService
{
    /** Day of the month the auto-submit scheduler runs (see routes/console.php). */
    public const AUTO_SUBMIT_DAY = 16;

    protected ?array $deactivatedIds = null;

    protected array $inactiveIdsCache = [];

    protected array $payrollWorkerIdsCache = [];

    public function __construct(
        protected SalaryProratingService $proratingService
    ) {}

    /**
     * Before auto-submit runs, the current month's submission window is still
     * open, so its "not submitted" list is not meaningful yet.
     */
    public function isWindowOpen(int $month, int $year): bool
    {
        return $month === now()->month
            && $year === now()->year
            && now()->day < self::AUTO_SUBMIT_DAY;
    }

    /**
     * Raw SQL condition restricting contract_worker rows to active worker records.
     */
    public function activeWorkerCondition(): string
    {
        $workerDb = config('database.connections.worker_db.database');

        return "EXISTS (SELECT 1 FROM `{$workerDb}`.`workers` WHERE `{$workerDb}`.`workers`.`wkr_id` = `contract_worker`.`con_wkr_id` AND `{$workerDb}`.`workers`.`wkr_status` = '1')";
    }

    /**
     * Worker IDs to leave out of a payroll period: manually deactivated workers
     * plus workers with a payroll exception for that month.
     */
    public function inactiveWorkerIds(int $month, int $year): array
    {
        $this->deactivatedIds ??= DB::table('inactive_workers')->pluck('worker_id')->all();

        $key = "{$year}-{$month}";

        return $this->inactiveIdsCache[$key] ??= array_values(array_unique(array_merge(
            $this->deactivatedIds,
            PayrollException::getExceptedWorkerIds($month, $year)
        )));
    }

    /**
     * Worker IDs on payroll for a period, grouped by contractor and split into
     * paid and not-yet-paid. Scoped per contractor so a worker who transferred
     * mid-month is judged only by the submissions of the contractor being
     * checked, and a paid record wins over another unpaid one.
     *
     * @return array{paid: Collection, unpaid: Collection}
     */
    public function payrollWorkerIdsByContractor(int $month, int $year): array
    {
        $key = "{$year}-{$month}";

        if (isset($this->payrollWorkerIdsCache[$key])) {
            return $this->payrollWorkerIdsCache[$key];
        }

        $rows = PayrollWorker::join('payroll_submissions', 'payroll_submissions.id', '=', 'payroll_workers.payroll_submission_id')
            ->where('payroll_submissions.month', $month)
            ->where('payroll_submissions.year', $year)
            ->get(['payroll_workers.worker_id', 'payroll_submissions.contractor_clab_no', 'payroll_submissions.status']);

        $paid = $rows->where('status', 'paid')
            ->groupBy('contractor_clab_no')
            ->map(fn ($group) => $group->pluck('worker_id')->unique()->values());

        $unpaid = $rows->where('status', '!=', 'paid')
            ->groupBy('contractor_clab_no')
            ->map(fn ($group, $clabNo) => $group->pluck('worker_id')->unique()
                ->diff($paid->get($clabNo, collect()))
                ->values());

        return $this->payrollWorkerIdsCache[$key] = ['paid' => $paid, 'unpaid' => $unpaid];
    }

    /**
     * Workers whose partial first month is paid with next month's payroll.
     */
    public function firstMonthWaivedWorkerIds($contracts, int $month, int $year): Collection
    {
        return $contracts
            ->filter(fn ($cw) => $cw->con_start && $this->proratingService->isFirstMonthWaived(Carbon::parse($cw->con_start), $month, $year))
            ->pluck('con_wkr_id');
    }

    /**
     * Payroll status of every contractor with counted workers in the period.
     *
     * @return Collection<string, array{total_workers:int, not_submitted:int, submitted_not_paid:int}>
     *                                                                                                 keyed by CLAB no
     */
    public function contractorStatus(int $month, int $year): Collection
    {
        $periodStart = Carbon::create($year, $month, 1)->startOfMonth();
        $periodEnd = $periodStart->copy()->endOfMonth();

        $contracts = ContractWorker::where('con_start', '<=', $periodEnd->toDateString())
            ->endsOnOrAfter($periodStart->toDateString())
            ->whereRaw($this->activeWorkerCondition())
            ->whereNotIn('con_wkr_id', $this->inactiveWorkerIds($month, $year))
            ->get(['con_ctr_clab_no', 'con_wkr_id', 'con_start']);

        $payrollWorkerIds = $this->payrollWorkerIdsByContractor($month, $year);

        return $contracts->groupBy('con_ctr_clab_no')->map(function ($activeContracts, $clabNo) use ($payrollWorkerIds, $month, $year) {
            $activeWorkerIds = $activeContracts->pluck('con_wkr_id');
            $paidIds = $payrollWorkerIds['paid']->get($clabNo, collect());
            $unpaidIds = $payrollWorkerIds['unpaid']->get($clabNo, collect());

            return [
                'total_workers' => $activeContracts->count(),
                'not_submitted' => $activeWorkerIds->diff($paidIds)
                    ->diff($unpaidIds)
                    ->diff($this->firstMonthWaivedWorkerIds($activeContracts, $month, $year))
                    ->count(),
                'submitted_not_paid' => $activeWorkerIds->intersect($unpaidIds)->count(),
            ];
        });
    }
}
