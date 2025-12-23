<?php

namespace App\Livewire\Client;

use App\Exports\WorkersExport;
use App\Services\ContractWorkerService;
use Livewire\Attributes\Url;
use Livewire\Component;
use Maatwebsite\Excel\Facades\Excel;

class Workers extends Component
{
    protected ContractWorkerService $contractWorkerService;

    #[Url]
    public $search = '';

    #[Url]
    public $status = 'all';

    #[Url]
    public $country = 'all';

    #[Url]
    public $position = 'all';

    #[Url]
    public $expiryStatus = 'all';

    #[Url]
    public $page = 1;

    #[Url]
    public $sortBy = 'status';

    #[Url]
    public $sortDirection = 'asc';

    public function boot(ContractWorkerService $contractWorkerService)
    {
        $this->contractWorkerService = $contractWorkerService;
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedStatus()
    {
        $this->resetPage();
    }

    public function updatedCountry()
    {
        $this->resetPage();
    }

    public function updatedPosition()
    {
        $this->resetPage();
    }

    public function updatedExpiryStatus()
    {
        $this->resetPage();
    }

    public function resetFilters()
    {
        $this->search = '';
        $this->status = 'all';
        $this->country = 'all';
        $this->position = 'all';
        $this->expiryStatus = 'all';
        $this->resetPage();
    }

    public function resetPage()
    {
        $this->page = 1;
    }

    public function sortByColumn($column)
    {
        if ($this->sortBy === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDirection = 'asc';
        }
        $this->resetPage();
    }

    public function export()
    {
        $clabNo = auth()->user()->contractor_clab_no ?? auth()->user()->username;

        if (! $clabNo) {
            return;
        }

        // Get all contracted workers with current filters applied
        $allWorkers = $this->contractWorkerService->getContractedWorkers($clabNo);

        // Apply the same filters as in render()
        if ($this->search) {
            $allWorkers = $allWorkers->filter(function ($worker) {
                return str_contains(strtolower($worker->name), strtolower($this->search)) ||
                       str_contains(strtolower($worker->ic_number), strtolower($this->search)) ||
                       str_contains(strtolower($worker->wkr_id), strtolower($this->search));
            });
        }

        if ($this->status && $this->status !== 'all') {
            $allWorkers = $allWorkers->filter(function ($worker) {
                if ($this->status === 'active') {
                    return $worker->contract_info && $worker->contract_info->isActive();
                } elseif ($this->status === 'inactive') {
                    return ! $worker->contract_info || ! $worker->contract_info->isActive();
                }

                return true;
            });
        }

        if ($this->country && $this->country !== 'all') {
            $allWorkers = $allWorkers->filter(function ($worker) {
                return $worker->country && $worker->country->cty_code === $this->country;
            });
        }

        if ($this->position && $this->position !== 'all') {
            $allWorkers = $allWorkers->filter(function ($worker) {
                return $worker->workTrade && $worker->workTrade->trade_code === $this->position;
            });
        }

        if ($this->expiryStatus && $this->expiryStatus !== 'all') {
            $allWorkers = $allWorkers->filter(function ($worker) {
                $passportExpired = $worker->wkr_passexp && $worker->wkr_passexp->isPast();
                $passportExpiringSoon = $worker->wkr_passexp && $worker->wkr_passexp->isFuture() && now()->diffInDays($worker->wkr_passexp, false) <= 60;
                $permitExpired = $worker->wkr_permitexp && $worker->wkr_permitexp->isPast();
                $permitExpiringSoon = $worker->wkr_permitexp && $worker->wkr_permitexp->isFuture() && now()->diffInDays($worker->wkr_permitexp, false) <= 30;

                return match ($this->expiryStatus) {
                    'expired' => $passportExpired || $permitExpired,
                    'expiring_soon' => ($passportExpiringSoon || $permitExpiringSoon) && ! $passportExpired && ! $permitExpired,
                    'valid' => ! $passportExpired && ! $permitExpired && ! $passportExpiringSoon && ! $permitExpiringSoon,
                    default => true,
                };
            });
        }

        $fileName = 'workers_'.now()->format('Y-m-d_His').'.xlsx';

        return Excel::download(new WorkersExport($allWorkers), $fileName);
    }

    public function render()
    {
        $clabNo = auth()->user()->contractor_clab_no ?? auth()->user()->username;

        if (! $clabNo) {
            return view('livewire.client.workers', [
                'workers' => collect([]),
                'stats' => [
                    'total_workers' => 0,
                    'active_workers' => 0,
                    'inactive_workers' => 0,
                    'average_salary' => 0,
                ],
                'pagination' => [
                    'current_page' => 1,
                    'per_page' => 10,
                    'total' => 0,
                    'last_page' => 1,
                    'from' => 0,
                    'to' => 0,
                ],
                'countries' => collect([]),
                'positions' => collect([]),
            ]);
        }

        // Get all contracted workers
        $allWorkers = $this->contractWorkerService->getContractedWorkers($clabNo);

        // Get unique countries and positions for filters
        $countries = $allWorkers->pluck('country')->filter()->unique('cty_code')->sortBy('cty_desc')->values();
        $positions = $allWorkers->pluck('workTrade')->filter()->unique('trade_code')->sortBy('trade_desc')->values();

        // Apply search filter
        if ($this->search) {
            $allWorkers = $allWorkers->filter(function ($worker) {
                return str_contains(strtolower($worker->name), strtolower($this->search)) ||
                       str_contains(strtolower($worker->ic_number), strtolower($this->search)) ||
                       str_contains(strtolower($worker->wkr_id), strtolower($this->search));
            });
        }

        // Apply status filter
        if ($this->status && $this->status !== 'all') {
            $allWorkers = $allWorkers->filter(function ($worker) {
                if ($this->status === 'active') {
                    return $worker->contract_info && $worker->contract_info->isActive();
                } elseif ($this->status === 'inactive') {
                    return ! $worker->contract_info || ! $worker->contract_info->isActive();
                }

                return true;
            });
        }

        // Apply country filter
        if ($this->country && $this->country !== 'all') {
            $allWorkers = $allWorkers->filter(function ($worker) {
                return $worker->country && $worker->country->cty_code === $this->country;
            });
        }

        // Apply position filter
        if ($this->position && $this->position !== 'all') {
            $allWorkers = $allWorkers->filter(function ($worker) {
                return $worker->workTrade && $worker->workTrade->trade_code === $this->position;
            });
        }

        // Apply expiry status filter
        if ($this->expiryStatus && $this->expiryStatus !== 'all') {
            $allWorkers = $allWorkers->filter(function ($worker) {
                $passportExpired = $worker->wkr_passexp && $worker->wkr_passexp->isPast();
                $passportExpiringSoon = $worker->wkr_passexp && $worker->wkr_passexp->isFuture() && now()->diffInDays($worker->wkr_passexp, false) <= 60;
                $permitExpired = $worker->wkr_permitexp && $worker->wkr_permitexp->isPast();
                $permitExpiringSoon = $worker->wkr_permitexp && $worker->wkr_permitexp->isFuture() && now()->diffInDays($worker->wkr_permitexp, false) <= 30;

                return match ($this->expiryStatus) {
                    'expired' => $passportExpired || $permitExpired,
                    'expiring_soon' => ($passportExpiringSoon || $permitExpiringSoon) && ! $passportExpired && ! $permitExpired,
                    'valid' => ! $passportExpired && ! $permitExpired && ! $passportExpiringSoon && ! $permitExpiringSoon,
                    default => true,
                };
            });
        }

        // Apply sorting
        $allWorkers = $allWorkers->sort(function ($a, $b) {
            $primaryA = match ($this->sortBy) {
                'wkr_id' => $a->wkr_id,
                'name' => strtolower($a->name),
                'ic_number' => $a->ic_number,
                'passport_expiry' => $a->wkr_passexp ? $a->wkr_passexp->timestamp : 0,
                'permit_expiry' => $a->wkr_permitexp ? $a->wkr_permitexp->timestamp : 0,
                'country' => strtolower($a->country->cty_desc ?? ''),
                'position' => strtolower($a->workTrade->trade_desc ?? ''),
                'basic_salary' => $a->basic_salary ?? 0,
                'status' => ($a->contract_info && $a->contract_info->isActive()) ? 0 : 1,
                default => $a->wkr_id,
            };

            $primaryB = match ($this->sortBy) {
                'wkr_id' => $b->wkr_id,
                'name' => strtolower($b->name),
                'ic_number' => $b->ic_number,
                'passport_expiry' => $b->wkr_passexp ? $b->wkr_passexp->timestamp : 0,
                'permit_expiry' => $b->wkr_permitexp ? $b->wkr_permitexp->timestamp : 0,
                'country' => strtolower($b->country->cty_desc ?? ''),
                'position' => strtolower($b->workTrade->trade_desc ?? ''),
                'basic_salary' => $b->basic_salary ?? 0,
                'status' => ($b->contract_info && $b->contract_info->isActive()) ? 0 : 1,
                default => $b->wkr_id,
            };

            // Primary sort comparison
            $comparison = $primaryA <=> $primaryB;

            // If primary values are equal, sort by name as secondary
            if ($comparison === 0) {
                $comparison = strtolower($a->name) <=> strtolower($b->name);
            }

            // Apply sort direction
            return $this->sortDirection === 'desc' ? -$comparison : $comparison;
        })->values();

        // Calculate statistics
        $activeWorkers = $allWorkers->filter(function ($worker) {
            return $worker->contract_info && $worker->contract_info->isActive();
        });

        $totalSalary = $allWorkers->sum(function ($worker) {
            return $worker->basic_salary ?? 0;
        });

        $averageSalary = $allWorkers->count() > 0
            ? $totalSalary / $allWorkers->count()
            : 0;

        $stats = [
            'total_workers' => $allWorkers->count(),
            'active_workers' => $activeWorkers->count(),
            'inactive_workers' => $allWorkers->count() - $activeWorkers->count(),
            'average_salary' => $averageSalary,
        ];

        // Pagination
        $perPage = 10;
        $total = $allWorkers->count();
        $workers = $allWorkers->slice(($this->page - 1) * $perPage, $perPage)->values();

        $pagination = [
            'current_page' => $this->page,
            'per_page' => $perPage,
            'total' => $total,
            'last_page' => ceil($total / $perPage),
            'from' => (($this->page - 1) * $perPage) + 1,
            'to' => min($this->page * $perPage, $total),
        ];

        return view('livewire.client.workers', [
            'workers' => $workers,
            'stats' => $stats,
            'pagination' => $pagination,
            'countries' => $countries,
            'positions' => $positions,
        ])->layout('components.layouts.app', ['title' => __('My Workers')]);
    }
}
