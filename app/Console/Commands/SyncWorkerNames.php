<?php

namespace App\Console\Commands;

use App\Models\ContractWorker;
use App\Models\InactiveWorker;
use App\Models\MonthlyOTEntry;
use App\Models\PayrollWorker;
use App\Models\SalaryAdjustment;
use App\Models\Worker;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Refresh the worker_name / worker_passport snapshots that this system copies
 * out of the read-only worker_db at submission time.
 *
 * Those snapshots are deliberate for closed periods (a paid payslip should keep
 * the name it was issued under), so the default scope is a rolling 12-month
 * window plus inactive_workers, which is a live registry rather than history.
 * Anything older than the window is only reachable with --all.
 */
class SyncWorkerNames extends Command
{
    protected $signature = 'workers:sync-names
        {--months=12 : How many months back to sync, counting the current one (default 12)}
        {--month= : Sync one specific period month (1-12); requires --year to be meaningful}
        {--year= : Sync one specific period year, pinning the window to a single month}
        {--worker= : Limit the sync to a single wkr_id}
        {--all : Sync every period including closed ones, and salary_adjustments history}
        {--dry-run : Show what would change without writing}';

    protected $description = 'Refresh snapshotted worker names/passports from the worker_db source of truth';

    /** Max IDs per whereIn against worker_db. */
    protected const ID_BATCH = 5000;

    /** Rows read per page when comparing snapshots, keeping memory flat. */
    protected const ROW_CHUNK = 2000;

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $syncAll = (bool) $this->option('all');
        $workerId = $this->option('worker');

        $periods = $this->resolvePeriods();

        if ($periods === null) {
            return self::FAILURE;
        }

        $scopeLabel = $syncAll
            ? 'all'
            : collect($periods)->map(fn ($p) => "{$p['month']}/{$p['year']}")->implode(', ');

        if ($syncAll) {
            $this->warn('--all: syncing every period, including closed submissions and adjustment history.');
        } else {
            $this->info("Syncing period(s) {$scopeLabel} (plus the inactive worker registry).");
        }

        if ($workerId) {
            $this->line("Restricted to worker #{$workerId}.");
        }

        if ($dryRun) {
            $this->warn('Dry run: no rows will be written.');
        }

        $this->newLine();

        $targets = $this->targets($syncAll, $periods);

        // One pass to find every worker referenced by the rows in scope, so the
        // external database is queried once instead of per table.
        $referencedIds = collect();
        foreach ($targets as $target) {
            $referencedIds = $referencedIds->merge(
                $this->scopedQuery($target, $workerId)->distinct()->pluck('worker_id')
            );
        }

        $referencedIds = $referencedIds->map(fn ($id) => (string) $id)->unique()->values();

        if ($referencedIds->isEmpty()) {
            $this->info('Nothing in scope. No rows to sync.');

            return self::SUCCESS;
        }

        $this->line("Found {$referencedIds->count()} distinct worker(s) referenced in scope.");

        try {
            // Batched so a large scope never exceeds the prepared-statement
            // placeholder limit (~65K) or builds one huge IN list.
            $canonical = collect();
            foreach ($referencedIds->chunk(self::ID_BATCH) as $batch) {
                Worker::whereIn('wkr_id', $batch->values())
                    ->toBase()
                    ->get(['wkr_id', 'wkr_name', 'wkr_passno'])
                    ->each(fn ($worker) => $canonical->put((string) $worker->wkr_id, $worker));
            }
        } catch (\Throwable $e) {
            $this->error('Could not read from the worker_db connection.');
            $this->error("Error: {$e->getMessage()}");

            return self::FAILURE;
        }

        $missing = $referencedIds->reject(fn ($id) => $canonical->has($id));

        if ($missing->isNotEmpty()) {
            $this->warn("{$missing->count()} worker(s) no longer exist in worker_db; their snapshots are left untouched:");
            $this->line('  '.$missing->take(20)->implode(', ').($missing->count() > 20 ? ', ...' : ''));
        }

        $this->newLine();

        $changes = [];
        $rowsByTable = [];

        foreach ($targets as $target) {
            [$tableChanges, $rowCount] = $this->collectChanges($target, $workerId, $canonical);

            $rowsByTable[$target['label']] = $rowCount;

            foreach ($tableChanges as $change) {
                $changes[] = $change;
            }
        }

        if (empty($changes)) {
            $this->info('✓ Every snapshot in scope already matches worker_db. Nothing to do.');
            $this->renderScope($rowsByTable);

            return self::SUCCESS;
        }

