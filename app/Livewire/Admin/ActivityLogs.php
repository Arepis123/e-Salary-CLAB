<?php

namespace App\Livewire\Admin;

use App\Models\ActivityLog;
use App\Models\User;
use Livewire\Attributes\Url;
use Livewire\Component;

class ActivityLogs extends Component
{
    #[Url]
    public $moduleFilter = '';

    #[Url]
    public $actionFilter = '';

    #[Url]
    public $contractorFilter = '';

    #[Url]
    public $search = '';

    #[Url]
    public $startDate = '';

    #[Url]
    public $endDate = '';

    #[Url]
    public $page = 1;

    public $perPage = 40;

    public $showFilters = false;

    public $contractors = [];

    public $modules = [];

    public $actions = [];

    public $selectedLog = null;

    public $showDetailModal = false;

    public function mount()
    {
        $this->loadFilterOptions();

        // Set default date range (last 7 days)
        if (empty($this->startDate)) {
            $this->startDate = now()->subDays(7)->format('Y-m-d');
        }
        if (empty($this->endDate)) {
            $this->endDate = now()->format('Y-m-d');
        }
    }

    public function toggleFilters()
    {
        $this->showFilters = ! $this->showFilters;
    }

    public function clearFilters()
    {
        $this->moduleFilter = '';
        $this->actionFilter = '';
        $this->contractorFilter = '';
        $this->search = '';
        $this->startDate = now()->subDays(7)->format('Y-m-d');
        $this->endDate = now()->format('Y-m-d');
        $this->resetPage();
    }

    public function resetPage()
    {
        $this->page = 1;
    }

    public function updatingModuleFilter()
    {
        $this->resetPage();
    }

    public function updatingActionFilter()
    {
        $this->resetPage();
    }

    public function updatingContractorFilter()
    {
        $this->resetPage();
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingStartDate()
    {
        $this->resetPage();
    }

    public function updatingEndDate()
    {
        $this->resetPage();
    }

    public function viewDetails($logId)
    {
        $this->selectedLog = ActivityLog::with(['user', 'subject'])->find($logId);
        $this->showDetailModal = true;
    }

    public function closeDetailModal()
    {
        $this->showDetailModal = false;
        $this->selectedLog = null;
    }

    protected function loadFilterOptions()
    {
        // Get unique contractors
        $this->contractors = User::where('role', 'client')
            ->whereNotNull('contractor_clab_no')
            ->orderBy('name')
            ->get()
            ->pluck('name', 'contractor_clab_no')
            ->toArray();

        // Get unique modules
        $this->modules = ActivityLog::distinct()
            ->pluck('module')
            ->sort()
            ->toArray();

        // Get unique actions
        $this->actions = ActivityLog::distinct()
            ->pluck('action')
            ->sort()
            ->toArray();
    }

    protected function getLogs()
    {
        $query = ActivityLog::with('user')->latest();

        // Apply module filter
        if ($this->moduleFilter) {
            $query->where('module', $this->moduleFilter);
        }

        // Apply action filter
        if ($this->actionFilter) {
            $query->where('action', $this->actionFilter);
        }

        // Apply contractor filter
        if ($this->contractorFilter) {
            $query->where('contractor_clab_no', $this->contractorFilter);
        }

        // Apply search
        if ($this->search) {
            $query->where(function ($q) {
                $q->where('description', 'like', '%'.$this->search.'%')
                    ->orWhere('user_name', 'like', '%'.$this->search.'%')
                    ->orWhere('user_email', 'like', '%'.$this->search.'%');
            });
        }

        // Apply date range
        if ($this->startDate) {
            $query->whereDate('created_at', '>=', $this->startDate);
        }
        if ($this->endDate) {
            $query->whereDate('created_at', '<=', $this->endDate);
        }

        return $query->get();
    }

    public function render()
    {
        $allLogs = $this->getLogs();

        // Manual pagination
        $total = $allLogs->count();
        $logs = $allLogs->slice(($this->page - 1) * $this->perPage, $this->perPage)->values();

        $pagination = [
            'current_page' => $this->page,
            'per_page' => $this->perPage,
            'total' => $total,
            'last_page' => ceil($total / $this->perPage),
            'from' => (($this->page - 1) * $this->perPage) + 1,
            'to' => min($this->page * $this->perPage, $total),
        ];

        return view('livewire.admin.activity-logs', [
            'logs' => $logs,
            'pagination' => $pagination,
        ]);
    }
}
