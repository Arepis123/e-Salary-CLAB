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

    public function mount()
    {
        $this->loadStats();
        $this->loadContractors();
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

    public function clearFilters()
    {
        $this->contractor = '';
        $this->statusFilter = '';
        $this->paymentStatusFilter = '';
        $this->search = '';
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
        $pending = PayrollSubmission::whereIn('status', ['submitted', 'approved'])
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
        // Get unique contractors from submissions
        $this->contractors = PayrollSubmission::with('user')
            ->get()
            ->pluck('user')
            ->filter()
            ->unique('id')
            ->sortBy('name')
            ->pluck('name', 'contractor_clab_no')
            ->toArray();
    }

    protected function getSubmissions()
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

        // Apply status filter
        if ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
        }

        // Apply payment status filter
        if ($this->paymentStatusFilter) {
            if ($this->paymentStatusFilter === 'paid') {
                // Check submission status, not just latest payment
                $query->where('status', 'paid');
            } elseif ($this->paymentStatusFilter === 'awaiting') {
                // Check if submission is awaiting payment (approved, pending_payment, overdue)
                $query->whereIn('status', ['approved', 'pending_payment', 'overdue']);
            }
        }

        // Apply sorting
        $query->orderBy($this->sortBy, $this->sortDirection);

        return $query->get();
    }

    public function render()
    {
        $allSubmissions = $this->getSubmissions();

        // Manual pagination
        $total = $allSubmissions->count();
        $submissions = $allSubmissions->slice(($this->page - 1) * $this->perPage, $this->perPage)->values();

        $pagination = [
            'current_page' => $this->page,
            'per_page' => $this->perPage,
            'total' => $total,
            'last_page' => ceil($total / $this->perPage),
            'from' => (($this->page - 1) * $this->perPage) + 1,
            'to' => min($this->page * $this->perPage, $total),
        ];

        return view('livewire.admin.salary', [
            'submissions' => $submissions,
            'pagination' => $pagination,
        ]);
    }
}
