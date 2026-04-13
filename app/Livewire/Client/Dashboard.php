<?php

namespace App\Livewire\Client;

use App\Models\News;
use App\Models\PayrollSubmission;
use App\Services\ContractWorkerService;
use App\Services\PaymentCalculatorService;
use Livewire\Component;

class Dashboard extends Component
{
    // Loading states
    public bool $isLoadingStats = true;
    public bool $isLoadingContent = true;

    // Stats
    public array $stats = [
        'total_workers' => 0,
        'active_workers' => 0,
        'expiring_soon' => 0,
    ];

    public array $paymentStats = [
        'this_month_amount' => 0,
        'this_month_deadline' => null,
        'this_month_status' => null,
        'this_month_workers' => 0,
        'outstanding_balance' => 0,
        'year_to_date_paid' => 0,
        'unsubmitted_workers' => 0,
    ];

    // Content
    public $recentWorkers;
    public $recentPayments;
    public $overduePayments;
    public $draftSubmissions;
    public $missingSubmissions;
    public $newsItems;
    public $expiringContracts;

    public function mount()
    {
        $this->recentWorkers = collect();
        $this->recentPayments = collect();
        $this->overduePayments = collect();
        $this->draftSubmissions = collect();
        $this->missingSubmissions = collect();
        $this->newsItems = collect();
        $this->expiringContracts = collect();
    }

    public function loadInitialData(): void
    {
        $user = auth()->user();
        $clabNo = $user->contractor_clab_no ?? $user->username;

        if (! $clabNo) {
            $this->isLoadingStats = false;
            return;
        }

        $workerService = app(ContractWorkerService::class);
        $paymentCalculator = app(PaymentCalculatorService::class);

        $currentMonth = now()->month;
        $currentYear = now()->year;

        // Workers
        $workers = $workerService->getContractedWorkers($clabNo);
        $activeContracts = $workerService->getActiveContractsByContractor($clabNo);
        $expiringContracts = $workerService->getExpiringContracts(30)
            ->filter(fn ($c) => $c->con_ctr_clab_no === $clabNo);

        $this->stats = [
            'total_workers' => $workers->count(),
            'active_workers' => $activeContracts->count(),
            'expiring_soon' => $expiringContracts->count(),
        ];

        // Payment stats
        $thisMonthSubmission = PayrollSubmission::byContractor($clabNo)
            ->forMonth($currentMonth, $currentYear)
            ->first();

        $submittedWorkerIds = PayrollSubmission::where('contractor_clab_no', $clabNo)
            ->where('month', $currentMonth)
            ->where('year', $currentYear)
            ->with('workers')
            ->get()
            ->flatMap(fn ($s) => $s->workers->pluck('worker_id'))
            ->unique()
            ->toArray();

        $remainingWorkers = $activeContracts->filter(
            fn ($c) => ! in_array($c->worker->wkr_id, $submittedWorkerIds)
        );

        $estimatedUnsubmitted = $remainingWorkers->sum(function ($c) use ($paymentCalculator) {
            return $paymentCalculator->calculateTotalPaymentToCLAB($c->worker->wkr_salary ?? 1700);
        });

        $outstandingBalance = PayrollSubmission::byContractor($clabNo)
            ->whereIn('status', ['pending_payment', 'overdue'])
            ->get()
            ->sum(fn ($s) => $s->total_due);

        $yearToDatePaid = PayrollSubmission::byContractor($clabNo)
            ->where('year', $currentYear)
            ->where('status', 'paid')
            ->get()
            ->sum(fn ($s) => $s->total_due);

        $this->paymentStats = [
            'this_month_amount' => $thisMonthSubmission ? $thisMonthSubmission->total_due : 0,
            'this_month_deadline' => $thisMonthSubmission ? $thisMonthSubmission->payment_deadline : null,
            'this_month_status' => $thisMonthSubmission ? $thisMonthSubmission->status : null,
            'this_month_workers' => $thisMonthSubmission ? $thisMonthSubmission->total_workers : 0,
            'outstanding_balance' => $outstandingBalance,
            'year_to_date_paid' => $yearToDatePaid,
            'unsubmitted_workers' => $remainingWorkers->count(),
        ];

        $this->isLoadingStats = false;
    }

    public function loadDeferredData(): void
    {
        $user = auth()->user();
        $clabNo = $user->contractor_clab_no ?? $user->username;

        if (! $clabNo) {
            $this->isLoadingContent = false;
            return;
        }

        $workerService = app(ContractWorkerService::class);

        $workers = $workerService->getContractedWorkers($clabNo);
        $this->recentWorkers = $workers->take(4);

        $this->recentPayments = PayrollSubmission::byContractor($clabNo)
            ->with('payment')
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->take(3)
            ->get();

        $this->newsItems = News::active()->get();

        $this->overduePayments = PayrollSubmission::byContractor($clabNo)
            ->overdue()
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->get();

        $this->draftSubmissions = $this->getDraftSubmissions($clabNo);
        $this->missingSubmissions = $this->getMissingSubmissionsHistory($clabNo, 6);

        $this->isLoadingContent = false;
    }