        $this->table(
            ['Table', 'Worker', 'Field', 'Stored', 'Source', 'Rows'],
            array_map(fn ($c) => [
                $c['label'],
                $c['worker_id'],
                $c['field'],
                $c['old'],
                $c['new'],
                $c['rows'],
            ], $changes)
        );

        $affectedRows = array_sum(array_column($changes, 'rows'));
        $affectedWorkers = count(array_unique(array_column($changes, 'worker_id')));

        $this->newLine();
        $this->info("{$affectedRows} row(s) across {$affectedWorkers} worker(s) are out of date.");

        if ($dryRun) {
            $this->warn('Dry run complete. Re-run without --dry-run to apply.');

            return self::SUCCESS;
        }

        if ($this->input->isInteractive() && ! $this->confirm('Apply these updates?', true)) {
            $this->info('Cancelled. No changes made.');

            return self::SUCCESS;
        }

        $written = 0;

        foreach ($changes as $change) {
            $field = $change['field']; // fixed whitelist: worker_name / worker_passport

            // BINARY because the columns are utf8mb4_unicode_ci: a plain != treats
            // "MARUF MRIDHA" and "Maruf Mridha" as equal, so case-only renames were
            // detected every night but never written. NULLs never match !=, so
            // they need their own branch.
            $written += $this->scopedQuery($change['target'], $change['worker_id'])
                ->where(fn ($q) => $q->whereNull($field)->orWhereRaw("BINARY {$field} <> ?", [$change['new']]))
                ->update([$field => $change['new']]);
        }

        $changedWorkerIds = array_values(array_unique(array_column($changes, 'worker_id')));

        $this->flushWorkerCaches($changedWorkerIds);

        $this->newLine();
        $this->info("✓ Updated {$written} row(s) and cleared the cached worker lookups.");

        Log::info('Worker name snapshots synced from worker_db', [
            'scope' => $scopeLabel,
            'worker_filter' => $workerId,
            'rows_updated' => $written,
            'workers' => $changedWorkerIds,
        ]);

