<?php

namespace App\Services;

use App\Models\ContractorWindowLog;
use App\Models\ContractorWindowSetting;
use App\Models\EntryUnlockLog;
use App\Models\MonthlyOTEntry;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ContractorWindowService
{
    /**
     * Open window for a contractor
     */
    public function openWindow(string $clabNo, int $userId, ?string $remarks = null): ContractorWindowSetting
    {
        return DB::transaction(function () use ($clabNo, $userId, $remarks) {
            // Get or create setting
            $setting = ContractorWindowSetting::firstOrCreate(
                ['contractor_clab_no' => $clabNo],
                [
                    'contractor_name' => $this->getContractorName($clabNo),
                    'is_window_open' => false,
                ]
            );

            // Update setting
            $setting->update([
                'is_window_open' => true,
                'window_opened_at' => now(),
                'last_changed_by' => $userId,
                'last_change_remarks' => $remarks,
            ]);

            // Log the action
            ContractorWindowLog::create([
                'contractor_clab_no' => $clabNo,
                'action' => 'opened',
                'changed_by' => $userId,
                'remarks' => $remarks,
                'metadata' => [
                    'previous_status' => ! $setting->wasChanged('is_window_open') ? 'open' : 'closed',
                ],
            ]);

            // Unlock locked entries for this contractor
            $this->unlockEntries($clabNo, $userId);

            // Clear cache
            Cache::forget("contractor_window:{$clabNo}");

            return $setting->fresh();
        });
    }

    /**
     * Close window for a contractor
     */
    public function closeWindow(string $clabNo, int $userId, ?string $remarks = null): ContractorWindowSetting
    {
        return DB::transaction(function () use ($clabNo, $userId, $remarks) {
            $setting = ContractorWindowSetting::firstOrCreate(
                ['contractor_clab_no' => $clabNo],
                [
                    'contractor_name' => $this->getContractorName($clabNo),
                    'is_window_open' => false,
                ]
            );

            $setting->update([
                'is_window_open' => false,
                'window_closed_at' => now(),
                'last_changed_by' => $userId,
                'last_change_remarks' => $remarks,
            ]);

            ContractorWindowLog::create([
                'contractor_clab_no' => $clabNo,
                'action' => 'closed',
                'changed_by' => $userId,
                'remarks' => $remarks,
                'metadata' => [
                    'previous_status' => ! $setting->wasChanged('is_window_open') ? 'closed' : 'open',
                ],
            ]);

            Cache::forget("contractor_window:{$clabNo}");

            return $setting->fresh();
        });
    }

    /**
     * Unlock locked entries when window is reopened
     */
    protected function unlockEntries(string $clabNo, int $userId): int
    {
        $period = app(OTEntryService::class)->getEntryPeriod();

        // Find all locked entries for this contractor in current period
        $lockedEntries = MonthlyOTEntry::where('contractor_clab_no', $clabNo)
            ->where('entry_month', $period['entry_month'])
            ->where('entry_year', $period['entry_year'])
            ->where('status', 'locked')
            ->get();

        $unlockedCount = 0;

        foreach ($lockedEntries as $entry) {
            $previousStatus = $entry->status;

            // Change status back to submitted (so it can be edited)
            $entry->update([
                'status' => 'submitted',
                'locked_at' => null,
            ]);

            // Log the unlock
            EntryUnlockLog::create([
                'monthly_ot_entry_id' => $entry->id,
                'previous_status' => $previousStatus,
                'new_status' => 'submitted',
                'unlocked_by' => $userId,
                'unlock_reason' => 'Window manually reopened by administrator',
            ]);

            $unlockedCount++;
        }

        return $unlockedCount;
    }

    /**
     * Get all contractors with their window settings
     */
    public function getAllContractorSettings(): \Illuminate\Support\Collection
    {
        // Get all unique contractor CLAB numbers from users
        $contractors = User::where('role', 'client')
            ->whereNotNull('contractor_clab_no')
            ->select('contractor_clab_no', 'name')
            ->distinct()
            ->get();

        // Get all window settings
        $settings = ContractorWindowSetting::all()->keyBy('contractor_clab_no');

        // Merge and create complete list
        return $contractors->map(function ($contractor) use ($settings) {
            $setting = $settings->get($contractor->contractor_clab_no);

            return [
                'contractor_clab_no' => $contractor->contractor_clab_no,
                'contractor_name' => $contractor->name,
                'is_window_open' => $setting ? $setting->is_window_open : false,
                'window_opened_at' => $setting?->window_opened_at,
                'window_closed_at' => $setting?->window_closed_at,
                'last_changed_by' => $setting?->lastChangedBy,
                'last_changed_at' => $setting?->updated_at,
                'last_change_remarks' => $setting?->last_change_remarks,
                'setting_id' => $setting?->id,
            ];
        })->sortBy('contractor_name');
    }

    /**
     * Get window change history for a contractor
     */
    public function getContractorHistory(string $clabNo, int $limit = 50): \Illuminate\Support\Collection
    {
        return ContractorWindowLog::forContractor($clabNo)
            ->with('changedBy')
            ->recent($limit)
            ->get();
    }

    /**
     * Helper to get contractor name from CLAB number
     */
    protected function getContractorName(string $clabNo): ?string
    {
        return User::where('contractor_clab_no', $clabNo)
            ->where('role', 'client')
            ->value('name');
    }

    /**
     * Get statistics about window usage
     */
    public function getWindowStatistics(): array
    {
        $totalContractors = User::where('role', 'client')
            ->whereNotNull('contractor_clab_no')
            ->distinct('contractor_clab_no')
            ->count();

        $openWindows = ContractorWindowSetting::where('is_window_open', true)->count();
        $closedWindows = ContractorWindowSetting::where('is_window_open', false)->count();
        $defaultWindows = $totalContractors - ($openWindows + $closedWindows);

        return [
            'total_contractors' => $totalContractors,
            'windows_open' => $openWindows,
            'windows_closed' => $closedWindows,
            'using_default' => $defaultWindows,
            'recent_changes' => ContractorWindowLog::recent(10)->with('changedBy')->get(),
        ];
    }
}
