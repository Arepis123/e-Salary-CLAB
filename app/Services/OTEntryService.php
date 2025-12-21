<?php

namespace App\Services;

use App\Models\MonthlyOTEntry;
use App\Models\Worker;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class OTEntryService
{
    /**
     * Check if current date is within OT entry window (1-15)
     */
    public function isWithinEntryWindow(?Carbon $date = null): bool
    {
        $checkDate = $date ?? now();
        return $checkDate->day >= 1 && $checkDate->day <= 15;
    }

    /**
     * Get the entry period details
     * If today is Dec 5th, entries are for November
     */
    public function getEntryPeriod(?Carbon $date = null): array
    {
        $today = $date ?? now();
        $entryFor = $today->copy()->subMonth();

        $windowStart = $today->copy()->startOfMonth();
        $windowEnd = $today->copy()->startOfMonth()->addDays(14)->endOfDay(); // 15th 23:59:59

        $daysRemaining = 0;
        if ($this->isWithinEntryWindow($today)) {
            $daysRemaining = 15 - $today->day + 1;
        }

        return [
            'entry_month' => $entryFor->month,
            'entry_year' => $entryFor->year,
            'entry_month_name' => $entryFor->format('F Y'),
            'submission_month' => $today->month,
            'submission_year' => $today->year,
            'submission_month_name' => $today->format('F Y'),
            'window_start' => $windowStart,
            'window_end' => $windowEnd,
            'days_remaining' => $daysRemaining,
            'is_within_window' => $this->isWithinEntryWindow($today),
            'window_status' => $this->getWindowStatus($today),
        ];
    }

    /**
     * Get window status: 'open', 'closed', or 'upcoming'
     */
    public function getWindowStatus(?Carbon $date = null): string
    {
        $checkDate = $date ?? now();

        if ($this->isWithinEntryWindow($checkDate)) {
            return 'open';
        }

        return 'closed';
    }

    /**
     * Get or create OT entries for a contractor for the current entry period
     */
    public function getOrCreateEntriesForContractor(string $clabNo): Collection
    {
        $period = $this->getEntryPeriod();

        // Check if entries already exist
        $existingEntries = MonthlyOTEntry::forContractorPeriod(
            $clabNo,
            $period['entry_month'],
            $period['entry_year']
        )->get();

        // If entries exist, return them
        if ($existingEntries->isNotEmpty()) {
            return $existingEntries;
        }

        // If no entries exist, create them from workers
        return $this->createEntriesFromWorkers($clabNo, $period);
    }

    /**
     * Create OT entries for all active workers of a contractor
     */
    protected function createEntriesFromWorkers(string $clabNo, array $period): Collection
    {
        // Get active workers through contract_workers relationship
        $workers = \App\Models\ContractWorker::where('con_ctr_clab_no', $clabNo)
            ->where('con_end', '>=', now()->toDateString())
            ->with('worker')
            ->get()
            ->pluck('worker')
            ->filter() // Remove null workers
            ->unique('wkr_id'); // Remove duplicates

        $entries = collect();

        foreach ($workers as $worker) {
            $entry = MonthlyOTEntry::create([
                'contractor_clab_no' => $clabNo,
                'worker_id' => $worker->wkr_id,
                'worker_name' => $worker->wkr_name,
                'worker_passport' => $worker->wkr_passno ?? 'N/A',
                'entry_month' => $period['entry_month'],
                'entry_year' => $period['entry_year'],
                'submission_month' => $period['submission_month'],
                'submission_year' => $period['submission_year'],
                'ot_normal_hours' => 0,
                'ot_rest_hours' => 0,
                'ot_public_hours' => 0,
                'status' => 'draft',
            ]);

            $entries->push($entry);
        }

        return $entries;
    }

    /**
     * Save OT entry (update existing or create new)
     */
    public function saveEntry(array $data): MonthlyOTEntry
    {
        $period = $this->getEntryPeriod();

        // Check if within window
        if (!$period['is_within_window']) {
            throw new \Exception('OT entry window is closed. Entries can only be submitted between 1st and 15th of the month.');
        }

        // Find or create entry
        $entry = MonthlyOTEntry::updateOrCreate(
            [
                'contractor_clab_no' => $data['contractor_clab_no'],
                'worker_id' => $data['worker_id'],
                'entry_month' => $period['entry_month'],
                'entry_year' => $period['entry_year'],
            ],
            [
                'worker_name' => $data['worker_name'],
                'worker_passport' => $data['worker_passport'],
                'submission_month' => $period['submission_month'],
                'submission_year' => $period['submission_year'],
                'ot_normal_hours' => $data['ot_normal_hours'] ?? 0,
                'ot_rest_hours' => $data['ot_rest_hours'] ?? 0,
                'ot_public_hours' => $data['ot_public_hours'] ?? 0,
                'status' => 'draft',
            ]
        );

        return $entry;
    }

    /**
     * Submit all entries for a contractor (mark as submitted)
     */
    public function submitEntries(string $clabNo): bool
    {
        $period = $this->getEntryPeriod();

        // Check if within window
        if (!$period['is_within_window']) {
            throw new \Exception('OT entry window is closed. Entries can only be submitted between 1st and 15th of the month.');
        }

        $entries = MonthlyOTEntry::forContractorPeriod(
            $clabNo,
            $period['entry_month'],
            $period['entry_year']
        )->drafts()->get();

        foreach ($entries as $entry) {
            $entry->update([
                'status' => 'submitted',
                'submitted_at' => now(),
            ]);
        }

        return true;
    }

    /**
     * Lock all entries after 15th (should be run by scheduler)
     */
    public function lockExpiredEntries(): int
    {
        $today = now();

        // Only lock if it's past the 15th
        if ($today->day <= 15) {
            return 0;
        }

        // Get last month's entry period
        $lastMonth = $today->copy()->subMonth();

        // Lock all entries for last month that are still in draft or submitted status
        $count = MonthlyOTEntry::where('entry_month', $lastMonth->month)
            ->where('entry_year', $lastMonth->year)
            ->whereIn('status', ['draft', 'submitted'])
            ->update([
                'status' => 'locked',
                'locked_at' => now(),
            ]);

        return $count;
    }

    /**
     * Get entries for a specific contractor and period (for payroll submission)
     */
    public function getEntriesForPayroll(string $clabNo, int $month, int $year): Collection
    {
        return MonthlyOTEntry::forContractorPeriod($clabNo, $month, $year)
            ->submitted()
            ->get();
    }

    /**
     * Check if contractor has submitted entries for current period
     */
    public function hasSubmittedEntries(string $clabNo): bool
    {
        $period = $this->getEntryPeriod();

        $submittedCount = MonthlyOTEntry::forContractorPeriod(
            $clabNo,
            $period['entry_month'],
            $period['entry_year']
        )->submitted()->count();

        return $submittedCount > 0;
    }

    /**
     * Get submission status for contractor
     */
    public function getSubmissionStatus(string $clabNo): array
    {
        $period = $this->getEntryPeriod();

        $totalEntries = MonthlyOTEntry::forContractorPeriod(
            $clabNo,
            $period['entry_month'],
            $period['entry_year']
        )->count();

        $submittedEntries = MonthlyOTEntry::forContractorPeriod(
            $clabNo,
            $period['entry_month'],
            $period['entry_year']
        )->submitted()->count();

        $draftEntries = MonthlyOTEntry::forContractorPeriod(
            $clabNo,
            $period['entry_month'],
            $period['entry_year']
        )->drafts()->count();

        return [
            'total' => $totalEntries,
            'submitted' => $submittedEntries,
            'drafts' => $draftEntries,
            'is_submitted' => $submittedEntries > 0 && $draftEntries === 0,
            'is_partial' => $draftEntries > 0 && $submittedEntries > 0,
        ];
    }
}