    protected function getDraftSubmissions(string $clabNo)
    {
        $drafts = PayrollSubmission::byContractor($clabNo)
            ->where('status', 'draft')
            ->with('workers.worker')
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->get();

        return $drafts->map(function ($draft) use ($clabNo) {
            $activeWorkerIds = \App\Models\ContractWorker::where('con_ctr_clab_no', $clabNo)
                ->where('con_end', '>=', \Carbon\Carbon::create($draft->year, $draft->month, 1)->startOfMonth()->toDateString())
                ->where('con_start', '<=', \Carbon\Carbon::create($draft->year, $draft->month, 1)->endOfMonth()->toDateString())
                ->pluck('con_wkr_id')
                ->unique();

            $draftWorkerIds = $draft->workers->pluck('worker_id');

            $paidWorkerIds = \App\Models\PayrollWorker::whereHas('payrollSubmission', function ($q) use ($clabNo, $draft) {
                $q->where('contractor_clab_no', $clabNo)
                    ->where('month', $draft->month)
                    ->where('year', $draft->year)
                    ->where('status', 'paid');
            })
                ->whereIn('worker_id', $activeWorkerIds)
                ->pluck('worker_id')
                ->unique();

            $missingWorkerIds = $activeWorkerIds->diff($draftWorkerIds)->diff($paidWorkerIds);

            $missingWorkerDetails = \App\Models\ContractWorker::whereIn('con_wkr_id', $missingWorkerIds)
                ->where('con_ctr_clab_no', $clabNo)
                ->with('worker')
                ->get()
                ->map(fn ($cw) => [
                    'worker_id' => $cw->con_wkr_id,
                    'name' => $cw->worker?->wkr_name ?? 'Unknown Worker',
                    'passport' => $cw->worker?->wkr_passno ?? ($cw->con_wkr_passno ?? 'N/A'),
                ]);

            return [
                'id' => $draft->id,
                'month' => $draft->month,
                'year' => $draft->year,
                'month_label' => $draft->month_year,
                'total_workers' => $activeWorkerIds->count(),
                'draft_workers' => $draftWorkerIds->count(),
                'paid_workers' => $paidWorkerIds->count(),
                'missing_workers' => $missingWorkerIds->count(),
                'missing_worker_details' => $missingWorkerDetails,
                'created_at' => $draft->created_at,
            ];
        })->filter(fn ($d) => ($d['draft_workers'] + $d['missing_workers']) > 0)->values();
    }

    protected function getMissingSubmissionsHistory(string $clabNo, int $monthsBack = 6)
    {
        $result = collect();
        $currentDate = now();

        for ($i = 1; $i <= $monthsBack; $i++) {
            $checkDate = $currentDate->copy()->subMonths($i);
            $month = $checkDate->month;
            $year = $checkDate->year;

            $activeWorkerIds = \App\Models\ContractWorker::where('con_ctr_clab_no', $clabNo)
                ->where('con_end', '>=', $checkDate->copy()->startOfMonth()->toDateString())
                ->where('con_start', '<=', $checkDate->copy()->endOfMonth()->toDateString())
                ->pluck('con_wkr_id')
                ->unique();

            if ($activeWorkerIds->isEmpty()) continue;

            $draftExists = PayrollSubmission::byContractor($clabNo)
                ->forMonth($month, $year)
                ->where('status', 'draft')
                ->exists();

            if ($draftExists) continue;

            $finalizedSubmissions = PayrollSubmission::byContractor($clabNo)
                ->forMonth($month, $year)
                ->where('status', '!=', 'draft')
                ->get();

            $submittedWorkerIds = collect();
            foreach ($finalizedSubmissions as $submission) {
                $submittedWorkerIds = $submittedWorkerIds->merge(
                    \App\Models\PayrollWorker::where('payroll_submission_id', $submission->id)->pluck('worker_id')
                );
            }
            $submittedWorkerIds = $submittedWorkerIds->unique();

            $missingWorkerIds = $activeWorkerIds->diff($submittedWorkerIds);

            if ($missingWorkerIds->isEmpty()) continue;

            $missingWorkerDetails = \App\Models\ContractWorker::whereIn('con_wkr_id', $missingWorkerIds)
                ->where('con_ctr_clab_no', $clabNo)
                ->with('worker')
                ->get()
                ->map(fn ($cw) => [
                    'worker_id' => $cw->con_wkr_id,
                    'name' => $cw->worker?->wkr_name ?? 'Unknown Worker',
                    'passport' => $cw->worker?->wkr_passno ?? ($cw->con_wkr_passno ?? 'N/A'),
                ]);

            $result->push([
                'month' => $month,
                'year' => $year,
                'month_label' => $checkDate->format('F Y'),
                'total_workers' => $activeWorkerIds->count(),
                'submitted_workers' => $submittedWorkerIds->count(),
                'missing_workers' => $missingWorkerIds->count(),
                'missing_worker_details' => $missingWorkerDetails,
                'has_submission' => $finalizedSubmissions->count() > 0,
                'submission_status' => $finalizedSubmissions->first()?->status ?? null,
            ]);
        }

        return $result;
    }

    public function render()
    {
        return view('livewire.client.dashboard');
    }
}
