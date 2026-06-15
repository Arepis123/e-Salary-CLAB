<?php

namespace App\Livewire\Admin;

use App\Models\Contractor;
use App\Models\PayrollSubmission;
use App\Models\User;
use Flux\Flux;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Contractors extends Component
{
    use WithPagination;

    public $stats = [];

    #[Url(except: '')]
    public $search = '';

    #[Url(except: '')]
    public $statusFilter = '';

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
        // Total contractors that have workers linked via contract_worker
        $totalContractors = Contractor::whereHas('contracts')->count();

        // Active contractors (submitted in last 3 months)
        $activeContractors = PayrollSubmission::where('created_at', '>=', now()->subMonths(3))
            ->distinct('contractor_clab_no')
            ->count('contractor_clab_no');

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
        // Pre-compute pending/outstanding stats from the app DB in a single
        // grouped query, keyed by CLAB no (avoids per-row queries across 5k+ rows).
        $pendingStats = PayrollSubmission::whereIn('status', ['pending_payment', 'overdue'])
            ->selectRaw('contractor_clab_no, COUNT(*) as pending_count, COALESCE(SUM(total_with_penalty), 0) as outstanding')
            ->groupBy('contractor_clab_no')
            ->get()
            ->keyBy('contractor_clab_no');

        // Contact overrides from contractors who have logged in (local users).
        $users = User::where('role', 'client')->get()->keyBy('contractor_clab_no');

        // Only contractors that have at least one worker linked via contract_worker.
        $query = Contractor::whereHas('contracts');

        // Apply search filter (mapped to contractor table columns)
        if ($this->search) {
            $search = $this->search;
            $query->where(function ($q) use ($search) {
                $q->where('ctr_comp_name', 'like', '%'.$search.'%')
                    ->orWhere('ctr_email', 'like', '%'.$search.'%')
                    ->orWhere('ctr_clab_no', 'like', '%'.$search.'%')
                    ->orWhere('ctr_contact_mobileno', 'like', '%'.$search.'%')
                    ->orWhere('ctr_telno', 'like', '%'.$search.'%')
                    ->orWhere('ctr_contact_name', 'like', '%'.$search.'%');
            });
        }

        // Apply sorting (map view sort keys -> contractor columns)
        $sortColumn = match ($this->sortBy) {
            'contractor_clab_no' => 'ctr_clab_no',
            'name' => 'ctr_comp_name',
            'person_in_charge' => 'ctr_contact_name',
            default => 'ctr_comp_name',
        };
        $query->orderBy($sortColumn, $this->sortDirection);

        // Map each contractor into the shape the view expects, merging contact
        // info (logged-in user preferred, contractors table as fallback) and stats.
        $contractors = $query->get()->map(function ($contractor) use ($pendingStats, $users) {
            $clab = $contractor->ctr_clab_no;
            $user = $users->get($clab);
            $stat = $pendingStats->get($clab);

            return (object) [
                'id' => $clab,
                'contractor_clab_no' => $clab,
                'name' => $contractor->ctr_comp_name,
                'email' => $user->email ?? $contractor->ctr_email ?? '',
                'phone' => $user->phone ?? $contractor->ctr_contact_mobileno ?? $contractor->ctr_telno ?? null,
                'person_in_charge' => $user->person_in_charge ?? $contractor->ctr_contact_name ?? null,
                'pending_payments' => $stat->pending_count ?? 0,
                'total_outstanding' => $stat->outstanding ?? 0,
            ];
        });

        // Apply status filter (depends on app-DB payroll data, so filter in PHP)
        if ($this->statusFilter) {
            if ($this->statusFilter === 'active' || $this->statusFilter === 'inactive') {
                $activeClabs = PayrollSubmission::where('created_at', '>=', now()->subMonths(3))
                    ->distinct()
                    ->pluck('contractor_clab_no')
                    ->flip();

                $contractors = $contractors->filter(function ($c) use ($activeClabs) {
                    $isActive = $activeClabs->has($c->contractor_clab_no);

                    return $this->statusFilter === 'active' ? $isActive : ! $isActive;
                })->values();
            } elseif ($this->statusFilter === 'with_pending') {
                $contractors = $contractors->filter(fn ($c) => $c->pending_payments > 0)->values();
            }
        }

        return $contractors;
    }

    public function render()
    {
        $allContractors = $this->getContractors();

        $currentPage = $this->getPage();
        $contractors = new LengthAwarePaginator(
            $allContractors->slice(($currentPage - 1) * $this->perPage, $this->perPage)->values(),
            $allContractors->count(),
            $this->perPage,
            $currentPage,
            ['path' => request()->url()]
        );

        return view('livewire.admin.contractors', [
            'contractors' => $contractors,
        ]);
    }
}
