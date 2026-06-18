<?php

namespace App\Services;

use App\Models\MonthlyOTEntry;
use App\Models\PayrollSubmission;
use App\Models\PayrollWorker;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Detects OT hours and transactions that a client changed AFTER a timesheet
 * (PayrollSubmission) was already generated for a period, and re-syncs the
 * generated timesheet back to the live OT entry data.
 *
 * Background: a timesheet snapshots the live MonthlyOTEntry data into
 * PayrollWorker / PayrollWorkerTransaction rows at generation time. If a
 * contractor's entry window is later re-opened, the client can change OT hours
 * or add transactions that never flow back into the generated timesheet — this
 * service surfaces and reconciles that drift.
 *
 * Scope: only pre-payment submissions ('submitted', 'approved') are considered.
 * Once payment has started (pending_payment / overdue / paid) the period is
 * settled and late OT must be handled as next-month backpay.
 */
class TimesheetDriftService
{
    /**
     * Submission statuses that are still safe to re-sync (before payment).
     */
    public const CHECKABLE_STATUSES = ['submitted', 'approved'];

    /**
     * The OT entry period for a given payroll period.
     * OT for payroll month M is entered the previous month (entry_month = M-1).
     *
     * @return array{0:int,1:int} [entryMonth, entryYear]
     */
    public function otEntryPeriod(int $payrollMonth, int $payrollYear): array
    {
        $entryMonth = $payrollMonth - 1;
        $entryYear = $payrollYear;
        if ($entryMonth < 1) {
            $entryMonth = 12;
            $entryYear--;
        }

        return [$entryMonth, $entryYear];
    }

    /**
     * Build a normalized comparable key for a transaction (type|amount|remarks).
     */
    protected function txnKey(string $type, $amount, ?string $remarks): string
    {
        return $type.'|'.number_format((float) $amount, 2, '.', '').'|'.trim((string) $remarks);
    }

    /**
     * Human-readable label for a transaction, for the before/after preview.
     */
    protected function txnLabel(string $type, $amount, ?string $remarks): string
    {
        $label = ucwords(str_replace('_', ' ', $type)).': RM '.number_format((float) $amount, 2);
        $remarks = trim((string) $remarks);

        return $remarks !== '' ? "{$label} ({$remarks})" : $label;
    }

    /**
     * Compare one generated worker against its live OT entry.
     * Returns null when nothing drifted, otherwise the list of changes.
     */
    public function diffWorker(PayrollWorker $worker, MonthlyOTEntry $entry): ?array
    {
        $changes = [];

        foreach (['ot_normal_hours', 'ot_rest_hours', 'ot_public_hours'] as $field) {
            $before = round((float) $worker->$field, 2);
            $after = round((float) $entry->$field, 2);

            if ($before !== $after) {
                $changes[$field] = ['before' => $before, 'after' => $after];
            }
        }

        // Only client-entered transactions are comparable. In the snapshot these
        // are the rows with description = NULL; auto-applied configured deductions
        // always set a description and must be ignored here.
        $snapshotTxns = $worker->transactions->whereNull('description');

        $liveKeys = $entry->transactions
            ->map(fn ($t) => $this->txnKey($t->type, $t->amount, $t->remarks))
            ->sort()->values()->all();
        $snapshotKeys = $snapshotTxns
            ->map(fn ($t) => $this->txnKey($t->type, $t->amount, $t->remarks))
            ->sort()->values()->all();

        if ($liveKeys !== $snapshotKeys) {
            $changes['transactions'] = [
                'before' => $snapshotTxns
                    ->map(fn ($t) => $this->txnLabel($t->type, $t->amount, $t->remarks))
                    ->values()->all(),
                'after' => $entry->transactions
                    ->map(fn ($t) => $this->txnLabel($t->type, $t->amount, $t->remarks))
                    ->values()->all(),
            ];
        }

        return empty($changes) ? null : [
            'worker_id' => $worker->worker_id,
            'worker_name' => $worker->worker_name,
            'worker_passport' => $worker->worker_passport,
            'changes' => $changes,
        ];
    }

    /**
     * Get all drifted workers for a single submission.
     *
     * @return array<int,array> list of per-worker diffs (see diffWorker)
     */
    public function getSubmissionDrift(PayrollSubmission $submission): array
    {
        // Ensure the relations we compare against are loaded.
        $workers = $submission->relationLoaded('workers')
            ? $submission->workers
            : $submission->workers()->with('transactions')->get();

        if ($workers->isEmpty()) {
            return [];
        }

        [$otMonth, $otYear] = $this->otEntryPeriod($submission->month, $submission->year);

        $otEntries = MonthlyOTEntry::with('transactions')
            ->where('contractor_clab_no', $submission->contractor_clab_no)
            ->where('entry_month', $otMonth)
            ->where('entry_year', $otYear)
            ->whereIn('worker_id', $workers->pluck('worker_id')->all())
            ->get()
            ->keyBy('worker_id');

        $drifts = [];
        foreach ($workers as $worker) {
            $entry = $otEntries->get($worker->worker_id);
            if (! $entry) {
                continue; // no live entry to compare against
            }

            $diff = $this->diffWorker($worker, $entry);
            if ($diff !== null) {
                $drifts[] = $diff;
            }
        }

        return $drifts;
    }

