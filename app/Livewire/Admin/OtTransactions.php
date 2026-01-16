<?php

namespace App\Livewire\Admin;

use App\Models\MonthlyOTEntry;
use App\Models\MonthlyOTEntryTransaction;
use App\Models\User;
use Livewire\Attributes\Url;
use Livewire\Component;

class OtTransactions extends Component
{
    public $stats = [];

    #[Url(except: '')]
    public $contractor = '';

    #[Url(except: '')]
    public $transactionType = '';

    #[Url(except: '')]
    public $statusFilter = '';

    #[Url(except: '')]
    public $search = '';

    #[Url(except: '')]
    public $selectedPeriod = '';

    #[Url(except: 1)]
    public $page = 1;

    public $availableMonths = [];

    public $contractors = [];

    public $perPage = 10;

    public $showFilters = true;

    public $sortBy = 'submitted_at';

    public $sortDirection = 'desc';

    // Detail Modal
    public $showDetailModal = false;

    public $selectedContractor = null;

    public $selectedEntries = [];

    public function mount()
    {
        $this->selectedPeriod = '';
        $this->generateAvailableMonths();
        $this->loadStats();
        $this->loadContractors();
    }

    protected function generateAvailableMonths()
    {
        $months = [];
        for ($i = 0; $i < 12; $i++) {
            $date = now()->subMonths($i);
            $months[] = [
                'value' => $date->format('Y-m'),
                'label' => $date->format('F Y'),
                'month' => $date->month,
                'year' => $date->year,
            ];
        }
        $this->availableMonths = $months;
    }

    protected function getSelectedMonthYear()
    {
        if (empty($this->selectedPeriod)) {
            return null;
        }

        foreach ($this->availableMonths as $month) {
            if ($month['value'] === $this->selectedPeriod) {
                return ['month' => $month['month'], 'year' => $month['year']];
            }
        }

        return null;
    }

