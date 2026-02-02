<?php

namespace App\Livewire\Admin;

use App\Models\PayrollPayment;
use App\Models\PayrollSubmission;
use App\Models\PayrollWorker;
use App\Models\User;
use Livewire\Component;

class Dashboard extends Component
{
    public $stats = [];

    public $recentPayments = [];

    public $chartData = [];

    public $contractorStatusChartData = [];

    public $selectedMonth;

    public $selectedYear;

    // Loading states for lazy-loaded sections
    public $isLoadingStats = true;

    public $isLoadingRecentPayments = true;

    public $isLoadingCharts = true;

    public function mount()
    {
        // Set default month based on payroll period logic:
        // Day 16 to end of month → show current month
        // Day 1 to 15 → show previous month
        $today = now();

        if ($today->day >= 16) {
            // 16th onwards: show current month
            $this->selectedMonth = $today->month;
            $this->selectedYear = $today->year;
        } else {
            // 1st to 15th: show previous month
            $previousMonth = $today->copy()->subMonth();
            $this->selectedMonth = $previousMonth->month;
            $this->selectedYear = $previousMonth->year;
        }

        // Initialize with empty/default data for immediate render
        $this->stats = [
            'clients_without_submission' => 0,
            'total_clients' => 0,
            'clients_with_submission_count' => 0,
            'active_workers' => 0,
            'this_month_payments' => 0,
            'outstanding_balance' => 0,
            'workers_growth' => 0,
            'payments_growth' => 0,
        ];

        $this->chartData = [
            'labels' => [],
            'totalPayments' => [],
            'numberOfPayments' => [],
        ];

        $this->contractorStatusChartData = [
            'labels' => ['Submitted & Paid', 'Submitted - Not Paid', 'Not Submitted'],
            'data' => [0, 0, 0],
            'colors' => ['#10b981', '#f59e0b', '#ef4444'],
        ];
    }

    /**
     * Load stats - called via wire:init for fast initial render
     */
    public function loadInitialData()
    {
        $this->loadStats();
        $this->isLoadingStats = false;
    }

    /**
     * Load deferred data (charts, recent payments) - called via wire:init
     */
    public function loadDeferredData()
    {
        $this->loadRecentPayments();
        $this->isLoadingRecentPayments = false;

        $this->loadChartData();
        $this->loadContractorStatusChartData();
        $this->isLoadingCharts = false;

        // Dispatch event to initialize charts after data is loaded
        $this->dispatch('chartsDataLoaded');
    }

    public function updatedSelectedMonth()
    {
        $this->loadContractorStatusChartData();
    }

    public function updatedSelectedYear()
    {
        $this->loadContractorStatusChartData();
    }

    protected function loadStats()
    {
        $currentMonth = now()->month;
        $currentYear = now()->year;
        $lastMonth = now()->subMonth()->month;
        $lastMonthYear = now()->subMonth()->year;

        // Get all clients
        $allClients = User::where('role', 'client')->get();
        $totalClients = $allClients->count();

        // Get clients who have submitted for current month (exclude drafts)
        $clientsWithSubmission = PayrollSubmission::where('month', $currentMonth)
            ->where('year', $currentYear)
            ->whereIn('status', ['submitted', 'approved', 'pending_payment', 'paid', 'overdue'])
            ->distinct('contractor_clab_no')
            ->pluck('contractor_clab_no');

        // Clients without submission this month
        $clientsWithoutSubmission = $allClients->whereNotIn('contractor_clab_no', $clientsWithSubmission)->count();

        // Previous month for comparison
        $clientsWithSubmissionLastMonth = PayrollSubmission::where('month', $lastMonth)
            ->where('year', $lastMonthYear)
            ->whereIn('status', ['submitted', 'approved', 'pending_payment', 'paid', 'overdue'])
            ->distinct('contractor_clab_no')
            ->count();

        // Active workers (unique workers from all submissions)
        $activeWorkers = PayrollWorker::distinct('worker_id')->count('worker_id');

        // This month payments
        $thisMonthPayments = PayrollPayment::where('status', 'completed')
            ->whereYear('completed_at', $currentYear)
            ->whereMonth('completed_at', $currentMonth)
            ->sum('amount');

        $lastMonthPayments = PayrollPayment::where('status', 'completed')
            ->whereYear('completed_at', $lastMonthYear)
            ->whereMonth('completed_at', $lastMonth)
            ->sum('amount');

        // Outstanding balance (approved + pending + overdue submissions)
        // Use total_due accessor to include penalty calculation
        $outstandingSubmissions = PayrollSubmission::whereIn('status', ['approved', 'pending_payment', 'overdue'])
            ->get();

        $outstandingBalance = $outstandingSubmissions->sum(function ($submission) {
            return $submission->total_due;
        });

        // Calculate growth
        $paymentsGrowth = $lastMonthPayments > 0
            ? round((($thisMonthPayments - $lastMonthPayments) / $lastMonthPayments) * 100, 1)
            : 0;

        $this->stats = [
            'clients_without_submission' => $clientsWithoutSubmission,
            'total_clients' => $totalClients,
            'clients_with_submission_count' => $clientsWithSubmission->count(),
            'active_workers' => $activeWorkers,
            'this_month_payments' => $thisMonthPayments,
            'outstanding_balance' => $outstandingBalance,
            'workers_growth' => 0, // TODO: Track worker growth over time
            'payments_growth' => $paymentsGrowth,
        ];
    }

