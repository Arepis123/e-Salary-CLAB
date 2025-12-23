<?php

namespace App\Services;

use App\Models\Worker;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class WorkerService
{
    /**
     * Cache TTL in seconds (1 hour by default)
     */
    protected int $cacheTTL = 3600;

    /**
     * Get a single worker by ID with caching
     */
    public function getWorker(int $workerId): ?Worker
    {
        return Cache::remember(
            "worker:{$workerId}",
            $this->cacheTTL,
            fn () => Worker::find($workerId)
        );
    }

    /**
     * Get multiple workers by IDs with caching
     */
    public function getWorkers(array $workerIds): Collection
    {
        return collect($workerIds)->map(fn ($id) => $this->getWorker($id))->filter();
    }

    /**
     * Get all active workers with caching
     */
    public function getActiveWorkers(): Collection
    {
        return Cache::remember(
            'workers:active',
            $this->cacheTTL,
            fn () => Worker::active()->get()
        );
    }

    /**
     * Get workers by contractor ID with caching
     */
    public function getWorkersByContractor(string $contractorClabNo): Collection
    {
        return Cache::remember(
            "workers:contractor:{$contractorClabNo}",
            $this->cacheTTL,
            fn () => Worker::where('wkr_currentemp', $contractorClabNo)->get()
        );
    }

    /**
     * Search workers by name or passport number with caching
     */
    public function searchWorkers(string $query): Collection
    {
        $cacheKey = 'workers:search:'.md5($query);

        return Cache::remember(
            $cacheKey,
            $this->cacheTTL,
            function () use ($query) {
                return Worker::where('wkr_name', 'LIKE', "%{$query}%")
                    ->orWhere('wkr_passno', 'LIKE', "%{$query}%")
                    ->get();
            }
        );
    }

    /**
     * Get workers by position with caching
     */
    public function getWorkersByPosition(string $position): Collection
    {
        return Cache::remember(
            "workers:position:{$position}",
            $this->cacheTTL,
            fn () => Worker::position($position)->get()
        );
    }

    /**
     * Invalidate cache for a specific worker
     */
    public function invalidateWorkerCache(int $workerId): void
    {
        Cache::forget("worker:{$workerId}");

        // Also invalidate lists that might contain this worker
        Cache::forget('workers:active');

        // If you know the contractor, invalidate that too
        $worker = Worker::find($workerId);
        if ($worker && $worker->wkr_currentemp) {
            Cache::forget("workers:contractor:{$worker->wkr_currentemp}");
        }
    }

    /**
     * Invalidate all worker caches
     */
    public function invalidateAllWorkerCaches(): void
    {
        // Use cache tags if using Redis
        if (Cache::getStore() instanceof \Illuminate\Cache\RedisStore) {
            Cache::tags(['workers'])->flush();
        } else {
            // Fallback: Clear specific patterns (less efficient)
            Cache::forget('workers:active');
            // Note: Individual worker caches would need to be cleared one by one
        }
    }

    /**
     * Get worker statistics with caching
     */
    public function getWorkerStatistics(): array
    {
        return Cache::remember(
            'workers:statistics',
            $this->cacheTTL,
            function () {
                return [
                    'total' => Worker::count(),
                    'active' => Worker::active()->count(),
                    'by_position' => Worker::selectRaw('wkr_wtrade as position, COUNT(*) as count')
                        ->whereNotNull('wkr_wtrade')
                        ->where('wkr_wtrade', '!=', '')
                        ->groupBy('wkr_wtrade')
                        ->pluck('count', 'position')
                        ->toArray(),
                ];
            }
        );
    }

    /**
     * Warm up cache for frequently accessed workers
     */
    public function warmCache(array $workerIds): void
    {
        foreach ($workerIds as $workerId) {
            $this->getWorker($workerId);
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
