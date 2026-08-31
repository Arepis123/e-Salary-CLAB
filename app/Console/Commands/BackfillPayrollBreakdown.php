<?php

namespace App\Console\Commands;

use App\Exceptions\BreakdownFileParseException;
use App\Models\PayrollSubmission;
use App\Services\BreakdownFileParser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Re-parses the breakdown spreadsheets already on disk and records their
 * itemised totals against the submission, so the client payment breakdown can
 * show real employer EPF / SOCSO / EIS / HRDF figures instead of estimates.
 *
 * Only admin_breakdown is ever written. Billing figures are never touched.
 */
class BackfillPayrollBreakdown extends Command
{
    protected $signature = 'payroll:backfill-breakdown
        {--dry-run : Inventory the files and project the run time without writing anything}
        {--check : Audit every file against admin_final_amount and report, without writing anything}
        {--csv= : With --check, write the full audit to this CSV path}
        {--limit= : Only process this many submissions}
        {--tolerance=0.05 : Ringgit difference allowed between the parsed total and admin_final_amount}
        {--force : Re-parse submissions that already have a stored breakdown}';

    protected $description = 'Backfill admin_breakdown from the payroll breakdown files already stored on disk';

    public function handle(BreakdownFileParser $parser): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $tolerance = (float) $this->option('tolerance');

        $check = (bool) $this->option('check');

        $query = PayrollSubmission::query()
            ->whereNotNull('breakdown_file_path')
            ->where('admin_final_amount', '>', 0)
            ->orderBy('id');

        // An audit looks at everything, including rows already backfilled.
        if (! $this->option('force') && ! $check) {
            $query->whereNull('admin_breakdown');
        }

        if ($limit = $this->option('limit')) {
            $query->limit((int) $limit);
        }

        $submissions = $query->get(['id', 'contractor_clab_no', 'month', 'year', 'breakdown_file_path', 'admin_final_amount']);

        if ($submissions->isEmpty()) {
            $this->info('Nothing to do: no submissions with a breakdown file are missing their itemisation.');

            return self::SUCCESS;
        }

        $this->info($submissions->count().' submission(s) with a breakdown file to consider.');

        // Split by whether the file is actually on this machine.
        $present = [];
        $missing = [];

        foreach ($submissions as $submission) {
            if (Storage::disk('local')->exists($submission->breakdown_file_path)) {
                $present[] = [
                    'submission' => $submission,
                    'size' => Storage::disk('local')->size($submission->breakdown_file_path),
                ];
            } else {
                $missing[] = $submission;
            }
        }

        $this->line('  files present on disk : '.count($present));
        $this->line('  files missing on disk : '.count($missing));

        if ($present === []) {
            $this->warn('None of the breakdown files are present on this machine — nothing can be parsed here.');

            return self::SUCCESS;
        }

        if ($check) {
            return $this->audit($parser, $present, $tolerance);
        }

        if ($dryRun) {
            return $this->project($parser, $present);
        }

