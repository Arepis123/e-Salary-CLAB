<?php

namespace App\Livewire\Client;

use App\Models\PayrollPayment;
use App\Models\PayrollSubmission;
use Livewire\Component;
use Livewire\Attributes\Url;
use Carbon\Carbon;

class Payments extends Component
{
    #[Url]
    public $search = '';

    #[Url]
    public $year;

    #[Url]
    public $statusFilter = 'all';

    #[Url]
    public $page = 1;

    #[Url]
    public $sortBy = 'payment_date';

    #[Url]
    public $sortDirection = 'desc';

    public function mount()
    {
        if (!$this->year) {
            $this->year = now()->year;
        }
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedYear()
    {
        $this->resetPage();
    }

    public function updatedStatusFilter()
    {
        $this->resetPage();
    }

    public function resetFilters()
    {
        $this->search = '';
        $this->statusFilter = 'all';
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

    public function render()
    {
        $clabNo = auth()->user()->contractor_clab_no;

        if (!$clabNo) {
            return view('livewire.client.payments', [
                'error' => 'No contractor CLAB number assigned to your account.',
                'payments' => collect([]),
                'stats' => [
                    'this_month_amount' => 0,
                    'this_month_status' => null,
                    'last_month_amount' => 0,
                    'this_year_amount' => 0,
                    'this_year_count' => 0,
                    'avg_monthly' => 0,
                ],
                'pagination' => [
                    'current_page' => 1,
                    'per_page' => 10,
                    'total' => 0,
                    'last_page' => 1,
                    'from' => 0,
                    'to' => 0,
                ],
                'availableYears' => collect([]),
            ]);
        }

        // Get all payments for this contractor with submission details
        $allPayments = PayrollPayment::whereHas('submission', function ($query) use ($clabNo) {
            $query->where('contractor_clab_no', $clabNo)
                  ->whereYear('created_at', $this->year);
        })
        ->with(['submission'])
        ->get();

        // Apply search filter
        if ($this->search) {
            $allPayments = $allPayments->filter(function($payment) {
                $searchLower = strtolower($this->search);
                return str_contains(strtolower($payment->transaction_id ?? ''), $searchLower) ||
                       str_contains(strtolower($payment->billplz_bill_id ?? ''), $searchLower) ||
                       str_contains(strtolower($payment->submission->month_year ?? ''), $searchLower) ||
                       str_contains(strtolower($payment->payment_method ?? ''), $searchLower);
            });
        }

        // Apply status filter
        if ($this->statusFilter && $this->statusFilter !== 'all') {
            $allPayments = $allPayments->filter(function($payment) {
                return $payment->status === $this->statusFilter;
            });
        }

        // Apply sorting
        $allPayments = $allPayments->sort(function($a, $b) {
            $primaryA = match($this->sortBy) {
                'transaction_id' => $a->transaction_id ?? $a->billplz_bill_id ?? '',
                'period' => $a->submission->month_year ?? '',
                'amount' => $a->amount ?? 0,
                'workers' => $a->submission->total_workers ?? 0,
                'payment_date' => $a->completed_at ? $a->completed_at->timestamp : 0,
                'method' => strtolower($a->payment_method ?? ''),
                'status' => match($a->status) {
                    'completed' => 0,
                    'pending' => 1,
                    'failed' => 2,
                    default => 3,
                },
                default => $a->completed_at ? $a->completed_at->timestamp : 0,
            };

            $primaryB = match($this->sortBy) {
                'transaction_id' => $b->transaction_id ?? $b->billplz_bill_id ?? '',
                'period' => $b->submission->month_year ?? '',
                'amount' => $b->amount ?? 0,
                'workers' => $b->submission->total_workers ?? 0,
                'payment_date' => $b->completed_at ? $b->completed_at->timestamp : 0,
                'method' => strtolower($b->payment_method ?? ''),
                'status' => match($b->status) {
                    'completed' => 0,
                    'pending' => 1,
                    'failed' => 2,
                    default => 3,
                },
                default => $b->completed_at ? $b->completed_at->timestamp : 0,
            };

            // Primary sort comparison
            $comparison = $primaryA <=> $primaryB;

            // If primary values are equal, sort by payment_date as secondary
            if ($comparison === 0) {
                $dateA = $a->completed_at ? $a->completed_at->timestamp : 0;
                $dateB = $b->completed_at ? $b->completed_at->timestamp : 0;
                $comparison = $dateB <=> $dateA; // Most recent first
            }

            // Apply sort direction
            return $this->sortDirection === 'desc' ? -$comparison : $comparison;
        })->values();

        // Calculate statistics
        $currentMonth = now()->month;
        $currentYear = now()->year;

        // This month (pending + paid in current month)
        // Use total_due accessor to include dynamic penalty calculation
        $thisMonthSubmissions = PayrollSubmission::where('contractor_clab_no', $clabNo)
            ->where('month', $currentMonth)
            ->where('year', $currentYear)
            ->get();

        $thisMonthAmount = $thisMonthSubmissions->sum(function($submission) {
            return $submission->total_due;
        });

        $thisMonthStatus = $thisMonthSubmissions->first()?->status;

        // Last month (paid)
        $lastMonth = now()->subMonth();
        $lastMonthSubmissions = PayrollSubmission::where('contractor_clab_no', $clabNo)
            ->where('month', $lastMonth->month)
            ->where('year', $lastMonth->year)
            ->where('status', 'paid')
            ->get();

        $lastMonthAmount = $lastMonthSubmissions->sum(function($submission) {
            return $submission->total_due;
        });

        // This year total
        $thisYearSubmissions = PayrollSubmission::where('contractor_clab_no', $clabNo)
            ->where('year', $currentYear)
            ->where('status', 'paid')
            ->get();

        $thisYearAmount = $thisYearSubmissions->sum(function($submission) {
            return $submission->total_due;
        });

        $thisYearCount = PayrollSubmission::where('contractor_clab_no', $clabNo)
            ->where('year', $currentYear)
            ->where('status', 'paid')
            ->count();

        // Average monthly
        $avgMonthly = $thisYearCount > 0 ? $thisYearAmount / $thisYearCount : 0;

        // Available years for filter
        $availableYears = PayrollSubmission::where('contractor_clab_no', $clabNo)
            ->selectRaw('DISTINCT year')
            ->orderBy('year', 'desc')
            ->pluck('year');

        $stats = [
            'this_month_amount' => $thisMonthAmount,
            'this_month_status' => $thisMonthStatus,
            'last_month_amount' => $lastMonthAmount,
            'this_year_amount' => $thisYearAmount,
            'this_year_count' => $thisYearCount,
            'avg_monthly' => $avgMonthly,
        ];

        // Pagination
        $perPage = 10;
        $total = $allPayments->count();
        $payments = $allPayments->slice(($this->page - 1) * $perPage, $perPage)->values();

        $pagination = [
            'current_page' => $this->page,
            'per_page' => $perPage,
            'total' => $total,
            'last_page' => ceil($total / $perPage),
            'from' => (($this->page - 1) * $perPage) + 1,
            'to' => min($this->page * $perPage, $total),
        ];

        return view('livewire.client.payments', [
            'payments' => $payments,
            'stats' => $stats,
            'pagination' => $pagination,
            'availableYears' => $availableYears,
        ])->layout('components.layouts.app', ['title' => __('Payment History')]);
    }
}
