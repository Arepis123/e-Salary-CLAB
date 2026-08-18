<?php

namespace App\Services;

use App\Models\ContractorConfiguration;
use App\Models\PayrollSubmission;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Aggregates the executive-level figures shown on the management dashboard.
 *
 * The important distinction this service draws — and the reason it exists
 * separately from the admin dashboard's queries — is between:
 *
 *  - COMPANY REVENUE: service charge (RM200 per billable worker) + SST (8% of
 *    that) + late penalties. This is money the business earns.
 *  - PAYROLL VOLUME: the wages and statutory contributions collected from a
 *    client and passed straight through to workers. This is not income.
 *
 * Everything here is read-only and aggregated in SQL wherever possible.
 * PayrollSubmission's client_total / total_due accessors are deliberately NOT
 * used: each one calls ContractorConfigurationService::getContractorConfiguration()
 * (a firstOrCreate per submission) and PayrollWorker::hasContractEnded() (a
 * worker_db query per worker), which is fine for one invoice page but would be
 * thousands of queries across a 12-month view. The arithmetic below mirrors
 * those accessors exactly, with the lookups batched.
 */
class ManagementMetricsService
{
    /** Service charge billed per billable worker, in RM. */
    public const SERVICE_CHARGE_PER_WORKER = 200;

    /** SST charged on the service charge. */
    public const SST_RATE = 0.08;

    /** Late-payment penalty, charged on the client total. */
    public const PENALTY_RATE = 0.08;

    /**
     * Day of the month the auto-submit job closes the submission window.
     * Mirrors \App\Livewire\Admin\MissingSubmissions::AUTO_SUBMIT_DAY.
     */
    public const AUTO_SUBMIT_DAY = 16;

    /** Submission statuses that represent real billed payroll (drafts excluded). */
    public const BILLED_STATUSES = ['submitted', 'approved', 'pending_payment', 'paid', 'overdue'];

    /** Submission statuses that are billed but not yet settled. */
    public const OUTSTANDING_STATUSES = ['approved', 'pending_payment', 'overdue'];

    /**
     * How long an aggregate stays cached. Management figures are reviewed, not
     * transacted against, so a short staleness window is an acceptable trade
     * for keeping these multi-month scans off every page load. Every payload
     * carries a `generated_at` so the dashboard can show its age.
     */
    protected int $cacheTtl = 900;

    /**
     * Bump this whenever the shape of a cached payload changes. Without it, a
     * deploy that adds or renames a key serves the old array to the new views
     * until the TTL lapses, which surfaces as undefined-key errors.
     */
    protected string $cacheVersion = 'v2';

    /**
     * Bypass the cache (used by the dashboard's manual refresh).
     */
    protected bool $fresh = false;

    public function fresh(bool $fresh = true): self
    {
        $this->fresh = $fresh;

        return $this;
    }

    // =====================================================================
    // Reporting period
    // =====================================================================

    /**
     * The payroll period the dashboard reports on.
     *
     * Contractors submit for the current month until auto-submit closes the
     * window on the 16th, so before that date the current month is still
     * half-formed and the previous month is the meaningful period to report.
     */
    public function periodContext(): array
    {
        $now = CarbonImmutable::now();
        $anchor = $now->day < self::AUTO_SUBMIT_DAY
            ? $now->subMonth()->startOfMonth()
            : $now->startOfMonth();

        $previous = $anchor->subMonth();

        return [
            'month' => $anchor->month,
            'year' => $anchor->year,
            'key' => $this->periodKey($anchor->year, $anchor->month),
            'label' => $anchor->format('F Y'),
            'previous_month' => $previous->month,
            'previous_year' => $previous->year,
            'previous_key' => $this->periodKey($previous->year, $previous->month),
            'previous_label' => $previous->format('F Y'),
        ];
    }

    /**
     * Sortable integer key for a month, e.g. August 2026 => 202608.
     * Lets month/year pairs be range-filtered in SQL without a date column.
     */
    protected function periodKey(int $year, int $month): int
    {
        return $year * 100 + $month;
    }

    /**
     * The last $months reporting periods, oldest first.
     *
     * @return array<int, array{year:int, month:int, key:int, label:string}>
     */
    protected function periodSeries(int $months): array
    {
        $context = $this->periodContext();
        $end = CarbonImmutable::create($context['year'], $context['month'], 1);

        $series = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $period = $end->subMonths($i);
            $series[] = [
                'year' => $period->year,
                'month' => $period->month,
                'key' => $this->periodKey($period->year, $period->month),
                'label' => $period->format('M Y'),
            ];
        }