        return $this->backfill($parser, $present, $missing, $tolerance);
    }

    /**
     * Audit every stored file against the amount the client was billed.
     *
     * Two things go wrong in practice and both are invisible from the UI:
     * a submission can carry a spreadsheet that was never the basis for its
     * approved amount, and the same combined workbook can end up attached to
     * several different contractors.
     *
     * @param  array<int, array{submission: PayrollSubmission, size: int}>  $present
     */
    protected function audit(BreakdownFileParser $parser, array $present, float $tolerance): int
    {
        $this->newLine();
        $this->info('Auditing '.count($present).' file(s) — nothing will be written.');

        $bar = $this->output->createProgressBar(count($present));
        $bar->start();

        $reconciled = [];
        $mismatched = [];
        $failed = [];
        $fingerprints = [];
        $rows = [];

        foreach ($present as $entry) {
            $submission = $entry['submission'];
            $path = Storage::disk('local')->path($submission->breakdown_file_path);
            $billed = (float) $submission->admin_final_amount;

            try {
                $breakdown = $parser->parse($path);
            } catch (\Throwable $e) {
                $failed[] = [$submission->id, $submission->contractor_clab_no, $submission->month.'/'.$submission->year, substr($e->getMessage(), 0, 55)];
                $rows[] = [$submission->id, $submission->contractor_clab_no, $submission->month.'/'.$submission->year, '', number_format($billed, 2), '', 'parse failed'];
                $bar->advance();

                continue;
            }

            $difference = $breakdown['total'] - $billed;
            $ok = abs($difference) <= $tolerance;

            $rows[] = [
                $submission->id,
                $submission->contractor_clab_no,
                $submission->month.'/'.$submission->year,
                number_format($breakdown['total'], 2),
                number_format($billed, 2),
                number_format($difference, 2),
                $ok ? 'reconciles' : 'MISMATCH',
            ];

            if ($ok) {
                $reconciled[] = $submission->id;
            } else {
                $mismatched[] = [
                    $submission->id,
                    $submission->contractor_clab_no,
                    $submission->month.'/'.$submission->year,
                    number_format($breakdown['total'], 2),
                    number_format($billed, 2),
                    number_format($difference, 2),
                ];
            }

            // Identical parsed contents across different contractors means the
            // same workbook was attached to more than one submission.
            $fingerprint = number_format($breakdown['gross_salary'], 2).'|'.number_format($breakdown['total'], 2);
            $fingerprints[$fingerprint][] = $submission->contractor_clab_no.' (#'.$submission->id.')';

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $total = count($present);
        $this->info('Audit complete.');
        $this->line('  reconciles with the billed amount : '.count($reconciled).' of '.$total);
        $this->line('  does NOT reconcile                : '.count($mismatched));
        $this->line('  could not be parsed               : '.count($failed));

        if ($mismatched !== []) {
            $this->newLine();
            $this->warn('Files whose total does not match what the client was billed:');
            $this->table(['ID', 'Contractor', 'Period', 'Parsed total', 'Billed', 'Difference'], $mismatched);
            $this->line('A backfill run skips these; the breakdown keeps showing calculated figures.');
        }

        $shared = array_filter($fingerprints, fn ($owners) => count(array_unique($owners)) > 1);

        if ($shared !== []) {
            $this->newLine();
            $this->warn('The same workbook contents appear under more than one submission:');
            $duplicateRows = [];
            foreach ($shared as $fingerprint => $owners) {
                [$gross, $sum] = explode('|', $fingerprint);
                $duplicateRows[] = [$gross, $sum, count($owners), implode(', ', array_unique($owners))];
            }
            $this->table(['Gross salary', 'File total', 'Submissions', 'Attached to'], $duplicateRows);
            $this->line('This usually means a combined workbook was uploaded instead of the contractor\'s own sheet.');
        }

        if ($failed !== []) {
            $this->newLine();
            $this->warn('Could not be parsed:');
            $this->table(['ID', 'Contractor', 'Period', 'Reason'], $failed);
        }

        if ($csvPath = $this->option('csv')) {
            $handle = fopen($csvPath, 'w');
            fputcsv($handle, ['submission_id', 'contractor', 'period', 'parsed_total', 'billed_amount', 'difference', 'status']);
            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }
            fclose($handle);
            $this->newLine();
            $this->info('Full audit written to '.$csvPath);
        }

        $this->newLine();
        $this->comment('Check only — nothing was written.');

        return self::SUCCESS;
    }

    /**
     * Dry run: time a spread of real files and project the full run.
     *
     * @param  array<int, array{submission: PayrollSubmission, size: int}>  $present
     */
    protected function project(BreakdownFileParser $parser, array $present): int
    {
        $totalBytes = array_sum(array_column($present, 'size'));

        $this->newLine();
        $this->line('  total size            : '.number_format($totalBytes / 1048576, 1).' MB');

        // Sample across the size range so the projection is not skewed by the
        // small files, which parse an order of magnitude faster.
        usort($present, fn ($a, $b) => $a['size'] <=> $b['size']);

        $count = count($present);
        $picks = array_unique(array_map(
            fn ($fraction) => (int) round($fraction * ($count - 1)),
            [0, 0.25, 0.5, 0.75, 1]
        ));

        $this->newLine();
        $this->info('Timing '.count($picks).' sample file(s):');

        $rows = [];
        $times = [];
        $reconciles = 0;
        $mismatched = 0;
        $failed = 0;

        foreach ($picks as $index) {
            $submission = $present[$index]['submission'];
            $size = $present[$index]['size'];
            $path = Storage::disk('local')->path($submission->breakdown_file_path);

            $start = microtime(true);

            try {
                $breakdown = $parser->parse($path);
                $elapsed = microtime(true) - $start;
                $times[] = $elapsed;

                $diff = $breakdown['total'] - (float) $submission->admin_final_amount;
                abs($diff) <= 0.05 ? $reconciles++ : $mismatched++;

                $rows[] = [
                    $submission->id,
                    number_format($size / 1024, 1).' KB',
                    number_format($elapsed, 2).'s',
                    number_format($breakdown['total'], 2),
                    number_format((float) $submission->admin_final_amount, 2),
                    abs($diff) <= 0.05 ? 'ok' : number_format($diff, 2),
                ];
            } catch (\Throwable $e) {
                $failed++;
                $rows[] = [
                    $submission->id,
                    number_format($size / 1024, 1).' KB',
                    '-',
                    'FAILED',
                    number_format((float) $submission->admin_final_amount, 2),
                    substr($e->getMessage(), 0, 40),
                ];
            }
        }

        $this->table(['ID', 'Size', 'Parse', 'Parsed total', 'admin_final', 'Reconciles'], $rows);

        if ($times === []) {
            $this->error('Every sample failed to parse; cannot project a run time.');

            return self::FAILURE;
        }

        $average = array_sum($times) / count($times);

        $this->newLine();
        $this->info('Projection for '.$count.' file(s):');
        $this->line('  average parse   : '.number_format($average, 2).'s per file');
        $this->line('  slowest sample  : '.number_format(max($times), 2).'s');
        $this->line('  expected run    : '.$this->humanise($average * $count));
        $this->line('  worst case      : '.$this->humanise(max($times) * $count));

        if ($mismatched > 0) {
            $this->newLine();
            $this->warn($mismatched.' of '.count($picks).' sampled file(s) did not reconcile to admin_final_amount.');
            $this->line('A real run skips those rather than storing an itemisation that contradicts the billed amount.');
        }

        if ($failed > 0) {
            $this->warn($failed.' of '.count($picks).' sampled file(s) could not be parsed at all.');
        }

        $this->newLine();
        $this->comment('Dry run — nothing was written.');

        return self::SUCCESS;
    }

    /**
     * @param  array<int, array{submission: PayrollSubmission, size: int}>  $present
     * @param  array<int, PayrollSubmission>  $missing
     */
    protected function backfill(BreakdownFileParser $parser, array $present, array $missing, float $tolerance): int
    {
        $stored = 0;
        $skipped = [];
        $failures = [];

        $bar = $this->output->createProgressBar(count($present));
        $bar->start();

        $started = microtime(true);

        foreach ($present as $entry) {
            $submission = $entry['submission'];
            $path = Storage::disk('local')->path($submission->breakdown_file_path);

            try {
                $breakdown = $parser->parse($path);
            } catch (BreakdownFileParseException $e) {
                $failures[] = [$submission->id, 'unreadable', $e->getMessage()];
                $bar->advance();

                continue;
            } catch (\Throwable $e) {
                $failures[] = [$submission->id, 'error', substr($e->getMessage(), 0, 60)];
                $bar->advance();

                continue;
            }

            // The stored file is not always the one the approved amount came
            // from — some submissions carry a combined workbook, or the admin
            // typed a different figure. Recording an itemisation that
            // contradicts the billed amount would be worse than recording
            // none, so those are left for a human to look at.
            $difference = $breakdown['total'] - (float) $submission->admin_final_amount;

            if (abs($difference) > $tolerance) {
                $skipped[] = [
                    $submission->id,
                    $submission->contractor_clab_no,
                    $submission->month.'/'.$submission->year,
                    number_format($breakdown['total'], 2),
                    number_format((float) $submission->admin_final_amount, 2),
                    number_format($difference, 2),
                ];
                $bar->advance();

                continue;
            }

            // Write the itemisation only, and leave updated_at alone so the
            // audit trail on historical submissions is not disturbed.
            $submission->timestamps = false;
            $submission->admin_breakdown = $breakdown;
            $submission->saveQuietly();

            $stored++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info('Backfill complete in '.$this->humanise(microtime(true) - $started).'.');
        $this->line('  itemisation stored        : '.$stored);
        $this->line('  skipped, did not reconcile: '.count($skipped));
        $this->line('  failed to parse           : '.count($failures));
        $this->line('  file missing on disk      : '.count($missing));

        if ($skipped !== []) {
            $this->newLine();
            $this->warn('Skipped — the stored file does not match the approved amount:');
            $this->table(['ID', 'Contractor', 'Period', 'Parsed total', 'admin_final', 'Difference'], $skipped);
            $this->line('These keep showing calculated figures on the breakdown, which is the safe default.');
        }

        if ($failures !== []) {
            $this->newLine();
            $this->warn('Failed to parse:');
            $this->table(['ID', 'Reason', 'Detail'], $failures);
        }

        return self::SUCCESS;
    }

    protected function humanise(float $seconds): string
    {
        if ($seconds < 60) {
            return number_format($seconds, 1).' seconds';
        }

        return number_format($seconds / 60, 1).' minutes';
    }
}
