<?php

namespace App\Services;

use App\Models\Contractor;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class ContractorService
{
    /**
     * Cache TTL in seconds (1 hour by default)
     */
    protected int $cacheTTL = 3600;

    /**
     * Get a single contractor by CLAB number with caching
     */
    public function getContractor(string $clabNo): ?Contractor
    {
        return Cache::remember(
            "contractor:{$clabNo}",
            $this->cacheTTL,
            fn () => Contractor::find($clabNo)
        );
    }

    /**
     * Get contractor with workers relationship
     */
    public function getContractorWithWorkers(string $clabNo): ?Contractor
    {
        return Cache::remember(
            "contractor:{$clabNo}:with_workers",
            $this->cacheTTL,
            fn () => Contractor::with('workers')->find($clabNo)
        );
    }

    /**
     * Get all active contractors with caching
     */
    public function getActiveContractors(): Collection
    {
        return Cache::remember(
            'contractors:active',
            $this->cacheTTL,
            fn () => Contractor::active()->get()
        );
    }

    /**
     * Search contractors by company name with caching
     */
    public function searchContractors(string $query): Collection
    {
        $cacheKey = 'contractors:search:'.md5($query);

        return Cache::remember(
            $cacheKey,
            $this->cacheTTL,
            function () use ($query) {
                return Contractor::where('ctr_comp_name', 'LIKE', "%{$query}%")
                    ->orWhere('ctr_comp_regno', 'LIKE', "%{$query}%")
                    ->orWhere('ctr_contact_name', 'LIKE', "%{$query}%")
                    ->orWhere('ctr_clab_no', 'LIKE', "%{$query}%")
                    ->get();
            }
        );
    }

    /**
     * Get contractors with active contracts
     */
    public function getContractorsWithActiveContracts(): Collection
    {
        return Cache::remember(
            'contractors:active_contracts',
            $this->cacheTTL,
            function () {
                return Contractor::active()
                    ->where('ctr_datereg', '<=', now())
                    ->where('ctr_clabexp_date', '>=', now())
                    ->get();
            }
        );
    }

    /**
     * Get contractor statistics with caching
     */
    public function getContractorStatistics(): array
    {
        return Cache::remember(
            'contractors:statistics',
            $this->cacheTTL,
            function () {
                return [
                    'total' => Contractor::count(),
                    'active' => Contractor::active()->count(),
                    'total_workers' => Contractor::withCount('workers')->get()->sum('workers_count'),
                ];
            }
        );
    }

    /**
     * Invalidate cache for a specific contractor
     */
    public function invalidateContractorCache(string $clabNo): void
    {
        Cache::forget("contractor:{$clabNo}");
        Cache::forget("contractor:{$clabNo}:with_workers");

        // Also invalidate lists
        Cache::forget('contractors:active');
        Cache::forget('contractors:active_contracts');
        Cache::forget('contractors:statistics');
    }

    /**
     * Invalidate all contractor caches
     */
    public function invalidateAllContractorCaches(): void
    {
        // Use cache tags if using Redis
        if (Cache::getStore() instanceof \Illuminate\Cache\RedisStore) {
            Cache::tags(['contractors'])->flush();
        } else {
            // Fallback: Clear specific patterns
            Cache::forget('contractors:active');
            Cache::forget('contractors:active_contracts');
            Cache::forget('contractors:statistics');
        }
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
