<?php

namespace App\Livewire\Admin\Dashboard;

use App\Models\ContractWorker;
use App\Models\PayrollSubmission;
use App\Models\User;
use Flux\Flux;
use Livewire\Attributes\Lazy;
use Livewire\Component;

#[Lazy]
class SubmissionStatusChart extends Component
{
    public $contractorStatusChartData = [];

    public $sectionContractors = [];

    public $sectionTitle = '';

    public $selectedMonth;

    public $selectedYear;

    public function mount(): void
    {
        // Set default month based on payroll period logic:
        // Day 16 to end of month → show current month
        // Day 1 to 15 → show previous month
        $today = now();

        if ($today->day >= 16) {
            $this->selectedMonth = $today->month;
            $this->selectedYear = $today->year;
        } else {
            $previousMonth = $today->copy()->subMonth();
            $this->selectedMonth = $previousMonth->month;
            $this->selectedYear = $previousMonth->year;
        }

        $this->loadContractorStatusChartData();

        // Tell the parent's chart JS the data is ready to render.
        $this->dispatch('chartsDataLoaded');

        // The charts are the last of the parallel sections to finish. Once they
        // are loaded, trigger the config-reminder popup as its own round-trip so
        // it never competes with the primary dashboard data.
        $this->dispatch('dashboard-data-loaded');
    }

    public function placeholder()
    {
        return view('livewire.admin.dashboard.placeholders.submission-status-chart');
    }

    public function updatedSelectedMonth(): void
    {
        $this->loadContractorStatusChartData();
    }

    public function updatedSelectedYear(): void
    {
        $this->loadContractorStatusChartData();
    }

    /**
     * Contractors expected to have a payroll for the given period.
     *
     * A contractor is only "expected" if it had at least one worker whose
     * contract overlaps the selected month (con_start <= period end AND
     * con_end >= period start). Using the current `active()` scope instead
     * would wrongly count companies that registered/started after a past
     * period as "Not Submitted" for payrolls that never should have existed.
     */
    protected function expectedClabNosForPeriod(int $month, int $year): array
    {
        $periodStart = \Carbon\Carbon::create($year, $month, 1)->startOfMonth()->toDateString();
        $periodEnd = \Carbon\Carbon::create($year, $month, 1)->endOfMonth()->toDateString();

        return ContractWorker::where('con_end', '>=', $periodStart)
            ->where('con_start', '<=', $periodEnd)
            ->distinct('con_ctr_clab_no')
            ->pluck('con_ctr_clab_no')
            ->toArray();
    }

    protected function loadContractorStatusChartData(): void
    {
        $month = $this->selectedMonth;
        $year = $this->selectedYear;

        // Contractors that had a worker under contract during this period.
        $activeClabNos = $this->expectedClabNosForPeriod($month, $year);

        $allContractors = User::where('role', 'client')
            ->whereIn('contractor_clab_no', $activeClabNos)
            ->get();

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

            $activeClabNos = $this->expectedClabNosForPeriod($month, $year);

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
        return view('livewire.admin.dashboard.submission-status-chart');
    }
}
