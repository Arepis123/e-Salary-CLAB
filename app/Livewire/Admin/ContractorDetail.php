<?php

namespace App\Livewire\Admin;

use App\Models\ContractWorker;
use App\Models\PayrollSubmission;
use App\Models\PayrollWorker;
use App\Models\User;
use App\Models\Worker;
use Livewire\Attributes\Url;
use Livewire\Component;

class ContractorDetail extends Component
{
    public $contractorClabNo;

    public $contractor;

    // Tabs
    public $activeTab = 'workers';

    // Pagination
    #[Url]
    public $workersPage = 1;

    #[Url]
    public $payrollPage = 1;

    public $workersPerPage = 10;

    public $payrollPerPage = 10;

    public function mount($clabNo)
    {
        $this->contractorClabNo = $clabNo;
        $this->contractor = User::where('contractor_clab_no', $clabNo)->firstOrFail();
    }

    public function setTab($tab)
    {
        $this->activeTab = $tab;
    }

    public function getWorkersProperty()
    {
        // Get all contracts for this contractor, then pick the latest per worker
        $contracts = ContractWorker::where('con_ctr_clab_no', $this->contractorClabNo)
            ->with(['worker.country', 'worker.workTrade'])
            ->get()
            ->groupBy('con_wkr_id')
            ->map(fn ($group) => $group->sortByDesc('con_start')->first());

        $enrichedWorkers = $contracts->map(function ($contract) {
            $worker = $contract->worker;

            $country = $worker?->country?->cty_desc ?? $worker?->wkr_nationality ?? '-';
            $position = $worker?->workTrade?->trade_desc ?? $worker?->wkr_wtrade ?? '-';
            $passport = $worker?->wkr_passno ?? $contract->con_wkr_passno;

            $latestPayrollWorker = PayrollWorker::where('worker_passport', $passport)
                ->whereHas('payrollSubmission', function ($query) {
                    $query->where('contractor_clab_no', $this->contractorClabNo);
                })
                ->orderByDesc('id')
                ->first();

            $totalSubmissions = PayrollWorker::where('worker_passport', $passport)
                ->whereHas('payrollSubmission', function ($query) {
                    $query->where('contractor_clab_no', $this->contractorClabNo);
                })
                ->count();

            return (object) [
                'worker_passport' => $passport,
                'worker_name' => $worker?->wkr_name ?? '-',
                'country' => $country,
                'position' => $position,
                'contract_start' => $contract->con_start,
                'contract_end' => $contract->con_end,
                'latest_submission_id' => $latestPayrollWorker?->payroll_submission_id,
                'total_submissions' => $totalSubmissions,
            ];
        })->sortBy('worker_name')->values();

        $perPage = $this->workersPerPage;
        $currentPage = $this->workersPage;
        $offset = ($currentPage - 1) * $perPage;

        return new \Illuminate\Pagination\LengthAwarePaginator(
            $enrichedWorkers->slice($offset, $perPage)->values(),
            $enrichedWorkers->count(),
            $perPage,
            $currentPage,
            ['path' => request()->url(), 'pageName' => 'workersPage']
        );
    }

    public function getPayrollHistoryProperty()
    {
        return PayrollSubmission::where('contractor_clab_no', $this->contractorClabNo)
            ->with(['payment'])
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->paginate($this->payrollPerPage, ['*'], 'payrollPage', $this->payrollPage);
    }

    public function getStatsProperty()
    {
        $submissions = PayrollSubmission::where('contractor_clab_no', $this->contractorClabNo);

        return [
            'total_submissions' => $submissions->count(),
            'total_workers' => PayrollWorker::whereHas('payrollSubmission', function ($query) {
                $query->where('contractor_clab_no', $this->contractorClabNo);
            })->distinct('worker_passport')->count('worker_passport'),
            'total_paid' => $submissions->where('status', 'paid')->sum('grand_total'),
            'total_outstanding' => $submissions->whereIn('status', ['pending_payment', 'overdue'])->sum('grand_total'),
            'pending_submissions' => $submissions->whereIn('status', ['pending_payment', 'overdue'])->count(),
        ];
    }

    public function render()
    {
        return view('livewire.admin.contractor-detail', [
            'stats' => $this->stats,
            'workers' => $this->workers,
            'payrollHistory' => $this->payrollHistory,
        ]);
    }
}
