<?php

namespace App\Livewire\Admin;

use App\Models\PayrollSubmission;
use App\Models\User;
use Flux\Flux;
use Livewire\Attributes\Url;
use Livewire\Component;

class Contractors extends Component
{
    public $stats = [];

    #[Url(except: '')]
    public $search = '';

    #[Url(except: '')]
    public $statusFilter = '';

    #[Url(except: 1)]
    public $page = 1;

    public $perPage = 10;

    public $showFilters = true;

    public $sortBy = 'name';

    public $sortDirection = 'asc';

    public function mount()
    {
        $this->loadStats();
    }

    public function toggleFilters()
    {
        $this->showFilters = ! $this->showFilters;
    }

    public function sortByColumn($column)
    {
        if ($this->sortBy === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDirection = 'asc';
        }
    }

    public function export()
    {
        $contractors = $this->getContractors();

        if ($contractors->isEmpty()) {
            Flux::toast(
                variant: 'warning',
                heading: 'No data to export',
                text: 'No contractors found matching your filters.'
            );

            return;
        }

        Flux::toast(
            variant: 'info',
            heading: 'Export feature',
            text: 'Contractor export will be implemented soon.'
        );
    }

    public function resetPage()
    {
        $this->page = 1;
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingStatusFilter()
    {
        $this->resetPage();
    }

    public function clearFilters()
    {
        $this->search = '';
        $this->statusFilter = '';
        $this->resetPage();
    }

    protected function loadStats()
    {
        // Total contractors
        $totalContractors = User::where('role', 'client')->count();

        // Active contractors (submitted in last 3 months)
        $activeContractors = User::where('role', 'client')
            ->whereHas('payrollSubmissions', function ($query) {
                $query->where('created_at', '>=', now()->subMonths(3));
            })
            ->count();

        // Total outstanding balance
        $totalOutstanding = PayrollSubmission::whereIn('status', ['pending_payment', 'overdue'])
            ->sum('total_with_penalty');

        // Contractors with pending payments
        $contractorsWithPending = PayrollSubmission::whereIn('status', ['pending_payment', 'overdue'])
            ->distinct('contractor_clab_no')
            ->count('contractor_clab_no');

        $this->stats = [
            'total_contractors' => $totalContractors,
            'active_contractors' => $activeContractors,
            'total_outstanding' => $totalOutstanding,
            'contractors_with_pending' => $contractorsWithPending,
        ];
    }

    protected function getContractors()
    {
        $query = User::where('role', 'client');

        // Apply search filter
        if ($this->search) {
            $query->where(function ($q) {
                $q->where('name', 'like', '%'.$this->search.'%')
                    ->orWhere('email', 'like', '%'.$this->search.'%')
                    ->orWhere('contractor_clab_no', 'like', '%'.$this->search.'%')
                    ->orWhere('phone', 'like', '%'.$this->search.'%')
                    ->orWhere('person_in_charge', 'like', '%'.$this->search.'%');
            });
        }

        // Apply status filter
        if ($this->statusFilter) {
            if ($this->statusFilter === 'active') {
                $query->whereHas('payrollSubmissions', function ($q) {
                    $q->where('created_at', '>=', now()->subMonths(3));
                });
            } elseif ($this->statusFilter === 'inactive') {
                $query->whereDoesntHave('payrollSubmissions', function ($q) {
                    $q->where('created_at', '>=', now()->subMonths(3));
                });
            } elseif ($this->statusFilter === 'with_pending') {
                $query->whereHas('payrollSubmissions', function ($q) {
                    $q->whereIn('status', ['pending_payment', 'overdue']);
                });
            }
        }

        // Apply sorting
        $query->orderBy($this->sortBy, $this->sortDirection);

        return $query->get();
    }

    public function render()
    {
        $allContractors = $this->getContractors();

        // Enhance contractors with statistics
        $allContractors->transform(function ($contractor) {
            $contractor->pending_payments = PayrollSubmission::where('contractor_clab_no', $contractor->contractor_clab_no)
                ->whereIn('status', ['pending_payment', 'overdue'])
                ->count();
            $contractor->total_outstanding = PayrollSubmission::where('contractor_clab_no', $contractor->contractor_clab_no)
                ->whereIn('status', ['pending_payment', 'overdue'])
                ->sum('total_with_penalty');

            return $contractor;
        });

        // Manual pagination
        $total = $allContractors->count();
        $contractors = $allContractors->slice(($this->page - 1) * $this->perPage, $this->perPage)->values();

        $pagination = [
            'current_page' => $this->page,
            'per_page' => $this->perPage,
            'total' => $total,
            'last_page' => ceil($total / $this->perPage),
            'from' => (($this->page - 1) * $this->perPage) + 1,
            'to' => min($this->page * $this->perPage, $total),
        ];

        return view('livewire.admin.contractors', [
            'contractors' => $contractors,
            'pagination' => $pagination,
        ]);
    }
}
