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
        // Get unique workers by grouping by passport number
        $payrollWorkers = PayrollWorker::whereHas('payrollSubmission', function ($query) {
            $query->where('contractor_clab_no', $this->contractorClabNo);
        })
            ->with(['worker.country', 'worker.workTrade', 'payrollSubmission'])
            ->selectRaw('worker_id, worker_passport, worker_name, MAX(id) as latest_id, MAX(payroll_submission_id) as latest_submission_id')
            ->groupBy('worker_id', 'worker_passport', 'worker_name')
            ->orderBy('worker_name', 'asc')
            ->get();

        // Enrich with worker details from worker_db
        $enrichedWorkers = $payrollWorkers->map(function ($payrollWorker) {
            $worker = $payrollWorker->worker;

            // Get the latest contract for this worker with this contractor
            $contract = ContractWorker::where('con_wkr_id', $payrollWorker->worker_id)
                ->where('con_ctr_clab_no', $this->contractorClabNo)
                ->orderBy('con_start', 'desc')
                ->first();

            // Get country name
            $country = $worker?->country?->cty_desc ?? $worker?->wkr_nationality ?? '-';

            // Get position/trade name
            $position = $worker?->workTrade?->trade_desc ?? $worker?->wkr_wtrade ?? '-';

            // Get contract period
            $contractStart = $contract?->con_start ?? null;
            $contractEnd = $contract?->con_end ?? null;

            // Count total submissions for this worker
            $totalSubmissions = PayrollWorker::where('worker_passport', $payrollWorker->worker_passport)
                ->whereHas('payrollSubmission', function ($query) {
                    $query->where('contractor_clab_no', $this->contractorClabNo);
                })
                ->count();

            return (object) [
                'worker_passport' => $payrollWorker->worker_passport,
                'worker_name' => $payrollWorker->worker_name,
                'country' => $country,
                'position' => $position,
                'contract_start' => $contractStart,
                'contract_end' => $contractEnd,
                'latest_submission_id' => $payrollWorker->latest_submission_id,
                'total_submissions' => $totalSubmissions,
            ];
        });

        // Manually paginate the results
        $perPage = $this->workersPerPage;
        $currentPage = $this->workersPage;
        $offset = ($currentPage - 1) * $perPage;

        $paginatedWorkers = $enrichedWorkers->slice($offset, $perPage);

        // Create a LengthAwarePaginator instance
        $paginator = new \Illuminate\Pagination\LengthAwarePaginator(
            $paginatedWorkers->values(),
            $enrichedWorkers->count(),
            $perPage,
            $currentPage,
            ['path' => request()->url(), 'pageName' => 'workersPage']
        );

        return $paginator;
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
