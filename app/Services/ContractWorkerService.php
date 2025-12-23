<?php

namespace App\Services;

use App\Models\Contractor;
use App\Models\ContractWorker;
use App\Models\Worker;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class ContractWorkerService
{
    /**
     * Cache TTL in seconds (1 hour by default)
     */
    protected int $cacheTTL = 3600;

    /**
     * Get all active contracts with caching
     */
    public function getActiveContracts(): Collection
    {
        return Cache::remember(
            'contract_workers:active',
            $this->cacheTTL,
            fn () => ContractWorker::active()
                ->with(['contractor', 'worker'])
                ->get()
        );
    }

    /**
     * Get contracts by contractor CLAB number
     */
    public function getContractsByContractor(string $clabNo): Collection
    {
        return Cache::remember(
            "contract_workers:contractor:{$clabNo}",
            $this->cacheTTL,
            fn () => ContractWorker::byContractor($clabNo)
                ->with('worker')
                ->get()
        );
    }

    /**
     * Get active contracts by contractor CLAB number
     */
    public function getActiveContractsByContractor(string $clabNo): Collection
    {
        return Cache::remember(
            "contract_workers:contractor:{$clabNo}:active",
            $this->cacheTTL,
            fn () => ContractWorker::byContractor($clabNo)
                ->active()
                ->with('worker')
                ->get()
        );
    }

    /**
     * Get contracted workers for a specific contractor
     * Returns all workers that have contracts (active or expired)
     * Sorted by: Active Status (active first), then Country (ascending), then Name
     */
    public function getContractedWorkers(string $clabNo): Collection
    {
        return Cache::remember(
            "contracted_workers:contractor:{$clabNo}",
            $this->cacheTTL,
            function () use ($clabNo) {
                $contractWorkerIds = ContractWorker::byContractor($clabNo)
                    ->pluck('con_wkr_id');

                return Worker::whereIn('wkr_id', $contractWorkerIds)
                    ->with(['country', 'workTrade'])
                    ->get()
                    ->map(function ($worker) use ($clabNo) {
                        // Attach contract info to each worker (most recent contract)
                        $contract = ContractWorker::where('con_wkr_id', $worker->wkr_id)
                            ->where('con_ctr_clab_no', $clabNo)
                            ->orderBy('con_end', 'desc')
                            ->first();

                        $worker->contract_info = $contract;

                        return $worker;
                    })
                    ->sortBy([
                        // Sort by active status first (active = 0, inactive = 1, so active comes first)
                        fn ($worker) => $worker->contract_info && $worker->contract_info->isActive() ? 0 : 1,
                        // Then by country name (ascending)
                        fn ($worker) => $worker->country?->cty_desc ?? 'ZZZ',
                        // Then by worker name
                        fn ($worker) => $worker->wkr_name,
                    ])
                    ->values(); // Reset array keys after sorting
            }
        );
    }

    /**
     * Get only active contracted workers for a specific contractor
     * Returns workers with active contracts only
     * Sorted by: Country (ascending), then Name
     */
    public function getActiveContractedWorkers(string $clabNo): Collection
    {
        return Cache::remember(
            "contracted_workers:contractor:{$clabNo}:active_only",
            $this->cacheTTL,
            function () use ($clabNo) {
                $contractWorkerIds = ContractWorker::byContractor($clabNo)
                    ->active()
                    ->pluck('con_wkr_id');

                return Worker::whereIn('wkr_id', $contractWorkerIds)
                    ->with(['country', 'workTrade'])
                    ->get()
                    ->map(function ($worker) use ($clabNo) {
                        // Attach contract info to each worker
                        $contract = ContractWorker::where('con_wkr_id', $worker->wkr_id)
                            ->where('con_ctr_clab_no', $clabNo)
                            ->active()
                            ->first();

                        $worker->contract_info = $contract;

                        return $worker;
                    })
                    ->sortBy([
                        // Sort by country name (ascending)
                        fn ($worker) => $worker->country?->cty_desc ?? 'ZZZ',
                        // Then by worker name
                        fn ($worker) => $worker->wkr_name,
                    ])
                    ->values(); // Reset array keys after sorting
            }
        );
    }

    /**
     * Get all contractors that have active contracts in the system
     */
    public function getContractorsWithActiveContracts(): Collection
    {
        return Cache::remember(
            'contracted_contractors:active',
            $this->cacheTTL,
            function () {
                $clabNumbers = ContractWorker::active()
                    ->distinct()
                    ->pluck('con_ctr_clab_no');

                return Contractor::whereIn('ctr_clab_no', $clabNumbers)->get();
            }
        );
    }

    /**
     * Get contract by ID
     */
    public function getContract(int $contractId): ?ContractWorker
    {
        return Cache::remember(
            "contract_worker:{$contractId}",
            $this->cacheTTL,
            fn () => ContractWorker::with(['contractor', 'worker'])->find($contractId)
        );
    }

    /**
     * Get contracts expiring within specified days
     */
    public function getExpiringContracts(int $days = 30): Collection
    {
        return Cache::remember(
            "contract_workers:expiring:{$days}",
            $this->cacheTTL,
            function () use ($days) {
                $endDate = now()->addDays($days)->toDateString();

                return ContractWorker::where('con_end', '>=', now()->toDateString())
                    ->where('con_end', '<=', $endDate)
                    ->with(['contractor', 'worker'])
                    ->orderBy('con_end')
                    ->get();
            }
        );
    }

    /**
     * Get statistics about contracts
     */
    public function getContractStatistics(): array
    {
        return Cache::remember(
            'contract_workers:statistics',
            $this->cacheTTL,
            function () {
                $total = ContractWorker::count();
                $active = ContractWorker::active()->count();
                $expired = ContractWorker::expired()->count();
                $expiringIn30Days = ContractWorker::active()
                    ->where('con_end', '<=', now()->addDays(30)->toDateString())
                    ->count();

                $contractorCount = ContractWorker::active()
                    ->distinct('con_ctr_clab_no')
                    ->count('con_ctr_clab_no');

                $workerCount = ContractWorker::active()
                    ->distinct('con_wkr_id')
                    ->count('con_wkr_id');

                return [
                    'total_contracts' => $total,
                    'active_contracts' => $active,
                    'expired_contracts' => $expired,
                    'expiring_soon' => $expiringIn30Days,
                    'active_contractors' => $contractorCount,
                    'active_workers' => $workerCount,
                ];
            }
        );
    }

    /**
     * Search contracts by worker name or passport number
     */
    public function searchContracts(string $query): Collection
    {
        $cacheKey = 'contract_workers:search:'.md5($query);

        return Cache::remember(
            $cacheKey,
            $this->cacheTTL,
            function () use ($query) {
                // Get matching worker IDs
                $workerIds = Worker::where('wkr_name', 'LIKE', "%{$query}%")
                    ->orWhere('wkr_passno', 'LIKE', "%{$query}%")
                    ->pluck('wkr_id');

                return ContractWorker::whereIn('con_wkr_id', $workerIds)
                    ->orWhere('con_ctr_clab_no', 'LIKE', "%{$query}%")
                    ->with(['contractor', 'worker'])
                    ->get();
            }
        );
    }

    /**
     * Check if a worker has an active contract with a contractor
     */
    public function hasActiveContract(int $workerId, string $clabNo): bool
    {
        return ContractWorker::where('con_wkr_id', $workerId)
            ->where('con_ctr_clab_no', $clabNo)
            ->active()
            ->exists();
    }

    /**
     * Get worker's active contract
     */
    public function getWorkerActiveContract(int $workerId): ?ContractWorker
    {
        return ContractWorker::where('con_wkr_id', $workerId)
            ->active()
            ->with(['contractor', 'worker'])
            ->first();
    }

    /**
     * Invalidate cache for a specific contract
     */
    public function invalidateContractCache(int $contractId): void
    {
        Cache::forget("contract_worker:{$contractId}");
        Cache::forget('contract_workers:active');
        Cache::forget('contract_workers:statistics');

        // Get the contract to invalidate contractor-specific cache
        $contract = ContractWorker::find($contractId);
        if ($contract) {
            Cache::forget("contract_workers:contractor:{$contract->con_ctr_clab_no}");
            Cache::forget("contract_workers:contractor:{$contract->con_ctr_clab_no}:active");
            Cache::forget("contracted_workers:contractor:{$contract->con_ctr_clab_no}");
        }
    }

    /**
     * Invalidate all contract caches
     */
    public function invalidateAllContractCaches(): void
    {
        if (Cache::getStore() instanceof \Illuminate\Cache\RedisStore) {
            Cache::tags(['contract_workers'])->flush();
        } else {
            Cache::forget('contract_workers:active');
            Cache::forget('contract_workers:statistics');
            Cache::forget('contracted_contractors:active');
        }
    }

    /**
     * Get workers with pending OT from previous month
     * These are workers who have OT hours recorded in the previous month
     * that need to be paid in the current month (even if contract ended)
     */
    public function getWorkersWithPendingOT(string $clabNo, int $month, int $year): Collection
    {
        // Calculate previous month
        $previousMonth = $month - 1;
        $previousYear = $year;

        if ($previousMonth < 1) {
            $previousMonth = 12;
            $previousYear--;
        }

        // Get workers who had OT in the previous month's payroll
        $workersWithOT = \App\Models\PayrollWorker::whereHas('payrollSubmission', function ($query) use ($clabNo, $previousMonth, $previousYear) {
            $query->where('contractor_clab_no', $clabNo)
                ->where('month', $previousMonth)
                ->where('year', $previousYear);
        })
            ->where(function ($query) {
                $query->where('ot_normal_hours', '>', 0)
                    ->orWhere('ot_rest_hours', '>', 0)
                    ->orWhere('ot_public_hours', '>', 0);
            })
            ->pluck('worker_id')
            ->unique();

        if ($workersWithOT->isEmpty()) {
            return collect([]);
        }

        // Get these workers from Worker model with their contract info
        return Worker::whereIn('wkr_id', $workersWithOT)
            ->with(['country', 'workTrade'])
            ->get()
            ->map(function ($worker) use ($clabNo) {
                // Attach contract info (most recent contract with this contractor)
                $contract = ContractWorker::where('con_wkr_id', $worker->wkr_id)
                    ->where('con_ctr_clab_no', $clabNo)
                    ->orderBy('con_end', 'desc')
                    ->first();

                $worker->contract_info = $contract;

                return $worker;
            });
    }

    /**
     * Set custom cache TTL
     */
    public function setCacheTTL(int $seconds): self
    {
        $this->cacheTTL = $seconds;

        return $this;
    }
}
