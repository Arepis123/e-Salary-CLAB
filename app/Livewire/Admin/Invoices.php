<?php

namespace App\Livewire\Admin;

use App\Models\PayrollSubmission;
use App\Models\User;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Invoices extends Component
{
    use WithPagination;
    #[Url]
    public $search = '';

    #[Url]
    public $statusFilter = 'all';

    #[Url]
    public $contractor = '';

    #[Url]
    public $year;

    #[Url]
    public $sortBy = 'issue_date';

    #[Url]
    public $sortDirection = 'desc';

    // Cached data loaded once
    public $contractors = [];

    public $availableYears = [];

    public $stats = [];

    // Loading states
    public $isLoadingStats = true;

    public $isLoadingTable = true;

    public $perPage = 15;

    public function mount()
    {
        if (! $this->year) {
            $this->year = now()->year;
        }

        // Initialize empty stats for fast initial render
        $this->stats = [
            'pending_invoices' => 0,
            'paid_invoices' => 0,
            'total_invoiced' => 0,
        ];

        // Load dropdown data once (fast queries)
        $this->loadContractors();
        $this->loadAvailableYears();
    }

    /**
     * Load initial data - called via wire:init
     */
    public function loadInitialData()
    {
        $this->loadStats();
        $this->isLoadingStats = false;
        $this->isLoadingTable = false;
    }

    protected function loadContractors()
    {
        $this->contractors = User::where('role', 'client')
            ->whereNotNull('contractor_clab_no')
            ->orderBy('name')
            ->get(['contractor_clab_no', 'name'])
            ->map(fn($user) => [
                'clab_no' => $user->contractor_clab_no,
                'name' => $user->name,
            ])
            ->toArray();
    }

    protected function loadAvailableYears()
    {
        $this->availableYears = PayrollSubmission::selectRaw('DISTINCT year')
            ->orderBy('year', 'desc')
            ->pluck('year')
            ->toArray();
    }

    protected function loadStats()
    {
        // Use aggregation queries instead of loading all records
        $this->stats = [
            'pending_invoices' => PayrollSubmission::whereIn('status', ['pending_payment', 'overdue'])->count(),
            'paid_invoices' => PayrollSubmission::where('status', 'paid')->count(),
            'total_invoiced' => PayrollSubmission::whereIn('status', ['approved', 'pending_payment', 'paid', 'overdue'])
                ->selectRaw('COALESCE(SUM(COALESCE(admin_final_amount, 0) + COALESCE(grand_total, 0)), 0) as total')
                ->value('total') ?? 0,
        ];
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedStatusFilter()
    {
        $this->resetPage();
    }

    public function updatedContractor()
    {
        $this->resetPage();
    }

    public function updatedYear()
    {
        $this->resetPage();
    }

    public function resetFilters()
    {
        $this->search = '';
        $this->statusFilter = 'all';
        $this->contractor = '';
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

    protected function getInvoicesQuery()
    {
        $query = PayrollSubmission::query()
            ->where('year', $this->year)
            ->with(['user', 'payment']);

        // Apply contractor filter
        if ($this->contractor) {
            $query->where('contractor_clab_no', $this->contractor);
        }

        // Apply search filter at database level
        if ($this->search) {
            $search = $this->search;
            $query->where(function ($q) use ($search) {
                $q->where('id', 'like', '%'.$search.'%')
                    ->orWhere('contractor_clab_no', 'like', '%'.$search.'%')
                    ->orWhereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('name', 'like', '%'.$search.'%');
                    });
            });
        }

        // Apply status filter
        if ($this->statusFilter && $this->statusFilter !== 'all') {
            $query->where('status', $this->statusFilter);
        }

        // Apply sorting at database level
        $sortColumn = match ($this->sortBy) {
            'invoice_number' => 'id',
            'contractor' => 'contractor_clab_no',
            'period' => 'month',
            'workers' => 'total_workers',
            'amount' => 'grand_total',
            'issue_date' => 'submitted_at',
            'due_date' => 'payment_deadline',
            'status' => 'status',
            default => 'submitted_at',
        };

        $query->orderBy($sortColumn, $this->sortDirection);

        // Secondary sort by submitted_at if not already sorting by it
        if ($sortColumn !== 'submitted_at') {
            $query->orderBy('submitted_at', 'desc');
        }

        return $query;
    }

    public function render()
    {
        $invoices = $this->getInvoicesQuery()->paginate($this->perPage);

        return view('livewire.admin.invoices', [
            'invoices' => $invoices,
        ]);
    }
}