        return $series;
    }

    // =====================================================================
    // Public metrics
    // =====================================================================

    /**
     * Row 1 — headline KPIs for the current reporting period, each against the
     * equivalent prior period.
     */
    public function revenueKpis(): array
    {
        return $this->remember('revenue-kpis', function () {
            $context = $this->periodContext();

            $submissions = $this->billedSubmissions($context['previous_key'], $context['key']);
            $revenue = $this->computeRevenue($submissions);

            $current = $this->summarise($revenue, $context['key']);
            $previous = $this->summarise($revenue, $context['previous_key']);

            // Cash is keyed on when it actually landed, not on the payroll
            // period it settles, so it uses calendar months rather than
            // reporting periods. The two are intentionally labelled apart.
            //
            // The comparison is day-aligned: month-to-date against the same
            // stretch of last month. Comparing a partial month against a whole
            // one would show a catastrophic drop every month, and clients
            // mostly pay after the 16th, so the distortion would be severe.
            $now = CarbonImmutable::now();
            $thisMonth = $now->startOfMonth();
            $lastMonth = $thisMonth->subMonth();
            $lastMonthCutoff = $lastMonth
                ->addDays(min($now->day, $lastMonth->daysInMonth) - 1)
                ->endOfDay();

            $cashThisMonth = $this->cashCollectedBetween($thisMonth, $now->endOfDay());
            $cashLastMonth = $this->cashCollectedBetween($lastMonth, $lastMonthCutoff);
            $cashLastMonthFull = $this->cashCollectedBetween($lastMonth, $lastMonth->endOfMonth());

            $receivables = $this->outstandingReceivables();

            return [
                'period_label' => $context['label'],
                'previous_period_label' => $context['previous_label'],
                'cash_month_label' => $thisMonth->format('F Y'),
                'cash_last_month_label' => $lastMonth->format('F Y'),

                'revenue' => $current['revenue'],
                'revenue_change' => $this->percentChange($current['revenue'], $previous['revenue']),
                'service_charge' => $current['service_charge'],
                'sst' => $current['sst'],
                'penalty' => $current['penalty'],

                'payroll_volume' => $current['payroll_volume'],
                'payroll_volume_change' => $this->percentChange($current['payroll_volume'], $previous['payroll_volume']),

                'cash_collected' => $cashThisMonth,
                'cash_collected_change' => $this->percentChange($cashThisMonth, $cashLastMonth),
                'cash_last_month_full' => $cashLastMonthFull,
                'cash_is_partial_month' => $now->day < $now->daysInMonth,

                'receivables' => $receivables['amount'],
                'receivables_clients' => $receivables['clients'],
                'receivables_overdue' => $receivables['overdue_amount'],

                'clients_billed' => $current['clients'],
                'clients_billed_change' => $current['clients'] - $previous['clients'],
                'billable_workers' => $current['billable_workers'],
                'billable_workers_change' => $current['billable_workers'] - $previous['billable_workers'],
                'revenue_per_client' => $current['clients'] > 0
                    ? $current['revenue'] / $current['clients']
                    : 0.0,
                'revenue_per_worker' => $current['billable_workers'] > 0
                    ? $current['revenue'] / $current['billable_workers']
                    : 0.0,

                'generated_at' => now()->toDateTimeString(),
            ];
        });
    }

    /**
     * Row 2 — 12-month revenue composition with payroll volume alongside, plus
     * the headcount and client counts that drive it.
     */
    public function trends(int $months = 12): array
    {
        return $this->remember("trends-{$months}", function () use ($months) {
            $series = $this->periodSeries($months);
            $startKey = $series[0]['key'];
            $endKey = end($series)['key'];

            $revenue = $this->computeRevenue($this->billedSubmissions($startKey, $endKey));
            $headcount = $this->headcountByPeriod($startKey, $endKey);

            $labels = [];
            $serviceCharge = [];
            $sst = [];
            $penalty = [];
            $payrollVolume = [];
            $revenueTotal = [];
            $workers = [];
            $clients = [];

            foreach ($series as $period) {
                $summary = $this->summarise($revenue, $period['key']);

                $labels[] = $period['label'];
                $serviceCharge[] = round($summary['service_charge'], 2);
                $sst[] = round($summary['sst'], 2);
                $penalty[] = round($summary['penalty'], 2);
                $payrollVolume[] = round($summary['payroll_volume'], 2);
                $revenueTotal[] = round($summary['revenue'], 2);

                $workers[] = (int) ($headcount[$period['key']]['workers'] ?? 0);
                $clients[] = (int) ($headcount[$period['key']]['clients'] ?? 0);
            }

            return [
                'labels' => $labels,
                'service_charge' => $serviceCharge,
                'sst' => $sst,
                'penalty' => $penalty,
                'payroll_volume' => $payrollVolume,
                'revenue_total' => $revenueTotal,
                'workers' => $workers,
                'clients' => $clients,
                'generated_at' => now()->toDateTimeString(),
            ];
        });
    }

    /**
     * Row 3 — receivables ageing, how long clients take to pay, how much of
     * what was billed came back, and how they paid it.
     */
    public function collectionHealth(int $months = 6): array
    {
        return $this->remember("collection-health-{$months}", function () use ($months) {
            $series = $this->periodSeries($months);
            $startKey = $series[0]['key'];
            $endKey = end($series)['key'];

            return [
                'aging' => $this->receivablesAging(),
                'payment_behaviour' => $this->paymentBehaviour($startKey, $endKey),
                'collection_rate' => $this->collectionRate($series),
                'payment_mix' => $this->paymentMix($months),
                'generated_at' => now()->toDateTimeString(),
            ];
        });
    }

    // =====================================================================
    // Revenue computation
    // =====================================================================

    /**
     * Billed (non-draft) submissions whose payroll period falls in the range.
     */
    protected function billedSubmissions(int $startKey, int $endKey): Collection
    {
        return PayrollSubmission::query()
            ->whereIn('status', self::BILLED_STATUSES)
            ->whereRaw('(year * 100 + month) between ? and ?', [$startKey, $endKey])
            ->get([
                'id', 'contractor_clab_no', 'month', 'year', 'status',
                'admin_final_amount', 'grand_total', 'has_penalty', 'penalty_amount',
                'payment_deadline', 'submitted_at', 'paid_at',
            ]);
    }

    /**
     * Resolve billable workers, exemptions, charges and penalty for each
     * submission, using batched lookups rather than per-model accessors.
     *
     * @return Collection<int, array<string, mixed>> keyed by submission id
     */
    protected function computeRevenue(Collection $submissions): Collection
    {
        if ($submissions->isEmpty()) {
            return collect();
        }

        $ids = $submissions->pluck('id')->all();
        $clabNos = $submissions->pluck('contractor_clab_no')->filter()->unique()->values();

        // Total workers per submission, counted in SQL.
        $workerCounts = DB::table('payroll_workers')
            ->whereIn('payroll_submission_id', $ids)
            ->groupBy('payroll_submission_id')
            ->selectRaw('payroll_submission_id, COUNT(*) as total')
            ->pluck('total', 'payroll_submission_id');

        $excluded = $this->excludedWorkerCounts($submissions, $ids, $clabNos);

        // Exemptions default to false, matching the firstOrCreate defaults in
        // ContractorConfigurationService, so a missing row is simply not exempt.
        $configs = ContractorConfiguration::whereIn('contractor_clab_no', $clabNos)
            ->get(['contractor_clab_no', 'service_charge_exempt', 'penalty_exempt'])
            ->keyBy('contractor_clab_no');

        return $submissions->mapWithKeys(function (PayrollSubmission $submission) use ($workerCounts, $excluded, $configs) {
            $config = $configs->get($submission->contractor_clab_no);
            $serviceChargeExempt = (bool) ($config->service_charge_exempt ?? false);
            $penaltyExempt = (bool) ($config->penalty_exempt ?? false);

            $totalWorkers = (int) ($workerCounts[$submission->id] ?? 0);
            $billableWorkers = max(0, $totalWorkers - (int) ($excluded[$submission->id] ?? 0));

            $serviceCharge = $serviceChargeExempt
                ? 0.0
                : $billableWorkers * self::SERVICE_CHARGE_PER_WORKER;
            $sst = $serviceCharge * self::SST_RATE;

            // grand_total is deprecated in favour of admin_final_amount but is
            // still the only figure on legacy submissions.
            $payrollVolume = (float) ($submission->admin_final_amount ?? $submission->grand_total ?? 0);
            $clientTotal = $payrollVolume + $serviceCharge + $sst;

            $penalty = $this->penaltyFor($submission, $clientTotal, $penaltyExempt);

            return [$submission->id => [
                'period_key' => $this->periodKey($submission->year, $submission->month),
                'clab_no' => $submission->contractor_clab_no,
                'status' => $submission->status,
                'billable_workers' => $billableWorkers,
                'service_charge' => $serviceCharge,
                'sst' => $sst,
                'penalty' => $penalty,
                'revenue' => $serviceCharge + $sst + $penalty,
                'payroll_volume' => $payrollVolume,
                'client_total' => $clientTotal,
                'total_due' => $clientTotal + $penalty,
                'payment_deadline' => $submission->payment_deadline,
            ]];
        });
    }

    /**
     * Count, per submission, the workers excluded from service-charge billing.
     *
     * PayrollWorker::isExcludedFromBilling() excludes a worker only when their
     * contract ended before the payroll period AND they earned no basic salary
     * (i.e. an OT-only tail). The second condition is cheap and highly
     * selective, so only zero-salary workers need the cross-database contract
     * lookup — and those are resolved in a single grouped query.
     *
     * @return array<int, int> submission id => excluded worker count
     */
    protected function excludedWorkerCounts(Collection $submissions, array $ids, Collection $clabNos): array
    {
        $zeroSalary = DB::table('payroll_workers')
            ->whereIn('payroll_submission_id', $ids)
            ->where('basic_salary', 0)
            ->get(['payroll_submission_id', 'worker_id']);

        if ($zeroSalary->isEmpty()) {
            return [];
        }

        // MAX(con_end) matches the model's orderBy('con_end', 'desc')->first().
        $contractEnds = DB::connection('worker_db')
            ->table('contract_worker')
            ->whereIn('con_wkr_id', $zeroSalary->pluck('worker_id')->filter()->unique()->all())
            ->whereIn('con_ctr_clab_no', $clabNos->all())
            ->groupBy('con_wkr_id', 'con_ctr_clab_no')
            ->selectRaw('con_wkr_id, con_ctr_clab_no, MAX(con_end) as con_end')
            ->get()
            ->keyBy(fn ($row) => $row->con_wkr_id.'|'.$row->con_ctr_clab_no);

        $periodStarts = $submissions->mapWithKeys(fn (PayrollSubmission $s) => [
            $s->id => CarbonImmutable::create($s->year, $s->month, 1)->startOfMonth(),
        ]);
        $clabBySubmission = $submissions->pluck('contractor_clab_no', 'id');

        $excluded = [];

        foreach ($zeroSalary as $worker) {
            $periodStart = $periodStarts[$worker->payroll_submission_id] ?? null;
            $clabNo = $clabBySubmission[$worker->payroll_submission_id] ?? null;

            if (! $periodStart || ! $clabNo) {
                continue;
            }

            $contract = $contractEnds->get($worker->worker_id.'|'.$clabNo);

            if (! $contract || ! $contract->con_end) {
                continue;
            }

            if (CarbonImmutable::parse($contract->con_end)->isBefore($periodStart)) {
                $excluded[$worker->payroll_submission_id] = ($excluded[$worker->payroll_submission_id] ?? 0) + 1;
            }
        }

        return $excluded;
    }

    /**
     * Penalty owed on a submission, mirroring PayrollSubmission::getTotalDueAttribute.
     */
    protected function penaltyFor(PayrollSubmission $submission, float $clientTotal, bool $penaltyExempt): float
    {
        if ($penaltyExempt) {
            return 0.0;
        }

        // A penalty that was already applied and saved stands, paid or not.
        if ($submission->has_penalty && $submission->penalty_amount > 0) {
            return (float) $submission->penalty_amount;
        }

        if ($submission->status !== 'paid' && $this->isOverdue($submission)) {
            return $clientTotal * self::PENALTY_RATE;
        }

        return 0.0;
    }

    /**
     * Deadlines are end-of-day, so a submission only turns overdue the day after.
     */
    protected function isOverdue(PayrollSubmission $submission): bool
    {
        if (! $submission->payment_deadline || $submission->status === 'paid') {
            return false;
        }

        return now()->isAfter($submission->payment_deadline->endOfDay());
    }

    /**
     * Fold the per-submission revenue rows for one period into totals.
     */
    protected function summarise(Collection $revenue, int $periodKey): array
    {
        $rows = $revenue->where('period_key', $periodKey);

        return [
            'revenue' => (float) $rows->sum('revenue'),
            'service_charge' => (float) $rows->sum('service_charge'),
            'sst' => (float) $rows->sum('sst'),
            'penalty' => (float) $rows->sum('penalty'),
            'payroll_volume' => (float) $rows->sum('payroll_volume'),
            'billable_workers' => (int) $rows->sum('billable_workers'),
            'clients' => $rows->pluck('clab_no')->filter()->unique()->count(),
        ];
    }

    // =====================================================================
    // Cash and collection
    // =====================================================================

    protected function cashCollectedBetween(CarbonImmutable $from, CarbonImmutable $to): float
    {
        return (float) DB::table('payroll_payments')
            ->where('status', 'completed')
            ->whereBetween('completed_at', [$from, $to])
            ->sum('amount');
    }

    /**
     * Everything billed and not yet settled, at any age.
     */
    protected function outstandingReceivables(): array
    {
        $revenue = $this->computeRevenue($this->outstandingSubmissions());

        $overdue = $revenue->filter(fn ($row) => $this->isPastDeadline($row['payment_deadline']));

        return [
            'amount' => (float) $revenue->sum('total_due'),
            'clients' => $revenue->pluck('clab_no')->filter()->unique()->count(),
            'overdue_amount' => (float) $overdue->sum('total_due'),
        ];
    }

    protected function outstandingSubmissions(): Collection
    {
        return PayrollSubmission::query()
            ->whereIn('status', self::OUTSTANDING_STATUSES)
            ->get([
                'id', 'contractor_clab_no', 'month', 'year', 'status',
                'admin_final_amount', 'grand_total', 'has_penalty', 'penalty_amount',
                'payment_deadline', 'submitted_at', 'paid_at',
            ]);
    }

    protected function isPastDeadline($deadline): bool
    {
        return $deadline && now()->isAfter(CarbonImmutable::parse($deadline)->endOfDay());
    }

    /**
     * Receivables bucketed by how far past the deadline they are.
     */
    protected function receivablesAging(): array
    {
        $buckets = [
            'current' => ['label' => 'Not yet due', 'amount' => 0.0, 'count' => 0],
            '1_30' => ['label' => '1–30 days', 'amount' => 0.0, 'count' => 0],
            '31_60' => ['label' => '31–60 days', 'amount' => 0.0, 'count' => 0],
            '61_90' => ['label' => '61–90 days', 'amount' => 0.0, 'count' => 0],
            'over_90' => ['label' => 'Over 90 days', 'amount' => 0.0, 'count' => 0],
        ];

        $today = CarbonImmutable::now()->startOfDay();

        foreach ($this->computeRevenue($this->outstandingSubmissions()) as $row) {
            $deadline = $row['payment_deadline'];

            if (! $deadline || ! $this->isPastDeadline($deadline)) {
                $key = 'current';
            } else {
                $daysLate = CarbonImmutable::parse($deadline)->startOfDay()->diffInDays($today);
                $key = match (true) {
                    $daysLate <= 30 => '1_30',
                    $daysLate <= 60 => '31_60',
                    $daysLate <= 90 => '61_90',
                    default => 'over_90',
                };
            }

            $buckets[$key]['amount'] += $row['total_due'];
            $buckets[$key]['count']++;
        }

        $total = array_sum(array_column($buckets, 'amount'));

        foreach ($buckets as $key => $bucket) {
            $buckets[$key]['share'] = $total > 0 ? round($bucket['amount'] / $total * 100, 1) : 0.0;
        }

        return [
            'buckets' => $buckets,
            'total' => $total,
        ];
    }

    /**
     * How long clients take to settle, and how often they hit the deadline.
     */
    protected function paymentBehaviour(int $startKey, int $endKey): array
    {
        $row = DB::table('payroll_submissions')
            ->where('status', 'paid')
            ->whereNotNull('paid_at')
            ->whereNotNull('submitted_at')
            ->whereRaw('(year * 100 + month) between ? and ?', [$startKey, $endKey])
            ->selectRaw('
                COUNT(*) as paid_count,
                AVG(DATEDIFF(paid_at, submitted_at)) as avg_days_to_pay,
                SUM(CASE WHEN payment_deadline IS NULL OR DATE(paid_at) <= DATE(payment_deadline) THEN 1 ELSE 0 END) as on_time
            ')
            ->first();

        $paidCount = (int) ($row->paid_count ?? 0);

        return [
            'paid_count' => $paidCount,
            'avg_days_to_pay' => round((float) ($row->avg_days_to_pay ?? 0), 1),
            'on_time_count' => (int) ($row->on_time ?? 0),
            'on_time_rate' => $paidCount > 0
                ? round((int) $row->on_time / $paidCount * 100, 1)
                : 0.0,
        ];
    }

    /**
     * Billed versus collected per payroll period.
     *
     * Payments join back to their submission, so collections are attributed to
     * the period they settle rather than the month the cash arrived — which is
     * what makes the ratio meaningful.
     */
    protected function collectionRate(array $series): array
    {
        $startKey = $series[0]['key'];
        $endKey = end($series)['key'];

        $revenue = $this->computeRevenue($this->billedSubmissions($startKey, $endKey));

        $collected = DB::table('payroll_payments as pp')
            ->join('payroll_submissions as ps', 'ps.id', '=', 'pp.payroll_submission_id')
            ->where('pp.status', 'completed')
            ->whereRaw('(ps.year * 100 + ps.month) between ? and ?', [$startKey, $endKey])
            ->groupBy('ps.year', 'ps.month')
            ->selectRaw('(ps.year * 100 + ps.month) as period_key, SUM(pp.amount) as collected')
            ->pluck('collected', 'period_key');

        $labels = [];
        $billed = [];
        $received = [];
        $rate = [];

        foreach ($series as $period) {
            $periodBilled = (float) $revenue->where('period_key', $period['key'])->sum('total_due');
            $periodCollected = (float) ($collected[$period['key']] ?? 0);

            $labels[] = $period['label'];
            $billed[] = round($periodBilled, 2);
            $received[] = round($periodCollected, 2);
            $rate[] = $periodBilled > 0 ? round($periodCollected / $periodBilled * 100, 1) : 0.0;
        }

        return [
            'labels' => $labels,
            'billed' => $billed,
            'collected' => $received,
            'rate' => $rate,
        ];
    }

    /**
     * How completed payments were made — Billplz versus manually recorded.
     */
    protected function paymentMix(int $months): array
    {
        $since = CarbonImmutable::now()->startOfMonth()->subMonths($months - 1);

        $rows = DB::table('payroll_payments')
            ->where('status', 'completed')
            ->where('completed_at', '>=', $since)
            ->groupBy('payment_method')
            ->selectRaw('payment_method, COUNT(*) as count, SUM(amount) as amount')
            ->get();

        $total = (float) $rows->sum('amount');

        return $rows
            ->sortByDesc('amount')
            ->map(fn ($row) => [
                'method' => $row->payment_method ?: 'Unspecified',
                'count' => (int) $row->count,
                'amount' => (float) $row->amount,
                'share' => $total > 0 ? round((float) $row->amount / $total * 100, 1) : 0.0,
            ])
            ->values()
            ->all();
    }

    // =====================================================================
    // Headcount
    // =====================================================================

    /**
     * Distinct workers and clients on payroll for each period, in one query.
     *
     * @return array<int, array{workers:int, clients:int}> keyed by period key
     */
    protected function headcountByPeriod(int $startKey, int $endKey): array
    {
        return DB::table('payroll_workers as pw')
            ->join('payroll_submissions as ps', 'ps.id', '=', 'pw.payroll_submission_id')
            ->whereIn('ps.status', self::BILLED_STATUSES)
            ->whereRaw('(ps.year * 100 + ps.month) between ? and ?', [$startKey, $endKey])
            ->groupBy('ps.year', 'ps.month')
            ->selectRaw('
                (ps.year * 100 + ps.month) as period_key,
                COUNT(DISTINCT pw.worker_id) as workers,
                COUNT(DISTINCT ps.contractor_clab_no) as clients
            ')
            ->get()
            ->mapWithKeys(fn ($row) => [(int) $row->period_key => [
                'workers' => (int) $row->workers,
                'clients' => (int) $row->clients,
            ]])
            ->all();
    }

    // =====================================================================
    // Helpers
    // =====================================================================

    protected function percentChange(float $current, float $previous): ?float
    {
        // No baseline means the change is undefined rather than zero — the view
        // shows "no prior data" instead of a misleading 0%.
        if ($previous <= 0) {
            return null;
        }

        return round(($current - $previous) / $previous * 100, 1);
    }

    protected function remember(string $key, \Closure $callback)
    {
        $cacheKey = "management.metrics.{$this->cacheVersion}.{$key}";

        if ($this->fresh) {
            Cache::forget($cacheKey);
        }

        return Cache::remember($cacheKey, $this->cacheTtl, $callback);
    }
}
