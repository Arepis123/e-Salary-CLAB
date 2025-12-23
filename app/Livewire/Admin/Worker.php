<?php

namespace App\Livewire\Admin;

use App\Exports\WorkersExport;
use App\Models\ContractWorker;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;

class Worker extends Component
{
    use WithPagination;

    #[Url]
    public $search = '';

    #[Url]
    public $clientFilter = '';

    #[Url]
    public $statusFilter = '';

    #[Url]
    public $positionFilter = '';

    #[Url]
    public $page = 1;

    #[Url]
    public $sortBy = 'name';

    #[Url]
    public $sortDirection = 'asc';

    public $stats = [];

    public $workers = [];

    public $showFilters = true;

    public $clients = [];

    public $positions = [];

    public $perPage = 10;

    public function mount()
    {
        $this->loadStats();
        $this->loadClients();
        $this->loadPositions();
    }

    public function toggleFilters()
    {
        $this->showFilters = ! $this->showFilters;
    }

    public function clearFilters()
    {
        $this->search = '';
        $this->clientFilter = '';
        $this->positionFilter = '';
        $this->statusFilter = '';
        $this->resetPage();
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

    public function resetPage()
    {
        $this->page = 1;
    }

    public function export()
    {
        // Get filtered worker IDs
        $workerData = $this->getWorkersData();
        $workerIds = $workerData->pluck('id')->toArray();

        // Load actual Worker models with relationships for export
        $allWorkers = \App\Models\Worker::with(['country', 'workTrade', 'activeContract'])
            ->whereIn('wkr_id', $workerIds)
            ->get();

        $fileName = 'workers_'.now()->format('Y-m-d_His').'.xlsx';

        return Excel::download(new WorkersExport($allWorkers), $fileName);
    }

    protected function loadClients()
    {
        // Get unique contractors from contract_worker table
        $this->clients = ContractWorker::with('contractor')
            ->get()
            ->pluck('contractor')
            ->filter()
            ->unique('ctr_clab_no')
            ->sortBy('ctr_comp_name')
            ->pluck('ctr_comp_name', 'ctr_clab_no')
            ->toArray();
    }

    protected function loadPositions()
    {
        // Get unique positions from actual worker data
        $allWorkers = $this->getAllWorkersForFilters();

        $this->positions = $allWorkers
            ->pluck('position')
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->toArray();
    }

    /**
     * Get all workers data without filters for populating filter options
     */
    protected function getAllWorkersForFilters()
    {
        // Get all contracted workers with worker details
        $contractWorkers = ContractWorker::with(['worker.country', 'contractor'])
            ->get();

        // Transform the data
        return $contractWorkers->map(function ($contractWorker) {
            $worker = $contractWorker->worker;
            $contractor = $contractWorker->contractor;

            return [
                'id' => $contractWorker->con_wkr_id,
                'employee_id' => $contractWorker->con_wkr_id,
                'name' => $worker ? $worker->wkr_name : 'N/A',
                'passport' => $contractWorker->con_wkr_passno,
                'position' => 'General Worker', // TODO: Add position field to database
                'client' => $contractor ? $contractor->ctr_comp_name : 'N/A',
                'salary' => 1700, // Default minimum salary
                'passport_expiry' => $worker && $worker->wkr_passexp
                    ? $worker->wkr_passexp->format('d/m/Y')
                    : 'N/A',
                'permit_expiry' => $worker && $worker->wkr_permitexp
                    ? $worker->wkr_permitexp->format('d/m/Y')
                    : 'N/A',
                'country' => $worker && $worker->country
                    ? $worker->country->cty_desc
                    : 'N/A',
            ];
        })->unique('employee_id');
    }

    protected function loadStats()
    {
        // Get total contracted workers
        $totalWorkers = ContractWorker::distinct('con_wkr_id')->count('con_wkr_id');

        // Active workers have contracts that haven't expired yet
        $activeWorkers = ContractWorker::active()->distinct('con_wkr_id')->count('con_wkr_id');

        // Inactive workers have expired contracts
        $inactiveWorkers = ContractWorker::expired()->distinct('con_wkr_id')->count('con_wkr_id');

        $this->stats = [
            'total' => $totalWorkers,
            'active' => $activeWorkers,
            'on_leave' => 0, // TODO: Add worker status tracking
            'inactive' => $inactiveWorkers,
        ];
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingClientFilter()
    {
        $this->resetPage();
    }

    public function updatingStatusFilter()
    {
        $this->resetPage();
    }

    public function updatingPositionFilter()
    {
        $this->resetPage();
    }

    public function getWorkersData()
    {
        // Start with all contracted workers
        $query = ContractWorker::with(['worker.country', 'contractor']);

        // Apply search filter
        if ($this->search) {
            $query->where(function ($q) {
                $q->where('con_wkr_id', 'like', '%'.$this->search.'%')
                    ->orWhere('con_wkr_passno', 'like', '%'.$this->search.'%')
                    ->orWhereHas('worker', function ($workerQuery) {
                        $workerQuery->where('wkr_name', 'like', '%'.$this->search.'%');
                    });
            });
        }

        // Apply client filter
        if ($this->clientFilter) {
            $query->where('con_ctr_clab_no', $this->clientFilter);
        }

        // Get the contracted workers
        $contractWorkers = $query->get();

        // Transform the data
        $transformedWorkers = $contractWorkers->map(function ($contractWorker) {
            $worker = $contractWorker->worker;
            $contractor = $contractWorker->contractor;

            // Determine status based on contract expiry
            $status = $contractWorker->isActive() ? 'Active' : 'Inactive';

            return [
                'id' => $contractWorker->con_wkr_id,
                'employee_id' => $contractWorker->con_wkr_id,
                'name' => $worker ? $worker->wkr_name : 'N/A',
                'passport' => $contractWorker->con_wkr_passno,
                'position' => 'General Worker', // TODO: Add position field to database
                'client' => $contractor ? $contractor->ctr_comp_name : 'N/A',
                'salary' => 1700, // Default minimum salary
                'status' => $status,
                'passport_expiry' => $worker && $worker->wkr_passexp
                    ? $worker->wkr_passexp->format('d/m/Y')
                    : 'N/A',
                'permit_expiry' => $worker && $worker->wkr_permitexp
                    ? $worker->wkr_permitexp->format('d/m/Y')
                    : 'N/A',
                'country' => $worker && $worker->country
                    ? $worker->country->cty_desc
                    : 'N/A',
                'contract_start' => $contractWorker->con_start ? $contractWorker->con_start->format('d/m/Y') : 'N/A',
                'contract_end' => $contractWorker->con_end ? $contractWorker->con_end->format('d/m/Y') : 'N/A',
            ];
        })->unique('employee_id');

        // Apply status filter after transformation
        if ($this->statusFilter) {
            $transformedWorkers = $transformedWorkers->filter(function ($worker) {
                return $worker['status'] === $this->statusFilter;
            });
        }

        // Apply position filter after transformation
        if ($this->positionFilter) {
            $transformedWorkers = $transformedWorkers->filter(function ($worker) {
                return $worker['position'] === $this->positionFilter;
            });
        }

        // Apply sorting
        $transformedWorkers = $transformedWorkers->sort(function ($a, $b) {
            // Helper function to convert d/m/Y date to timestamp for sorting
            $dateToTimestamp = function ($dateString) {
                if ($dateString === 'N/A' || empty($dateString)) {
                    return PHP_INT_MAX; // Put N/A dates at the end
                }
                // Convert d/m/Y to timestamp
                $date = \DateTime::createFromFormat('d/m/Y', $dateString);

                return $date ? $date->getTimestamp() : PHP_INT_MAX;
            };

            $primaryA = match ($this->sortBy) {
                'name' => strtolower($a['name']),
                'passport' => strtolower($a['passport']),
                'position' => strtolower($a['position']),
                'country' => strtolower($a['country']),
                'client' => strtolower($a['client']),
                'passport_expiry' => $dateToTimestamp($a['passport_expiry']),
                'permit_expiry' => $dateToTimestamp($a['permit_expiry']),
                'status' => $a['status'] === 'Active' ? 0 : 1,
                default => strtolower($a['name']),
            };

            $primaryB = match ($this->sortBy) {
                'name' => strtolower($b['name']),
                'passport' => strtolower($b['passport']),
                'position' => strtolower($b['position']),
                'country' => strtolower($b['country']),
                'client' => strtolower($b['client']),
                'passport_expiry' => $dateToTimestamp($b['passport_expiry']),
                'permit_expiry' => $dateToTimestamp($b['permit_expiry']),
                'status' => $b['status'] === 'Active' ? 0 : 1,
                default => strtolower($b['name']),
            };

            $comparison = $primaryA <=> $primaryB;

            // If primary values are equal, sort by name as secondary
            if ($comparison === 0 && $this->sortBy !== 'name') {
                $comparison = strtolower($a['name']) <=> strtolower($b['name']);
            }

            return $this->sortDirection === 'desc' ? -$comparison : $comparison;
        })->values();

        return $transformedWorkers;
    }

    public function render()
    {
        $allWorkers = $this->getWorkersData();

        // Pagination
        $total = $allWorkers->count();
        $this->workers = $allWorkers->slice(($this->page - 1) * $this->perPage, $this->perPage)->values();

        $pagination = [
            'current_page' => $this->page,
            'per_page' => $this->perPage,
            'total' => $total,
            'last_page' => ceil($total / $this->perPage),
            'from' => (($this->page - 1) * $this->perPage) + 1,
            'to' => min($this->page * $this->perPage, $total),
        ];

        return view('livewire.admin.worker', [
            'workers' => $this->workers,
            'pagination' => $pagination,
        ]);
    }
}
