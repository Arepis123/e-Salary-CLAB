<?php

namespace App\Console\Commands;

use App\Models\ContractWorker;
use App\Models\PayrollWorker;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixZeroBasicSalary extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payroll:fix-zero-basic-salary
                            {--salary=1700 : Full monthly salary to restore}
                            {--submission= : Only check a specific submission ID}
                            {--include-paid : Also fix rows in paid submissions}
                            {--apply : Write the changes (default is a dry run)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Find payroll workers saved with RM 0 basic salary that should have been paid, and restore their salary';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $salary = (float) $this->option('salary');
        $apply = (bool) $this->option('apply');
        $includePaid = (bool) $this->option('include-paid');

        $rows = PayrollWorker::with('payrollSubmission.payment')
            ->where('basic_salary', 0)
            ->when($this->option('submission'), fn ($q, $id) => $q->where('payroll_submission_id', $id))
            ->get();

        if ($rows->isEmpty()) {
            $this->info('No payroll workers with RM 0 basic salary found.');

            return 0;
        }

        // One cross-database query for every contract these rows could need,
        // instead of one lookup per row as PayrollWorker::hasContractEnded() does.
        $contracts = DB::connection('worker_db')
            ->table('contract_worker')
            ->whereIn('con_wkr_id', $rows->pluck('worker_id')->unique())
            ->selectRaw('con_wkr_id, con_ctr_clab_no, con_start, '.ContractWorker::effectiveEndSql().' as effective_end')
            ->get()
            ->groupBy(fn ($c) => $c->con_wkr_id.'|'.$c->con_ctr_clab_no);

        $results = $rows->map(function (PayrollWorker $row) use ($contracts, $salary, $includePaid) {
            $submission = $row->payrollSubmission;
            $periodStart = Carbon::create($submission->year, $submission->month, 1)->startOfMonth();
            $periodEnd = $periodStart->copy()->endOfMonth();

            $result = [
                'row' => $row,
                'period' => $periodStart->format('M Y'),
                'status' => $submission->status,
                'new_salary' => null,
                'decision' => 'skip',
                'reason' => null,
            ];

            // Same rule the timesheet uses: RM 0 is correct when the worker had
            // no contract with this contractor covering the payroll month. Those
            // rows are also what keeps an OT-only worker out of the service charge.
            $active = ($contracts->get($row->worker_id.'|'.$submission->contractor_clab_no) ?? collect())
                ->contains(fn ($c) => $c->con_start
                    && substr($c->con_start, 0, 10) <= $periodEnd->toDateString()
                    && (! $c->effective_end || substr($c->effective_end, 0, 10) >= $periodStart->toDateString()));

            if (! $active) {
                $result['reason'] = 'No active contract in period (RM 0 is correct)';

                return $result;
            }

            if ($row->is_pro_rated) {
                if (! $row->days_worked || ! $row->total_days_in_month) {
                    $result['reason'] = 'Pro-rated with no days worked';

                    return $result;
                }

                $result['new_salary'] = round($salary / $row->total_days_in_month * $row->days_worked, 2);
            } else {
                $result['new_salary'] = $salary;
            }

            $isPaid = $submission->status === 'paid' || $submission->payment !== null;

            if ($isPaid && ! $includePaid) {
                $result['decision'] = 'paid';
                $result['reason'] = 'Submission already paid (use --include-paid)';

                return $result;
            }

            $result['decision'] = 'fix';
            $result['reason'] = $isPaid ? 'Paid submission, display only' : 'Active contract, salary missing';

            return $result;
        });

        $this->table(
            ['Row ID', 'Submission', 'Period', 'Status', 'Worker', 'Name', 'New Salary', 'Decision', 'Reason'],
            $results->map(fn ($r) => [
                $r['row']->id,
                $r['row']->payroll_submission_id,
                $r['period'],
                $r['status'],
                $r['row']->worker_id,
                $r['row']->worker_name,
                $r['new_salary'] !== null ? number_format($r['new_salary'], 2) : '-',
                strtoupper($r['decision']),
                $r['reason'],
            ])
        );

        $toFix = $results->where('decision', 'fix');

        $this->newLine();
        $this->info("To fix: {$toFix->count()}");
        $this->line('Paid, not fixed: '.$results->where('decision', 'paid')->count());
        $this->line('Skipped (RM 0 is correct): '.$results->where('decision', 'skip')->count());

        if (! $apply) {
            $this->newLine();
            $this->warn('Dry run, nothing was changed. Re-run with --apply to write the changes.');

            return 0;
        }

        if ($toFix->isEmpty()) {
            return 0;
        }

        if (! $this->confirm("Update basic salary on {$toFix->count()} payroll worker rows?")) {
            return 0;
        }

        DB::transaction(function () use ($toFix) {
            foreach ($toFix as $r) {
                $row = $r['row'];
                $row->basic_salary = $r['new_salary'];

                // A no-op unless payroll.use_auto_calculations is on, in which
                // case the stored OT, EPF, SOCSO and net columns follow the new salary.
                $row->calculateSalary();
                $row->save();
            }
        });

        $this->info("Updated {$toFix->count()} rows.");

        return 0;
    }
}
