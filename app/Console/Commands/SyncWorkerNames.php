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
 * the name it was issued under), so by default this only touches the current
 * period plus inactive_workers, which is a live registry rather than history.
 */
class SyncWorkerNames extends Command
{
    protected $signature = 'workers:sync-names
        {--month= : Period month to sync (1-12, defaults to current month)}
        {--year= : Period year to sync (defaults to current year)}
        {--worker= : Limit the sync to a single wkr_id}
        {--all : Sync every period including closed ones, and salary_adjustments history}
        {--dry-run : Show what would change without writing}';

    protected $description = 'Refresh snapshotted worker names/passports from the worker_db source of truth';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $syncAll = (bool) $this->option('all');
        $workerId = $this->option('worker');
        $month = (int) ($this->option('month') ?: now()->month);
        $year = (int) ($this->option('year') ?: now()->year);

        if ($month < 1 || $month > 12) {
            $this->error("Invalid month: {$month}. Expected 1-12.");

            return self::FAILURE;
        }

        if ($syncAll) {
            $this->warn('--all: syncing every period, including closed submissions and adjustment history.');
        } else {
            $this->info("Syncing period {$month}/{$year} (plus the inactive worker registry).");
        }

        if ($workerId) {
            $this->line("Restricted to worker #{$workerId}.");
        }

        if ($dryRun) {
            $this->warn('Dry run: no rows will be written.');
        }

        $this->newLine();

        $targets = $this->targets($syncAll, $month, $year);

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
            $canonical = Worker::whereIn('wkr_id', $referencedIds)
                ->get(['wkr_id', 'wkr_name', 'wkr_passno'])
                ->keyBy(fn ($worker) => (string) $worker->wkr_id);
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
            $written += $this->scopedQuery($change['target'], $change['worker_id'])
                ->where($change['field'], '!=', $change['new'])
                ->update([$change['field'] => $change['new']]);
        }

        $changedWorkerIds = array_values(array_unique(array_column($changes, 'worker_id')));

        $this->flushWorkerCaches($changedWorkerIds);

        $this->newLine();
        $this->info("✓ Updated {$written} row(s) and cleared the cached worker lookups.");

        Log::info('Worker name snapshots synced from worker_db', [
            'scope' => $syncAll ? 'all' : "{$month}/{$year}",
            'worker_filter' => $workerId,
            'rows_updated' => $written,
            'workers' => $changedWorkerIds,
        ]);

        return self::SUCCESS;
    }

    /**
     * The snapshot tables to sync, each with the scope that keeps closed
     * periods out of the way unless --all is passed.
     */
    protected function targets(bool $syncAll, int $month, int $year): array
    {
        $targets = [
            [
                'label' => 'monthly_ot_entries',
                'query' => fn () => $syncAll
                    ? MonthlyOTEntry::query()
                    : MonthlyOTEntry::query()->where('entry_month', $month)->where('entry_year', $year),
            ],
            [
                'label' => 'payroll_workers',
                'query' => fn () => $syncAll
                    ? PayrollWorker::query()
                    : PayrollWorker::query()->whereHas(
                        'payrollSubmission',
                        fn ($q) => $q->where('month', $month)->where('year', $year)
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
        $rows = $this->scopedQuery($target, $workerId)
            ->get(['worker_id', 'worker_name', 'worker_passport']);

        $changes = [];

        foreach ($rows->groupBy(fn ($row) => (string) $row->worker_id) as $id => $group) {
            $source = $canonical->get($id);

            if (! $source) {
                continue;
            }

            foreach (['worker_name' => $source->wkr_name, 'worker_passport' => $source->wkr_passno] as $field => $value) {
                // Never overwrite a stored value with a blank one from the source.
                if ($value === null || $value === '') {
                    continue;
                }

                $stale = $group->filter(fn ($row) => (string) $row->{$field} !== (string) $value);

                if ($stale->isEmpty()) {
                    continue;
                }

                $changes[] = [
                    'target' => $target,
                    'label' => $target['label'],
                    'worker_id' => $id,
                    'field' => $field,
                    'old' => $stale->first()->{$field} ?? '(null)',
                    'new' => $value,
                    'rows' => $stale->count(),
                ];
            }
        }

        return [$changes, $rows->count()];
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
            $clabNumbers = $clabNumbers->merge(
                ContractWorker::where('con_wkr_id', $id)->pluck('con_ctr_clab_no')
            );
        }

        $clabNumbers = $clabNumbers
            ->merge(Worker::whereIn('wkr_id', $workerIds)->pluck('wkr_currentemp'))
            ->filter()
            ->unique();

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
