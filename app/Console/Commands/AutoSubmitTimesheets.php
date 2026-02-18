<?php

namespace App\Console\Commands;

use App\Models\MonthlyOTEntry;
use App\Models\PayrollSubmission;
use App\Models\User;
use App\Services\ContractWorkerService;
use App\Services\PayrollService;
use App\Services\SalaryProratingService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class AutoSubmitTimesheets extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payroll:auto-submit
                            {--month= : Target month (defaults to current month)}
                            {--year= : Target year (defaults to current year)}
                            {--contractor= : Specific contractor CLAB no (defaults to all)}
                            {--dry-run : Preview what would be submitted without actually submitting}
                            {--revert : Revert (delete) auto-submitted timesheets that are still in submitted status}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Auto-submit timesheets for contractors who have not submitted for the current month';

    protected ContractWorkerService $contractWorkerService;

    protected PayrollService $payrollService;

    protected SalaryProratingService $proratingService;

    public function __construct(
        ContractWorkerService $contractWorkerService,
        PayrollService $payrollService,
        SalaryProratingService $proratingService
    ) {
        parent::__construct();
        $this->contractWorkerService = $contractWorkerService;
        $this->payrollService = $payrollService;
        $this->proratingService = $proratingService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $month = (int) ($this->option('month') ?? now()->month);
        $year = (int) ($this->option('year') ?? now()->year);
        $specificContractor = $this->option('contractor');
        $dryRun = $this->option('dry-run');
        $revert = $this->option('revert');

        if ($revert) {
            return $this->handleRevert($month, $year, $specificContractor, $dryRun);
        }

        $this->info("Auto-submitting timesheets for {$month}/{$year}".($dryRun ? ' [DRY RUN]' : ''));
        $this->newLine();

        // Get all contractors (client users)
        $contractors = User::where('role', 'client')
            ->whereNotNull('contractor_clab_no')
            ->when($specificContractor, fn ($q) => $q->where('contractor_clab_no', $specificContractor))
            ->get();

        if ($contractors->isEmpty()) {
            $this->warn('No contractors found.');

            return 0;
        }

        $this->info("Found {$contractors->count()} contractor(s) to process.");
        $this->newLine();

        $submitted = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($contractors as $contractor) {
            $clabNo = $contractor->contractor_clab_no;

            try {
                $result = $this->processContractor($clabNo, $month, $year, $dryRun);

                if ($result === 'submitted') {
                    $submitted++;
                    $this->info("  ✓ {$clabNo} ({$contractor->name}) - Auto-submitted");
                } elseif ($result === 'skipped_already_submitted') {
                    $skipped++;
                    $this->line("  - {$clabNo} ({$contractor->name}) - Already submitted, skipped");
                } elseif ($result === 'skipped_no_workers') {
                    $skipped++;
                    $this->line("  - {$clabNo} ({$contractor->name}) - No active workers, skipped");
                }
            } catch (\Exception $e) {
                $errors++;
                $this->error("  ✗ {$clabNo} ({$contractor->name}) - Error: {$e->getMessage()}");
            }
        }

        $this->newLine();
        $this->info("Completed! Submitted: {$submitted}, Skipped: {$skipped}, Errors: {$errors}");

        return 0;
    }

    /**
     * Handle reverting (deleting) auto-submitted timesheets.
     * Only deletes submissions still in 'submitted' status (not yet approved/paid).
     */
    protected function handleRevert(int $month, int $year, ?string $specificContractor, bool $dryRun): int
    {
        $this->info("Reverting auto-submitted timesheets for {$month}/{$year}".($dryRun ? ' [DRY RUN]' : ''));
        $this->newLine();

        $submissions = PayrollSubmission::where('month', $month)
            ->where('year', $year)
            ->where('status', 'submitted')
            ->when($specificContractor, fn ($q) => $q->where('contractor_clab_no', $specificContractor))
            ->with('workers.transactions')
            ->get();

        if ($submissions->isEmpty()) {
            $this->warn('No submitted timesheets found to revert.');

            return 0;
        }

        $this->info("Found {$submissions->count()} submission(s) to revert.");
        $this->newLine();

        $reverted = 0;

        foreach ($submissions as $submission) {
            $workerCount = $submission->workers->count();

            if ($dryRun) {
                $this->line("  Would revert: {$submission->contractor_clab_no} - {$workerCount} worker(s)");
                $reverted++;

                continue;
            }

            $submission->workers->each(function ($worker) {
                $worker->transactions()->delete();
                $worker->delete();
            });
            $submission->delete();

            $reverted++;
            $this->info("  ✓ {$submission->contractor_clab_no} - Reverted ({$workerCount} workers removed)");
        }

        $this->newLine();
        $this->info("Completed! Reverted: {$reverted} submission(s).");

        return 0;
    }

    /**
     * Process a single contractor for auto-submission.
     */
    protected function processContractor(string $clabNo, int $month, int $year, bool $dryRun): string
    {
        // Check if contractor already has a non-draft submission for this month
        $existingSubmission = PayrollSubmission::where('contractor_clab_no', $clabNo)
            ->where('month', $month)
            ->where('year', $year)
            ->whereIn('status', ['submitted', 'approved', 'pending_payment', 'paid', 'overdue'])
            ->exists();

        if ($existingSubmission) {
            return 'skipped_already_submitted';
        }

        // Get active workers for this period
        $targetDate = Carbon::create($year, $month, 1);
        $activeWorkers = $this->contractWorkerService->getContractedWorkers($clabNo)
            ->filter(function ($worker) use ($targetDate) {
                $contract = $worker->contracts()
                    ->where('con_end', '>=', $targetDate->copy()->startOfMonth()->toDateString())
                    ->where('con_start', '<=', $targetDate->copy()->endOfMonth()->toDateString())
                    ->first();

                return $contract !== null;
            });

        // Also include workers with pending OT
        $workersWithPendingOT = $this->contractWorkerService->getWorkersWithPendingOT(
            $clabNo,
            $month,
            $year
        );

        $activeWorkers = $activeWorkers->merge($workersWithPendingOT)->unique('wkr_id')->values();

        // Get IDs of workers already in any submission this month (including drafts)
        $submittedWorkerIds = PayrollSubmission::where('contractor_clab_no', $clabNo)
            ->where('month', $month)
            ->where('year', $year)
            ->with('workers')
            ->get()
            ->flatMap(fn ($s) => $s->workers->pluck('worker_id'))
            ->unique()
            ->toArray();

        // Filter out already-submitted workers
        $remainingWorkers = $activeWorkers->filter(function ($worker) use ($submittedWorkerIds) {
            return ! in_array($worker->wkr_id, $submittedWorkerIds);
        });

        if ($remainingWorkers->isEmpty()) {
            return 'skipped_no_workers';
        }

        // Get OT entries for previous month
        $otEntryMonth = $month - 1;
        $otEntryYear = $year;
        if ($otEntryMonth < 1) {
            $otEntryMonth = 12;
            $otEntryYear--;
        }

        $monthlyOTEntries = MonthlyOTEntry::with('transactions')
            ->where('contractor_clab_no', $clabNo)
            ->where('entry_month', $otEntryMonth)
            ->where('entry_year', $otEntryYear)
            ->whereIn('status', ['submitted', 'locked'])
            ->get()
            ->keyBy('worker_id');

        // Build worker data array
        $workersData = $remainingWorkers->map(function ($worker) use ($month, $year, $monthlyOTEntries) {
            $payrollPeriodDate = Carbon::create($year, $month, 1);
            $hasActiveContract = $worker->contract_info &&
                $worker->contract_info->con_end >= $payrollPeriodDate->copy()->startOfMonth()->toDateString() &&
                $worker->contract_info->con_start <= $payrollPeriodDate->copy()->endOfMonth()->toDateString();

            // Calculate pro-rated basic salary
            $basicSalary = 0;
            $isProRated = false;
            $daysWorked = null;
            $totalDaysInMonth = null;
            $proratingNotes = null;

            if ($hasActiveContract && $worker->contract_info) {
                $proratingResult = $this->proratingService->calculateProratedSalary(
                    $worker->contract_info->con_start,
                    $worker->contract_info->con_end,
                    $month,
                    $year,
                    $worker->basic_salary ?? 1700
                );

                $basicSalary = $proratingResult['pro_rated_salary'];
                $isProRated = $proratingResult['is_pro_rated'];
                $daysWorked = $proratingResult['days_worked'];
                $totalDaysInMonth = $proratingResult['total_days'];
                $proratingNotes = $proratingResult['notes'];
            }

            // Get OT data from monthly OT entries
            $monthlyOTEntry = $monthlyOTEntries->get($worker->wkr_id);
            $otNormalHours = $monthlyOTEntry ? $monthlyOTEntry->ot_normal_hours : 0;
            $otRestHours = $monthlyOTEntry ? $monthlyOTEntry->ot_rest_hours : 0;
            $otPublicHours = $monthlyOTEntry ? $monthlyOTEntry->ot_public_hours : 0;

            // Get transactions from monthly OT entries
            $transactions = [];
            if ($monthlyOTEntry && $monthlyOTEntry->transactions) {
                $transactions = $monthlyOTEntry->transactions->map(function ($txn) {
                    return [
                        'type' => $txn->type,
                        'amount' => $txn->amount,
                        'remarks' => $txn->remarks,
                    ];
                })->toArray();
            }

            return [
                'worker_id' => $worker->wkr_id,
                'worker_name' => $worker->name,
                'worker_passport' => $worker->ic_number,
                'basic_salary' => $basicSalary,
                'is_pro_rated' => $isProRated,
                'days_worked' => $daysWorked,
                'total_days_in_month' => $totalDaysInMonth,
                'prorating_notes' => $proratingNotes,
                'ot_normal_hours' => $otNormalHours,
                'ot_rest_hours' => $otRestHours,
                'ot_public_hours' => $otPublicHours,
                'advance_payment' => 0,
                'deduction' => 0,
                'transactions' => $transactions,
                'contract_ended' => ! $hasActiveContract,
            ];
        })->values()->toArray();

        if ($dryRun) {
            $this->line("    Would submit {$clabNo} with ".count($workersData).' worker(s)');
            foreach ($workersData as $w) {
                $ot = $w['ot_normal_hours'] + $w['ot_rest_hours'] + $w['ot_public_hours'];
                $this->line("    - {$w['worker_name']}: RM {$w['basic_salary']} + OT {$ot}h");
            }

            return 'submitted';
        }

        // Delete existing draft if any (to avoid duplicates)
        PayrollSubmission::where('contractor_clab_no', $clabNo)
            ->where('month', $month)
            ->where('year', $year)
            ->where('status', 'draft')
            ->each(function ($draft) {
                $draft->workers()->each(fn ($w) => $w->transactions()->delete());
                $draft->workers()->delete();
                $draft->delete();
            });

        // Submit using PayrollService
        $this->payrollService->savePayrollSubmission($clabNo, $workersData, $month, $year);

        return 'submitted';
    }
}
