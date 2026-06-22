<?php

namespace App\Livewire\Admin\Dashboard;

use App\Models\ContractWorker;
use App\Models\PayrollPayment;
use App\Models\PayrollSubmission;
use App\Models\PayrollWorker;
use App\Models\User;
use Livewire\Attributes\Lazy;
use Livewire\Component;

#[Lazy]
class StatsCards extends Component
{
    public $stats = [];

    public function mount(): void
    {
        $this->loadStats();
    }

    public function placeholder()
    {
        return view('livewire.admin.dashboard.placeholders.stats-cards');
    }

    protected function loadStats(): void
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

        // Feed the Alerts box (rendered by the parent) so the heavy outstanding
        // query above is not run a second time there.
        $this->dispatch('stats-loaded', outstandingBalance: $outstandingBalance);
    }

    public function render()
    {
        return view('livewire.admin.dashboard.stats-cards');
    }
}