        return self::SUCCESS;
    }

    /**
     * Work out which periods are in scope.
     *
     * The default is a rolling window ending at the current month. A single
     * month is rarely the right default here: OT for month M is entered during
     * days 1-15 of M+1, so the period actually being worked on is the previous
     * one, and payment/receipt activity trails it by another month.
     *
     * @return array<int, array{month: int, year: int}>|null null on invalid input
     */
    protected function resolvePeriods(): ?array
    {
        $month = $this->option('month');
        $year = $this->option('year');

        // An explicit --month/--year pins the window to that single period.
        if ($month !== null || $year !== null) {
            $month = (int) ($month ?: now()->month);
            $year = (int) ($year ?: now()->year);

            if ($month < 1 || $month > 12) {
                $this->error("Invalid month: {$month}. Expected 1-12.");

                return null;
            }

            return [['month' => $month, 'year' => $year]];
        }

        $months = (int) $this->option('months');

        if ($months < 1) {
            $this->error("Invalid --months: {$months}. Expected 1 or more.");

            return null;
        }

        $cursor = now()->startOfMonth();

        return collect(range(0, $months - 1))
            ->map(fn ($offset) => [
                'month' => (int) $cursor->copy()->subMonths($offset)->month,
                'year' => (int) $cursor->copy()->subMonths($offset)->year,
            ])
            ->all();
    }

    /**
     * Constrain a query to the periods in scope, using the given column names.
     *
     * @param  array<int, array{month: int, year: int}>  $periods
     */
    protected function scopeToPeriods($query, array $periods, string $monthColumn, string $yearColumn)
    {
        return $query->where(function ($outer) use ($periods, $monthColumn, $yearColumn) {
            foreach ($periods as $period) {
                $outer->orWhere(fn ($q) => $q
                    ->where($monthColumn, $period['month'])
                    ->where($yearColumn, $period['year'])
                );
            }
        });
    }

    /**
     * The snapshot tables to sync, each with the scope that keeps closed
     * periods out of the way unless --all is passed.
     *
     * @param  array<int, array{month: int, year: int}>  $periods
     */
    protected function targets(bool $syncAll, array $periods): array
    {
        $targets = [
            [
                'label' => 'monthly_ot_entries',
                'query' => fn () => $syncAll
                    ? MonthlyOTEntry::query()
                    : $this->scopeToPeriods(MonthlyOTEntry::query(), $periods, 'entry_month', 'entry_year'),
            ],
            [
                'label' => 'payroll_workers',
                'query' => fn () => $syncAll
                    ? PayrollWorker::query()
                    : PayrollWorker::query()->whereHas(
                        'payrollSubmission',
                        fn ($q) => $this->scopeToPeriods($q, $periods, 'month', 'year')
                    ),
            ],
            // Not period-scoped: this is a live registry of who is currently
            // deactivated, so a stale name here is always wrong.
            [
                'label' => 'inactive_workers',
                'query' => fn () => InactiveWorker::query(),
            ],
        ];

        if ($syncAll) {
            // Audit history. Only rewritten on an explicit --all.
            $targets[] = [
                'label' => 'salary_adjustments',
                'query' => fn () => SalaryAdjustment::query(),
            ];
        }

        return $targets;
    }

    protected function scopedQuery(array $target, ?string $workerId)
    {
        $query = ($target['query'])();

        if ($workerId !== null && $workerId !== '') {
            $query->where('worker_id', $workerId);
        }

        return $query;
    }

    /**
     * Compare stored snapshots against worker_db and return one entry per
     * (worker, field) that drifted.
     *
     * @return array{0: array<int, array<string, mixed>>, 1: int}
     */
    protected function collectChanges(array $target, ?string $workerId, $canonical): array
    {
        $query = $this->scopedQuery($target, $workerId);
        $model = $query->getModel();
        $keyColumn = $model->getQualifiedKeyName();

        // Stream plain rows page by page (keyset on the primary key) instead of
        // hydrating every row in the window as a model. Memory stays bounded by
        // ROW_CHUNK plus one small tally per drifted (worker, field).
        $rows = $query->toBase()
            ->select([$keyColumn, 'worker_id', 'worker_name', 'worker_passport'])
            ->lazyById(self::ROW_CHUNK, $keyColumn, $model->getKeyName());

        $stale = [];
        $rowCount = 0;

        foreach ($rows as $row) {
            $rowCount++;
            $id = (string) $row->worker_id;
            $source = $canonical->get($id);

            if (! $source) {
                continue;
            }

            foreach (['worker_name' => $source->wkr_name, 'worker_passport' => $source->wkr_passno] as $field => $value) {
                // Never overwrite a stored value with a blank one from the source.
                if ($value === null || $value === '') {
                    continue;
                }

                // Whitespace-only drift (trailing/double spaces) is not a rename.
                if ($this->normalize($row->{$field}) === $this->normalize($value)) {
                    continue;
                }

                $key = $id."\0".$field;
                $stale[$key] ??= [
                    'target' => $target,
                    'label' => $target['label'],
                    'worker_id' => $id,
                    'field' => $field,
                    'old' => $row->{$field} ?? '(null)',
                    'new' => $value,
                    'rows' => 0,
                ];
                $stale[$key]['rows']++;
            }
        }

        return [array_values($stale), $rowCount];
    }

    /**
     * Comparison form of a stored value: trimmed, with any run of whitespace
     * (including non-breaking spaces) collapsed to a single space.
     */
    protected function normalize(mixed $value): string
    {
        return trim((string) preg_replace('/[\s\x{00A0}]+/u', ' ', (string) $value));
    }

    /**
     * Drop the cached lookups that bake worker names into their payload, so the
     * UI reflects the sync immediately instead of after the 1 hour TTL.
     */
    protected function flushWorkerCaches(array $workerIds): void
    {
        $clabNumbers = collect();

        foreach ($workerIds as $id) {
            Cache::forget("worker:{$id}");
        }

        // One worker_db round trip per batch rather than one per worker.
        foreach (array_chunk($workerIds, self::ID_BATCH) as $batch) {
            $clabNumbers = $clabNumbers
                ->merge(ContractWorker::whereIn('con_wkr_id', $batch)->pluck('con_ctr_clab_no'))
                ->merge(Worker::whereIn('wkr_id', $batch)->pluck('wkr_currentemp'));
        }

        $clabNumbers = $clabNumbers->filter()->unique();

        foreach ($clabNumbers as $clabNo) {
            Cache::forget("workers:contractor:{$clabNo}");
            Cache::forget("contract_workers:contractor:{$clabNo}");
            Cache::forget("contract_workers:contractor:{$clabNo}:active");
            Cache::forget("contracted_workers:contractor:{$clabNo}");
            Cache::forget("contracted_workers:contractor:{$clabNo}:active_only");
        }

        Cache::forget('workers:active');
        Cache::forget('workers:statistics');
        Cache::forget('contract_workers:active');
    }

    protected function renderScope(array $rowsByTable): void
    {
        $this->newLine();
        $this->line('Rows checked:');

        foreach ($rowsByTable as $label => $count) {
            $this->line("  {$label}: {$count}");
        }
    }
}