    protected function loadRecentPayments()
    {
        $recentSubmissions = PayrollSubmission::with(['user', 'payments'])
            ->where('status', 'paid')
            ->whereHas('payments', function ($query) {
                // Check if submission has ANY completed payment (not just latest)
                $query->where('status', 'completed');
            })
            ->orderBy('paid_at', 'desc')
            ->limit(5)
            ->get();

        $this->recentPayments = $recentSubmissions->map(function ($submission) {
            $clientName = $submission->user
                ? $submission->user->name
                : 'Client '.$submission->contractor_clab_no;

            // Get actual completed payment (exclude redirect logs)
            $actualPayment = $submission->payments()
                ->where('status', 'completed')
                ->latest()
                ->first();

            $date = $actualPayment && $actualPayment->completed_at
                ? $actualPayment->completed_at->format('M d, Y')
                : $submission->paid_at->format('M d, Y');

            return [
                'client' => $clientName,
                'amount' => $submission->client_total,
                'workers' => $submission->total_workers,
                'date' => $date,
                'status' => 'completed',
            ];
        })->toArray();
    }

    protected function loadChartData()
    {
        $labels = [];
        $totalPayments = [];
        $numberOfPayments = [];

        // Get data for last 5 months + current month (6 months total)
        for ($i = 5; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $month = $date->month;
            $year = $date->year;

            $labels[] = $date->format('M Y');

            // Total payment amount for the month
            $monthTotal = PayrollPayment::where('status', 'completed')
                ->whereYear('completed_at', $year)
                ->whereMonth('completed_at', $month)
                ->sum('amount');

            $totalPayments[] = (float) $monthTotal;

            // Number of payments for the month
            $monthCount = PayrollPayment::where('status', 'completed')
                ->whereYear('completed_at', $year)
                ->whereMonth('completed_at', $month)
                ->count();

            $numberOfPayments[] = $monthCount;
        }

        $this->chartData = [
            'labels' => $labels,
            'totalPayments' => $totalPayments,
            'numberOfPayments' => $numberOfPayments,
        ];
    }

    protected function loadContractorStatusChartData()
    {
        // Use selected month/year instead of current
        $month = $this->selectedMonth;
        $year = $this->selectedYear;

        // Get all contractors
        $allContractors = User::where('role', 'client')->get();
        $totalContractors = $allContractors->count();

        // Contractors who submitted and paid
        $submittedAndPaid = PayrollSubmission::where('month', $month)
            ->where('year', $year)
            ->where('status', 'paid')
            ->distinct('contractor_clab_no')
            ->count('contractor_clab_no');

        // Contractors who submitted but not paid
        $submittedNotPaid = PayrollSubmission::where('month', $month)
            ->where('year', $year)
            ->whereIn('status', ['pending_payment', 'overdue'])
            ->distinct('contractor_clab_no')
            ->count('contractor_clab_no');

        // Contractors who haven't submitted at all
        $notSubmitted = $totalContractors - $submittedAndPaid - $submittedNotPaid;

        $this->contractorStatusChartData = [
            'labels' => ['Submitted & Paid', 'Submitted - Not Paid', 'Not Submitted'],
            'data' => [$submittedAndPaid, $submittedNotPaid, $notSubmitted],
            'colors' => ['#10b981', '#f59e0b', '#ef4444'], // green, orange, red
        ];
    }

    public function render()
    {
        return view('livewire.admin.dashboard');
    }
}
