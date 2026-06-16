<?php

namespace App\Livewire\Admin;

use App\Models\ContractorConfiguration;
use App\Models\ContractWorker;
use App\Models\PayrollPayment;
use App\Models\PayrollSubmission;
use App\Models\PayrollWorker;
use App\Models\User;
use App\Services\ContractorWindowService;
use Flux\Flux;
use Livewire\Component;

class Dashboard extends Component
{
    public $stats = [];

    // Configuration reminder popup: contractors with an open OT window and/or
    // an active contractor-specific override (service charge / penalty exemption
    // or enabled deductions). Surfaced so admins don't forget these are active.
    public $configReminderWindows = [];

    public $configReminderSettings = [];

    public $recentPayments = [];

    public $contractorStatusChartData = [];

    public $topOverdueChartData = [];

    public $sectionContractors = [];

    public $sectionTitle = '';

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
            'last_month_payments' => 0,
            'outstanding_balance' => 0,
            'workers_growth' => 0,
            'payments_growth' => 0,
        ];

        $this->contractorStatusChartData = [
            'labels' => ['Submitted & Paid', 'Submitted - Not Paid', 'Not Submitted'],
            'data' => [0, 0, 0],
            'colors' => ['#10b981', '#f59e0b', '#ef4444'],
        ];

        $this->topOverdueChartData = [
            'labels' => [],
            'data' => [],
        ];
    }

    /**
     * Load stats - called via wire:init for fast initial render
     */
    public function loadInitialData()
    {
        $this->loadStats();
        $this->isLoadingStats = false;

        $this->loadConfigReminders();
    }

    /**
     * Build the configuration-reminder data and pop the modal when there are any
     * open OT windows or active contractor-specific settings. Runs on every
     * dashboard visit (via wire:init), so the popup appears each time either
     * area has something active.
     */
    protected function loadConfigReminders(): void
    {
        // Contractors with an open OT entry window.
        $this->configReminderWindows = app(ContractorWindowService::class)
            ->getAllContractorSettings()
            ->where('is_window_open', true)
            ->map(fn ($c) => [
                'name' => $c['contractor_name'],
                'clab_no' => $c['contractor_clab_no'],
            ])
            ->values()
            ->toArray();

        // Contractors with an active exemption override: service charge or penalty.
        $this->configReminderSettings = ContractorConfiguration::where(function ($q) {
            $q->where('service_charge_exempt', true)
                ->orWhere('penalty_exempt', true);
        })
            ->orderBy('contractor_name')
            ->get()
            ->map(fn ($config) => [
                'name' => $config->contractor_name,
                'clab_no' => $config->contractor_clab_no,
                'service_charge_exempt' => (bool) $config->service_charge_exempt,
                'penalty_exempt' => (bool) $config->penalty_exempt,
            ])
            ->values()
            ->toArray();

        // Show the popup when either area has something active.
        if (! empty($this->configReminderWindows) || ! empty($this->configReminderSettings)) {
            Flux::modal('config-reminder')->show();
        }
    }

    /**
     * Load deferred data (charts, recent payments) - called via wire:init
     */
    public function loadDeferredData()
    {
        $this->loadRecentPayments();
        $this->isLoadingRecentPayments = false;

        $this->loadContractorStatusChartData();
        $this->loadTopOverdueClients();
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
        $twoMonthsAgoMonth = now()->subMonths(2)->month;
        $twoMonthsAgoYear = now()->subMonths(2)->year;

        // Before the 16th, the current month has no submissions yet.
        // Use last month as "current" and two months ago as "previous" for comparisons.
        $isBeforeAutoSubmit = now()->day < 16;
        [$displayMonth, $displayYear, $prevMonth, $prevYear] = $isBeforeAutoSubmit
            ? [$lastMonth, $lastMonthYear, $twoMonthsAgoMonth, $twoMonthsAgoYear]
            : [$currentMonth, $currentYear, $lastMonth, $lastMonthYear];

        // Get clients that have at least one active worker
        $activeClabNos = ContractWorker::active()
            ->distinct('con_ctr_clab_no')
            ->pluck('con_ctr_clab_no')
            ->toArray();

        $allClients = User::where('role', 'client')
            ->whereIn('contractor_clab_no', $activeClabNos)
            ->get();
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

        // Active workers — use display period (last month before 16th, current month after)
        $activeWorkers = PayrollWorker::whereHas('submission', function ($q) use ($displayMonth, $displayYear) {
            $q->where('month', $displayMonth)->where('year', $displayYear);
        })->distinct('worker_id')->count('worker_id');

        // Active workers in the previous period for net change comparison
        $activeWorkersPrev = PayrollWorker::whereHas('submission', function ($q) use ($prevMonth, $prevYear) {
            $q->where('month', $prevMonth)->where('year', $prevYear);
        })->distinct('worker_id')->count('worker_id');

        // Net change
        $workersGrowth = $activeWorkers - $activeWorkersPrev;

        // This month payments
        $thisMonthPayments = PayrollPayment::where('status', 'completed')
            ->whereYear('completed_at', $currentYear)
            ->whereMonth('completed_at', $currentMonth)
            ->sum('amount');

        $lastMonthPayments = PayrollPayment::where('status', 'completed')
            ->whereYear('completed_at', $lastMonthYear)
            ->whereMonth('completed_at', $lastMonth)
            ->sum('amount');

        // Outstanding balance (approved + pending + overdue submissions).
        // The status filter already restricts this to legitimate, submitted payroll
        // (drafts are excluded), so no date guard is needed. Use the total_due
        // accessor to include any penalty calculation.
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
            'last_month_payments' => $lastMonthPayments,
            'outstanding_balance' => $outstandingBalance,
            'workers_growth' => $workersGrowth,
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

    protected function loadContractorStatusChartData()
    {
        // Use selected month/year instead of current
        $month = $this->selectedMonth;
        $year = $this->selectedYear;

        // Get contractors that have at least one active worker
        $activeClabNos = ContractWorker::active()
            ->distinct('con_ctr_clab_no')
            ->pluck('con_ctr_clab_no')
            ->toArray();

        $allContractors = User::where('role', 'client')
            ->whereIn('contractor_clab_no', $activeClabNos)
            ->get();
        $totalContractors = $allContractors->count();

        // Contractors who submitted and paid
        $submittedAndPaid = PayrollSubmission::where('month', $month)
            ->where('year', $year)
            ->where('status', 'paid')
            ->distinct('contractor_clab_no')
            ->count('contractor_clab_no');

        // Contractors who submitted but not paid (includes all non-paid statuses)
        $submittedNotPaid = PayrollSubmission::where('month', $month)
            ->where('year', $year)
            ->whereIn('status', ['submitted', 'approved', 'pending_review', 'pending_payment', 'overdue', 'draft'])
            ->distinct('contractor_clab_no')
            ->count('contractor_clab_no');

        // Contractors who haven't submitted at all for this period
        $allSubmittedClabNos = PayrollSubmission::where('month', $month)
            ->where('year', $year)
            ->pluck('contractor_clab_no')
            ->unique()
            ->toArray();

        $notSubmitted = $allContractors
            ->whereNotIn('contractor_clab_no', $allSubmittedClabNos)
            ->count();

        $this->contractorStatusChartData = [
            'labels' => ['Submitted & Paid', 'Submitted - Not Paid', 'Not Submitted'],
            'data' => [$submittedAndPaid, $submittedNotPaid, $notSubmitted],
            'colors' => ['#10b981', '#f59e0b', '#ef4444'], // green, orange, red
        ];
    }

    /**
     * Top 5 clients who most frequently miss their payroll payment deadline.
     *
     * A submission counts as overdue when it is either:
     *  - still unsettled past its deadline (currently overdue), or
     *  - paid, but settled after the deadline (historically late).
     */
    protected function loadTopOverdueClients()
    {
        $overdueSubmissions = PayrollSubmission::query()
            ->with('user')
            ->whereNotNull('payment_deadline')
            ->where(function ($q) {
                // Currently overdue: deadline passed and not yet settled
                $q->where(function ($q2) {
                    $q2->whereNotIn('status', ['paid', 'draft'])
                        ->whereDate('payment_deadline', '<', now()->startOfDay());
                })
                    // Or paid, but settled after the deadline (late payment)
                    ->orWhere(function ($q2) {
                        $q2->where('status', 'paid')
                            ->whereColumn('paid_at', '>', 'payment_deadline');
                    });
            })
            ->get();

        $topClients = $overdueSubmissions
            ->groupBy('contractor_clab_no')
            ->map(function ($group) {
                $first = $group->first();

                return [
                    'name' => $first->user->name ?? ('Client '.$first->contractor_clab_no),
                    'count' => $group->count(),
                ];
            })
            ->sortByDesc('count')
            ->take(5)
            ->values();

        $this->topOverdueChartData = [
            'labels' => $topClients->pluck('name')->toArray(),
            'data' => $topClients->pluck('count')->toArray(),
        ];
    }

    public function loadSectionContractors(int $sectionIndex): void
    {
        $month = $this->selectedMonth;
        $year = $this->selectedYear;

        if ($sectionIndex === 0) {
            $this->sectionTitle = 'Submitted & Paid';

            $submissions = PayrollSubmission::where('month', $month)
                ->where('year', $year)
                ->where('status', 'paid')
                ->with(['user', 'payment'])
                ->orderBy('contractor_clab_no')
                ->get();

            $this->sectionContractors = $submissions->map(function ($submission) {
                return [
                    'name' => $submission->user ? $submission->user->name : 'N/A',
                    'clab_no' => $submission->contractor_clab_no,
                    'amount' => $submission->payment ? $submission->payment->amount : $submission->client_total,
                    'paid_at' => $submission->paid_at ? $submission->paid_at->format('d/m/Y') : 'N/A',
                    'status' => 'Paid',
                ];
            })->toArray();

        } elseif ($sectionIndex === 1) {
            $this->sectionTitle = 'Submitted - Not Paid';

            $submissions = PayrollSubmission::where('month', $month)
                ->where('year', $year)
                ->whereIn('status', ['submitted', 'approved', 'pending_review', 'pending_payment', 'overdue', 'draft'])
                ->with('user')
                ->orderBy('contractor_clab_no')
                ->get();

            $this->sectionContractors = $submissions->map(function ($submission) {
                return [
                    'name' => $submission->user ? $submission->user->name : 'N/A',
                    'clab_no' => $submission->contractor_clab_no,
                    'amount' => $submission->total_due,
                    'paid_at' => null,
                    'status' => ucwords(str_replace('_', ' ', $submission->status)),
                ];
            })->toArray();

        } else {
            $this->sectionTitle = 'Not Submitted';

            $allSubmittedClabNos = PayrollSubmission::where('month', $month)
                ->where('year', $year)
                ->pluck('contractor_clab_no')
                ->unique()
                ->toArray();

            $activeClabNos = ContractWorker::active()
                ->distinct('con_ctr_clab_no')
                ->pluck('con_ctr_clab_no')
                ->toArray();

            $notSubmitted = User::where('role', 'client')
                ->whereIn('contractor_clab_no', $activeClabNos)
                ->whereNotIn('contractor_clab_no', $allSubmittedClabNos)
                ->orderBy('name')
                ->get();

            $this->sectionContractors = $notSubmitted->map(function ($user) {
                return [
                    'name' => $user->name,
                    'clab_no' => $user->contractor_clab_no,
                    'amount' => null,
                    'paid_at' => null,
                    'status' => 'Not Submitted',
                ];
            })->toArray();
        }

        Flux::modal('section-contractors')->show();
    }

    public function render()
    {
        return view('livewire.admin.dashboard');
    }
}