    /**
     * Find every contractor whose generated timesheet for the period has drifted
     * from the live OT data.
     *
     * @return Collection<int,array{clab_no:string,submission_id:int,status:string,drifted_workers:int,drifts:array}>
     */
    public function getDriftedContractors(int $month, int $year): Collection
    {
        $submissions = PayrollSubmission::where('month', $month)
            ->where('year', $year)
            ->whereIn('status', self::CHECKABLE_STATUSES)
            ->with('workers.transactions')
            ->get();

        if ($submissions->isEmpty()) {
            return collect();
        }

        // The OT entry period is the same for every submission on this page, so
        // load all relevant entries in ONE query (keyed by "clab|worker") instead
        // of running a separate query per submission.
        [$otMonth, $otYear] = $this->otEntryPeriod($month, $year);

        $otEntries = MonthlyOTEntry::with('transactions')
            ->whereIn('contractor_clab_no', $submissions->pluck('contractor_clab_no')->unique()->all())
            ->where('entry_month', $otMonth)
            ->where('entry_year', $otYear)
            ->get()
            ->keyBy(fn ($entry) => $entry->contractor_clab_no.'|'.$entry->worker_id);

        $result = collect();

        foreach ($submissions as $submission) {
            $drifts = [];

            foreach ($submission->workers as $worker) {
                $entry = $otEntries->get($submission->contractor_clab_no.'|'.$worker->worker_id);
                if (! $entry) {
                    continue;
                }

                $diff = $this->diffWorker($worker, $entry);
                if ($diff !== null) {
                    $drifts[] = $diff;
                }
            }

            if (! empty($drifts)) {
                $result->push([
                    'clab_no' => $submission->contractor_clab_no,
                    'submission_id' => $submission->id,
                    'status' => $submission->status,
                    'drifted_workers' => count($drifts),
                    'drifts' => $drifts,
                ]);
            }
        }

        return $result;
    }

    /**
     * Re-sync a generated timesheet to the live OT entry data.
     *
     * Updates OT hours and replaces the client-entered transactions on each
     * drifted worker (preserving auto-applied configured deductions), then puts
     * the submission back into the admin review queue so the final amount is
     * re-confirmed.
     *
     * @return array{synced:int}
     */
    public function resyncSubmission(PayrollSubmission $submission): array
    {
        if (! in_array($submission->status, self::CHECKABLE_STATUSES, true)) {
            throw new \RuntimeException('This submission can no longer be re-synced because payment has started.');
        }

        return DB::transaction(function () use ($submission) {
            $workers = $submission->workers()->with('transactions')->get();

            if ($workers->isEmpty()) {
                return ['synced' => 0];
            }

            [$otMonth, $otYear] = $this->otEntryPeriod($submission->month, $submission->year);

            $otEntries = MonthlyOTEntry::with('transactions')
                ->where('contractor_clab_no', $submission->contractor_clab_no)
                ->where('entry_month', $otMonth)
                ->where('entry_year', $otYear)
                ->whereIn('worker_id', $workers->pluck('worker_id')->all())
                ->get()
                ->keyBy('worker_id');

            $synced = 0;
            foreach ($workers as $worker) {
                $entry = $otEntries->get($worker->worker_id);
                if (! $entry || $this->diffWorker($worker, $entry) === null) {
                    continue;
                }

                // Pull OT hours from the live entry.
                $worker->ot_normal_hours = $entry->ot_normal_hours;
                $worker->ot_rest_hours = $entry->ot_rest_hours;
                $worker->ot_public_hours = $entry->ot_public_hours;

                // Replace only client-entered transactions; keep configured
                // deductions (description IS NOT NULL) intact.
                $worker->transactions()->whereNull('description')->delete();
                foreach ($entry->transactions as $txn) {
                    $worker->transactions()->create([
                        'type' => $txn->type,
                        'amount' => $txn->amount,
                        'remarks' => $txn->remarks,
                    ]);
                }

                // No-op unless auto-calculations are enabled.
                $worker->calculateSalary(0);
                $worker->save();

                $synced++;
            }

            if ($synced === 0) {
                return ['synced' => 0];
            }

            // When the system auto-calculates, refresh the payroll amount.
            if (config('payroll.use_auto_calculations', false)) {
                $submission->admin_final_amount = $submission->workers()->sum('total_payment');
            }

            // Bounce an already-approved timesheet back into the review queue so
            // the admin re-confirms the final amount against the new data.
            if ($submission->status === 'approved') {
                $submission->status = 'submitted';
                $submission->admin_reviewed_by = null;
                $submission->admin_reviewed_at = null;
            }

            $submission->save();

            return ['synced' => $synced];
        });
    }
}