    public function updatedSelectedPeriod()
    {
        $this->loadStats();
        $this->loadContractors();
        $this->resetPage();
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

    public function updatingTransactionType()
    {
        $this->resetPage();
    }

    public function updatingStatusFilter()
    {
        $this->resetPage();
    }

    public function clearFilters()
    {
        $this->contractor = '';
        $this->transactionType = '';
        $this->statusFilter = '';
        $this->search = '';
        $this->selectedPeriod = '';
        $this->loadStats();
        $this->loadContractors();
        $this->resetPage();
    }

    public function openDetail($clabNo, $month, $year)
    {
        $this->selectedContractor = User::where('contractor_clab_no', $clabNo)->first();

        $this->selectedEntries = MonthlyOTEntry::with('transactions')
            ->where('contractor_clab_no', $clabNo)
            ->where('entry_month', $month)
            ->where('entry_year', $year)
            ->orderBy('worker_name')
            ->get();

        $this->showDetailModal = true;
    }

    public function closeDetail()
    {
        $this->showDetailModal = false;
        $this->selectedContractor = null;
        $this->selectedEntries = [];
    }

    protected function loadStats()
    {
        $period = $this->getSelectedMonthYear();

        // Build base query for entries
        $entriesQuery = MonthlyOTEntry::query();
        if ($period) {
            $entriesQuery->where('entry_month', $period['month'])
                ->where('entry_year', $period['year']);
        }

        // Total OT Hours by type (only from submitted/locked entries)
        $otStats = (clone $entriesQuery)
            ->whereIn('status', ['submitted', 'locked'])
            ->selectRaw('
                COALESCE(SUM(ot_normal_hours), 0) as total_weekday_ot,
                COALESCE(SUM(ot_rest_hours), 0) as total_rest_ot,
                COALESCE(SUM(ot_public_hours), 0) as total_public_ot
            ')
            ->first();

        // Transaction totals by type
        $transactionQuery = MonthlyOTEntryTransaction::whereHas('monthlyOTEntry', function ($q) use ($period) {
            $q->whereIn('status', ['submitted', 'locked']);
            if ($period) {
                $q->where('entry_month', $period['month'])
                    ->where('entry_year', $period['year']);
            }
        });

        $transactionStats = (clone $transactionQuery)
            ->selectRaw('
                type,
                COALESCE(SUM(amount), 0) as total_amount,
                COUNT(*) as count
            ')
            ->groupBy('type')
            ->get()
            ->keyBy('type');

        // Count unique contractors who submitted
        $contractorsSubmitted = (clone $entriesQuery)
            ->whereIn('status', ['submitted', 'locked'])
            ->distinct('contractor_clab_no')
            ->count('contractor_clab_no');

        // Count unique contractors with draft entries
        $contractorsDraft = (clone $entriesQuery)
            ->where('status', 'draft')
            ->distinct('contractor_clab_no')
            ->count('contractor_clab_no');

        $this->stats = [
            'total_weekday_ot_hours' => $otStats->total_weekday_ot ?? 0,
            'total_rest_ot_hours' => $otStats->total_rest_ot ?? 0,
            'total_public_ot_hours' => $otStats->total_public_ot ?? 0,
            'total_advance_payment' => $transactionStats['advance_payment']->total_amount ?? 0,
            'total_deduction' => $transactionStats['deduction']->total_amount ?? 0,
            'total_npl_days' => $transactionStats['npl']->total_amount ?? 0,
            'total_allowance' => $transactionStats['allowance']->total_amount ?? 0,
            'contractors_submitted' => $contractorsSubmitted,
            'contractors_draft' => $contractorsDraft,
        ];
    }

    protected function loadContractors()
    {
        $period = $this->getSelectedMonthYear();

        $query = MonthlyOTEntry::query();

        if ($period) {
            $query->where('entry_month', $period['month'])
                ->where('entry_year', $period['year']);
        }

        $clabNos = $query->distinct('contractor_clab_no')
            ->pluck('contractor_clab_no')
            ->toArray();

        $this->contractors = User::whereIn('contractor_clab_no', $clabNos)
            ->orderBy('name')
            ->pluck('name', 'contractor_clab_no')
            ->toArray();
    }

    protected function getContractorSubmissions()
    {
        $period = $this->getSelectedMonthYear();

        // Get grouped data by contractor and period
        $query = MonthlyOTEntry::with('transactions')
            ->select('contractor_clab_no', 'entry_month', 'entry_year')
            ->selectRaw('MAX(status) as status')
            ->selectRaw('MAX(submitted_at) as submitted_at')
            ->selectRaw('COUNT(*) as worker_count')
            ->selectRaw('COALESCE(SUM(ot_normal_hours), 0) as total_ot_normal')
            ->selectRaw('COALESCE(SUM(ot_rest_hours), 0) as total_ot_rest')
            ->selectRaw('COALESCE(SUM(ot_public_hours), 0) as total_ot_public')
            ->groupBy('contractor_clab_no', 'entry_month', 'entry_year');

        // Apply period filter
        if ($period) {
            $query->where('entry_month', $period['month'])
                ->where('entry_year', $period['year']);
        }

        // Apply contractor filter
        if ($this->contractor) {
            $query->where('contractor_clab_no', $this->contractor);
        }

        // Apply status filter
        if ($this->statusFilter) {
            $query->havingRaw('MAX(status) = ?', [$this->statusFilter]);
        }

        // Apply search filter
        if ($this->search) {
            $matchingClabNos = User::where('name', 'like', '%'.$this->search.'%')
                ->pluck('contractor_clab_no')
                ->toArray();

            $query->whereIn('contractor_clab_no', $matchingClabNos);
        }

        // Apply transaction type filter
        if ($this->transactionType) {
            $query->whereHas('transactions', function ($q) {
                $q->where('type', $this->transactionType);
            });
        }

        // Get all results first for sorting
        $results = $query->get();

        // Add contractor names and transaction summaries
        $clabNos = $results->pluck('contractor_clab_no')->unique()->toArray();
        $users = User::whereIn('contractor_clab_no', $clabNos)->pluck('name', 'contractor_clab_no');

        // Get transaction summaries for each contractor-period
        $results = $results->map(function ($item) use ($users) {
            $item->contractor_name = $users[$item->contractor_clab_no] ?? 'Unknown';

            // Get transactions for this contractor-period
            $transactions = MonthlyOTEntryTransaction::whereHas('monthlyOTEntry', function ($q) use ($item) {
                $q->where('contractor_clab_no', $item->contractor_clab_no)
                    ->where('entry_month', $item->entry_month)
                    ->where('entry_year', $item->entry_year);
            })->get();

            $item->total_advance = $transactions->where('type', 'advance_payment')->sum('amount');
            $item->total_deduction = $transactions->where('type', 'deduction')->sum('amount');
            $item->total_npl = $transactions->where('type', 'npl')->sum('amount');
            $item->total_allowance = $transactions->where('type', 'allowance')->sum('amount');
            $item->has_transactions = $transactions->count() > 0;

            return $item;
        });

        // Sort results
        if ($this->sortBy === 'submitted_at') {
            $results = $this->sortDirection === 'desc'
                ? $results->sortByDesc('submitted_at')
                : $results->sortBy('submitted_at');
        } elseif ($this->sortBy === 'contractor_name') {
            $results = $this->sortDirection === 'desc'
                ? $results->sortByDesc('contractor_name')
                : $results->sortBy('contractor_name');
        } elseif ($this->sortBy === 'entry_month') {
            $results = $results->sort(function ($a, $b) {
                $dateA = $a->entry_year * 100 + $a->entry_month;
                $dateB = $b->entry_year * 100 + $b->entry_month;

                return $this->sortDirection === 'desc' ? $dateB - $dateA : $dateA - $dateB;
            });
        } elseif ($this->sortBy === 'status') {
            $results = $this->sortDirection === 'desc'
                ? $results->sortByDesc('status')
                : $results->sortBy('status');
        }

        return $results->values();
    }

    public function render()
    {
        $allSubmissions = $this->getContractorSubmissions();

        // Manual pagination
        $total = $allSubmissions->count();
        $submissions = $allSubmissions->slice(($this->page - 1) * $this->perPage, $this->perPage)->values();

        $pagination = [
            'current_page' => $this->page,
            'per_page' => $this->perPage,
            'total' => $total,
            'last_page' => max(1, ceil($total / $this->perPage)),
            'from' => $total > 0 ? (($this->page - 1) * $this->perPage) + 1 : 0,
            'to' => min($this->page * $this->perPage, $total),
        ];

        return view('livewire.admin.ot-transactions', [
            'submissions' => $submissions,
            'pagination' => $pagination,
        ]);
    }
}
