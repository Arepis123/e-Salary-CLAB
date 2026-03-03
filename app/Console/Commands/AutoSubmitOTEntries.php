<?php

namespace App\Console\Commands;

use App\Models\MonthlyOTEntry;
use App\Models\User;
use App\Services\OTEntryService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class AutoSubmitOTEntries extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payroll:auto-submit-ot
                            {--month= : Target entry month (defaults to previous month)}
                            {--year= : Target entry year (defaults to current or previous month\'s year)}
                            {--contractor= : Specific contractor CLAB no (defaults to all)}
                            {--dry-run : Preview what would be submitted without actually submitting}
                            {--revert : Revert (set back to draft) auto-submitted OT entries that are still in submitted status}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Auto-submit OT entries for contractors who have not submitted for the previous month (runs on 16th)';

    protected OTEntryService $otEntryService;

    public function __construct(OTEntryService $otEntryService)
    {
        parent::__construct();
        $this->otEntryService = $otEntryService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $revert = $this->option('revert');
        $specificContractor = $this->option('contractor');

        // Determine the entry period (previous month by default)
        $entryPeriod = $this->resolveEntryPeriod();
        $entryMonth = $entryPeriod['month'];
        $entryYear = $entryPeriod['year'];
        $periodLabel = Carbon::create($entryYear, $entryMonth, 1)->format('F Y');

        if ($revert) {
            return $this->handleRevert($entryMonth, $entryYear, $specificContractor, $dryRun, $periodLabel);
        }

        $this->info("Auto-submitting OT entries for {$periodLabel}".($dryRun ? ' [DRY RUN]' : ''));
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
                $result = $this->processContractor($clabNo, $contractor->name, $entryMonth, $entryYear, $dryRun);

                if ($result === 'submitted') {
                    $submitted++;
                    $this->info("  ✓ {$clabNo} ({$contractor->name}) - Auto-submitted");
                } elseif ($result === 'skipped_already_submitted') {
                    $skipped++;
                    $this->line("  - {$clabNo} ({$contractor->name}) - Already submitted, skipped");
                } elseif ($result === 'skipped_no_drafts') {
                    $skipped++;
                    $this->line("  - {$clabNo} ({$contractor->name}) - No draft entries, skipped");
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
     * Process a single contractor for OT auto-submission.
     */
    protected function processContractor(string $clabNo, string $name, int $entryMonth, int $entryYear, bool $dryRun): string
    {
        // Check if contractor already has submitted/locked entries for this period
        $alreadySubmitted = MonthlyOTEntry::forContractorPeriod($clabNo, $entryMonth, $entryYear)
            ->whereIn('status', ['submitted', 'locked'])
            ->exists();

        if ($alreadySubmitted) {
            return 'skipped_already_submitted';
        }

        // Find draft entries
        $drafts = MonthlyOTEntry::forContractorPeriod($clabNo, $entryMonth, $entryYear)
            ->drafts()
            ->get();

        if ($drafts->isEmpty()) {
            return 'skipped_no_drafts';
        }

        if ($dryRun) {
            $this->line("    Would submit {$drafts->count()} entry/entries for {$clabNo}");
            foreach ($drafts as $entry) {
                $totalOT = $entry->ot_normal_hours + $entry->ot_rest_hours + $entry->ot_public_hours;
                $this->line("    - {$entry->worker_name}: OT {$totalOT}h (normal: {$entry->ot_normal_hours}, rest: {$entry->ot_rest_hours}, public: {$entry->ot_public_hours})");
            }

            return 'submitted';
        }

        $this->otEntryService->autoSubmitDraftEntries($clabNo, $entryMonth, $entryYear);

        return 'submitted';
    }

    /**
     * Revert auto-submitted OT entries back to draft status.
     * Only reverts entries still in 'submitted' status (not locked).
     */
    protected function handleRevert(int $entryMonth, int $entryYear, ?string $specificContractor, bool $dryRun, string $periodLabel): int
    {
        $this->info("Reverting auto-submitted OT entries for {$periodLabel}".($dryRun ? ' [DRY RUN]' : ''));
        $this->newLine();

        $query = MonthlyOTEntry::where('entry_month', $entryMonth)
            ->where('entry_year', $entryYear)
            ->where('status', 'submitted');

        if ($specificContractor) {
            $query->where('contractor_clab_no', $specificContractor);
        }

        $entries = $query->get();

        if ($entries->isEmpty()) {
            $this->warn('No submitted OT entries found to revert.');

            return 0;
        }

        $this->info("Found {$entries->count()} entry/entries to revert.");
        $this->newLine();

        $reverted = 0;

        foreach ($entries as $entry) {
            if ($dryRun) {
                $this->line("  Would revert: {$entry->contractor_clab_no} - {$entry->worker_name}");
                $reverted++;
                continue;
            }

            $entry->update([
                'status' => 'draft',
                'submitted_at' => null,
            ]);

            $reverted++;
            $this->info("  ✓ {$entry->contractor_clab_no} - {$entry->worker_name} reverted to draft");
        }

        $this->newLine();
        $this->info("Completed! Reverted: {$reverted} entry/entries.");

        return 0;
    }

    /**
     * Resolve the entry period (previous month by default).
     */
    protected function resolveEntryPeriod(): array
    {
        if ($this->option('month') && $this->option('year')) {
            return [
                'month' => (int) $this->option('month'),
                'year' => (int) $this->option('year'),
            ];
        }

        // Default: previous month (OT entries are always for last month)
        $previousMonth = now()->subMonth();

        return [
            'month' => (int) ($this->option('month') ?? $previousMonth->month),
            'year' => (int) ($this->option('year') ?? $previousMonth->year),
        ];
    }
}
