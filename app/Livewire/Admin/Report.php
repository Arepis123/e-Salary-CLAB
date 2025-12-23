<?php

namespace App\Livewire\Admin;

use App\Models\PayrollPayment;
use App\Models\PayrollSubmission;
use App\Models\PayrollWorker;
use Flux\Flux;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class Report extends Component
{
    public $reportType = '';

    public $period = '';

    public $clientFilter = '';

    public $selectedMonth;

    public $selectedYear;

    public $stats = [];

    public $clientPayments = [];

    public $topWorkers = [];

    public $chartData = [];

    public $availableMonths = [];

    public function mount()
    {
        // Set default to current month/year
        $this->selectedMonth = now()->month;
        $this->selectedYear = now()->year;

        // Generate available months (last 12 months)
        $this->generateAvailableMonths();

        $this->loadStats();
        $this->loadClientPayments();
        $this->loadTopWorkers();
        $this->loadChartData();
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

    public function updatedSelectedMonth()
    {
        $this->loadClientPayments();
        $this->loadTopWorkers();
    }

    public function updatedSelectedYear()
    {
        $this->loadClientPayments();
        $this->loadTopWorkers();
    }

    public function filterByMonthYear($monthYear)
    {
        [$year, $month] = explode('-', $monthYear);
        $this->selectedYear = (int) $year;
        $this->selectedMonth = (int) $month;

        $this->loadClientPayments();
        $this->loadTopWorkers();
    }

    protected function loadStats()
    {
        $currentMonth = now()->month;
        $currentYear = now()->year;

        // Total paid this month
        $totalPaid = PayrollPayment::where('status', 'completed')
            ->whereYear('completed_at', $currentYear)
            ->whereMonth('completed_at', $currentMonth)
            ->sum('amount');

        // Pending amount
        $pendingAmount = PayrollSubmission::whereIn('status', ['pending_payment', 'overdue'])
            ->sum('total_with_penalty');

        // Average salary (from all paid submissions)
        $totalWorkers = PayrollWorker::whereHas('submission', function ($query) use ($currentYear, $currentMonth) {
            $query->where('year', $currentYear)
                ->where('month', $currentMonth);
        })->count();

        $averageSalary = $totalWorkers > 0
            ? PayrollWorker::whereHas('submission', function ($query) use ($currentYear, $currentMonth) {
                $query->where('year', $currentYear)
                    ->where('month', $currentMonth);
            })->avg('net_salary')
            : 0;

        // Total hours worked
        $totalHours = PayrollWorker::whereHas('submission', function ($query) use ($currentYear, $currentMonth) {
            $query->where('year', $currentYear)
                ->where('month', $currentMonth);
        })->sum(DB::raw('regular_hours + ot_normal_hours + ot_rest_hours + ot_public_hours'));

        // Overtime hours
        $overtimeHours = PayrollWorker::whereHas('submission', function ($query) use ($currentYear, $currentMonth) {
            $query->where('year', $currentYear)
                ->where('month', $currentMonth);
        })->sum(DB::raw('ot_normal_hours + ot_rest_hours + ot_public_hours'));

        // Completed and pending payments
        $completedPayments = PayrollPayment::where('status', 'completed')
            ->whereYear('completed_at', $currentYear)
            ->whereMonth('completed_at', $currentMonth)
            ->count();

        $pendingPayments = PayrollSubmission::whereIn('status', ['pending_payment', 'overdue'])
            ->count();

        $this->stats = [
            'total_paid' => $totalPaid,
            'pending_amount' => $pendingAmount,
            'average_salary' => round($averageSalary, 2),
            'total_hours' => round($totalHours, 0),
            'completed_payments' => $completedPayments,
            'pending_payments' => $pendingPayments,
            'overtime_hours' => round($overtimeHours, 0),
        ];
    }

    protected function loadClientPayments()
    {
        // Use selected month/year or default to current
        $currentMonth = $this->selectedMonth ?? now()->month;
        $currentYear = $this->selectedYear ?? now()->year;

        // Get all submissions for selected month/year grouped by contractor
        $submissions = PayrollSubmission::where('month', $currentMonth)
            ->where('year', $currentYear)
            ->with(['workers', 'user'])
            ->get()
            ->groupBy('contractor_clab_no');

        $clientPayments = [];

        foreach ($submissions as $clabNo => $contractorSubmissions) {
            $firstSubmission = $contractorSubmissions->first();
            $clientName = $firstSubmission->user
                ? ($firstSubmission->user->company_name ?? $firstSubmission->user->name)
                : 'Contractor '.$clabNo;

            // Aggregate data across all submissions for this contractor
            $totalWorkers = $contractorSubmissions->sum(function ($submission) {
                return $submission->workers->count();
            });

            $totalHours = $contractorSubmissions->sum(function ($submission) {
                return $submission->workers->sum(function ($worker) {
                    return $worker->regular_hours + $worker->ot_normal_hours + $worker->ot_rest_hours + $worker->ot_public_hours;
                });
            });

            $totalBasicSalary = $contractorSubmissions->sum(function ($submission) {
                return $submission->workers->sum('basic_salary');
            });

            $totalOvertime = $contractorSubmissions->sum(function ($submission) {
                return $submission->workers->sum(function ($worker) {
                    return $worker->ot_normal_pay + $worker->ot_rest_pay + $worker->ot_public_pay;
                });
            });

            $totalAllowances = $contractorSubmissions->sum(function ($submission) {
                return $submission->workers->sum('allowance');
            });

            $totalDeductions = $contractorSubmissions->sum(function ($submission) {
                return $submission->workers->sum(function ($worker) {
                    return $worker->epf_employee + $worker->socso_employee + $worker->other_deductions;
                });
            });

            $totalAmount = $contractorSubmissions->sum('total_with_penalty');

            // Determine status based on submissions
            $hasAnyPaid = $contractorSubmissions->contains(function ($submission) {
                return $submission->status === 'paid';
            });
            $hasAnyPending = $contractorSubmissions->contains(function ($submission) {
                return in_array($submission->status, ['pending_payment', 'overdue']);
            });

            $status = $hasAnyPaid && ! $hasAnyPending ? 'Paid' : 'Pending';

            $clientPayments[] = [
                'client' => $clientName,
                'workers' => $totalWorkers,
                'hours' => round($totalHours, 0),
                'basic_salary' => round($totalBasicSalary, 2),
                'overtime' => round($totalOvertime, 2),
                'allowances' => round($totalAllowances, 2),
                'deductions' => round($totalDeductions, 2),
                'total' => round($totalAmount, 2),
                'status' => $status,
            ];
        }

        // Sort by total amount descending
        usort($clientPayments, function ($a, $b) {
            return $b['total'] <=> $a['total'];
        });

        $this->clientPayments = $clientPayments;
    }

    protected function loadTopWorkers()
    {
        // Use selected month/year or default to current
        $currentMonth = $this->selectedMonth ?? now()->month;
        $currentYear = $this->selectedYear ?? now()->year;

        // Get top 5 workers by total salary (including overtime) for selected month
        $topWorkers = PayrollWorker::whereHas('payrollSubmission', function ($query) use ($currentMonth, $currentYear) {
            $query->where('month', $currentMonth)
                ->where('year', $currentYear);
        })
            ->with(['payrollSubmission.user', 'worker'])
            ->get()
            ->map(function ($worker) {
                $totalHours = $worker->regular_hours + $worker->ot_normal_hours + $worker->ot_rest_hours + $worker->ot_public_hours;
                $totalEarned = $worker->gross_salary;

                return [
                    'worker_id' => $worker->worker_id,
                    'name' => $worker->worker_name ?? ($worker->worker ? $worker->worker->wkr_name : 'Worker '.$worker->worker_id),
                    'position' => 'General Worker',
                    'client' => $worker->payrollSubmission && $worker->payrollSubmission->user
                        ? ($worker->payrollSubmission->user->company_name ?? $worker->payrollSubmission->user->name)
                        : 'N/A',
                    'hours' => round($totalHours, 0),
                    'earned' => round($totalEarned, 2),
                ];
            })
            ->sortByDesc('earned')
            ->take(5)
            ->values();

        // Add rank
        $this->topWorkers = $topWorkers->map(function ($worker, $index) {
            $worker['rank'] = $index + 1;

            return $worker;
        })->toArray();
    }

    protected function loadChartData()
    {
        // Monthly trend: Get last 6 months of payment data
        $trendLabels = [];
        $trendData = [];

        for ($i = 5; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $month = $date->month;
            $year = $date->year;

            $trendLabels[] = $date->format('M');

            $total = PayrollSubmission::where('month', $month)
                ->where('year', $year)
                ->sum('total_with_penalty');

            $trendData[] = round($total, 2);
        }

        // Client distribution: Get current month data grouped by client
        $currentMonth = now()->month;
        $currentYear = now()->year;

        $distributionLabels = [];
        $distributionData = [];

        foreach ($this->clientPayments as $client) {
            $distributionLabels[] = $client['client'];
            $distributionData[] = $client['total'];
        }

        $this->chartData = [
            'trend' => [
                'labels' => $trendLabels,
                'data' => $trendData,
            ],
            'distribution' => [
                'labels' => $distributionLabels,
                'data' => $distributionData,
            ],
        ];
    }

    public function applyFilters()
    {
        // TODO: Implement filter logic
        Flux::toast(variant: 'success', text: 'Filters applied successfully!');
    }

    public function render()
    {
        return view('livewire.admin.report');
    }
}
