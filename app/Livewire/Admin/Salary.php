<?php

namespace App\Livewire\Admin;

use App\Exports\PayrollSubmissionsExport;
use App\Models\PayrollSubmission;
use Flux\Flux;
use Livewire\Attributes\Url;
use Livewire\Component;
use Maatwebsite\Excel\Facades\Excel;

class Salary extends Component
{
    public $stats = [];

    #[Url(except: '')]
    public $contractor = '';

    #[Url(except: '')]
    public $statusFilter = '';

    #[Url(except: '')]
    public $paymentStatusFilter = '';

    #[Url(except: '')]
    public $search = '';

    #[Url(except: '')]
    public $yearFilter = '';

    #[Url(except: '')]
    public $monthFilter = '';

    #[Url(except: 1)]
    public $page = 1;

    public $contractors = [];

    public $perPage = 10;

    public $showFilters = true;

    public $sortBy = 'created_at';

    public $sortDirection = 'desc';

    // Payment Log Modal
    public $showPaymentLog = false;

    public $selectedSubmission = null;

    // Loading states for lazy loading
    public $isLoadingStats = true;

    public $isLoadingTable = true;

    public function mount()
    {
        // Set default year to current year if not set
        if (empty($this->yearFilter)) {
            $this->yearFilter = (string) now()->year;
        }

        // Initialize with empty stats for fast initial render
        $this->stats = [
            'total_submissions' => 0,
            'grand_total' => 0,
            'completed' => 0,
            'pending' => 0,
        ];

        // Load contractors immediately (now fast since it queries Users table directly)
        $this->loadContractors();
    }

    /**
     * Load initial data - called via wire:init for fast initial render
     */
    public function loadInitialData()
    {
        $this->loadStats();
        $this->isLoadingStats = false;
        $this->isLoadingTable = false;
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
        // Get all submissions based on current filters
        $submissions = $this->getSubmissions();

        // Check if there are submissions to export
        if ($submissions->isEmpty()) {
            Flux::toast(
                variant: 'warning',
                heading: 'No data to export',
                text: 'No payroll submissions found matching your filters.'
            );

            return;
        }

        // Prepare filter information for export
        $filters = [
            'search' => $this->search,
            'contractor' => $this->contractor ? ($this->contractors[$this->contractor] ?? $this->contractor) : null,
            'status' => $this->statusFilter,
            'payment_status' => $this->paymentStatusFilter,
        ];

        // Generate filename with current date
        $filename = 'payroll_submissions_'.now()->format('Y-m-d_His').'.xlsx';

        // Return Excel download
        return Excel::download(new PayrollSubmissionsExport($submissions, $filters), $filename);
    }

    public function openPaymentLog($submissionId)
    {
        $this->selectedSubmission = PayrollSubmission::with(['user', 'payment', 'payments'])
            ->findOrFail($submissionId);
        $this->showPaymentLog = true;
    }

    public function closePaymentLog()
    {
        $this->showPaymentLog = false;
        $this->selectedSubmission = null;
    }

    public function resetPage()
    {
        $this->page = 1;
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingContractor()
    {
        $this->resetPage();
    }

    public function updatingStatusFilter()
    {
        $this->resetPage();
    }

    public function updatingPaymentStatusFilter()
    {
        $this->resetPage();
    }

    public function updatingYearFilter()
    {
        $this->resetPage();
    }

    public function updatingMonthFilter()
    {
        $this->resetPage();
    }

    public function clearFilters()
    {
        $this->contractor = '';
        $this->statusFilter = '';
        $this->paymentStatusFilter = '';
        $this->search = '';
        $this->yearFilter = (string) now()->year;
        $this->monthFilter = '';
        $this->resetPage();
    }

    protected function loadStats()
    {
        $currentMonth = now()->month;
        $currentYear = now()->year;

        // Total submissions for current payroll period (exclude drafts)
        $totalSubmissions = PayrollSubmission::where('month', $currentMonth)
            ->where('year', $currentYear)
            ->whereIn('status', ['submitted', 'approved', 'pending_payment', 'paid', 'overdue'])
            ->count();

        // Grand total for current payroll period (sum of admin final amount + grand total)
        $submissions = PayrollSubmission::where('month', $currentMonth)
            ->where('year', $currentYear)
            ->whereIn('status', ['approved', 'pending_payment', 'paid', 'overdue'])
            ->get();

        $grandTotal = $submissions->sum(function ($submission) {
            return ($submission->admin_final_amount ?? 0) + ($submission->grand_total ?? 0);
        });

        // Completed submissions (paid) for current payroll period
        $completed = PayrollSubmission::where('month', $currentMonth)
            ->where('year', $currentYear)
            ->where('status', 'paid')
            ->count();

        // Pending submissions (submitted + approved, waiting for action)
        $pending = PayrollSubmission::whereIn('status', ['submitted', 'approved', 'pending_payment'])
            ->count();

        $this->stats = [
            'total_submissions' => $totalSubmissions,
            'grand_total' => $grandTotal,
            'completed' => $completed,
            'pending' => $pending,
        ];
    }

    protected function loadContractors()
    {
        // Get contractors directly from Users table (much faster than loading all submissions)
        $this->contractors = \App\Models\User::where('role', 'client')
            ->whereNotNull('contractor_clab_no')
            ->orderBy('name')
            ->pluck('name', 'contractor_clab_no')
            ->toArray();
    }

    protected function getSubmissionsQuery()
    {
        $query = PayrollSubmission::with(['user', 'payment']);

        // Apply search filter
        if ($this->search) {
            $query->where(function ($q) {
                $q->where('id', 'like', '%'.$this->search.'%')
                    ->orWhereHas('user', function ($userQuery) {
                        $userQuery->where('name', 'like', '%'.$this->search.'%');
                    });
            });
        }

        // Apply contractor filter
        if ($this->contractor) {
            $query->where('contractor_clab_no', $this->contractor);
        }

        // Apply year filter
        if ($this->yearFilter) {
            $query->where('year', $this->yearFilter);
        }

        // Apply month filter
        if ($this->monthFilter) {
            $query->where('month', $this->monthFilter);
        }

        // Apply status filter
        if ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
        }

        // Apply payment status filter
        if ($this->paymentStatusFilter) {
            if ($this->paymentStatusFilter === 'paid') {
                $query->where('status', 'paid');
            } elseif ($this->paymentStatusFilter === 'awaiting') {
                $query->whereIn('status', ['approved', 'pending_payment', 'overdue']);
            }
        }

        // Apply sorting
        $query->orderBy($this->sortBy, $this->sortDirection);

        return $query;
    }

    /**
     * Get submissions for export (all matching records)
     */
    protected function getSubmissions()
    {
        return $this->getSubmissionsQuery()->get();
    }

    public function render()
    {
        // Use database-level pagination (much faster than loading all then slicing)
        $paginator = $this->getSubmissionsQuery()->paginate($this->perPage, ['*'], 'page', $this->page);

        $pagination = [
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
            'from' => $paginator->firstItem() ?? 0,
            'to' => $paginator->lastItem() ?? 0,
        ];

        return view('livewire.admin.salary', [
            'submissions' => $paginator->items(),
            'pagination' => $pagination,
        ]);
    }
}
