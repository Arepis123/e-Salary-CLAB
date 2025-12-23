<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\News;
use App\Models\PayrollSubmission;
use App\Services\ContractWorkerService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    protected ContractWorkerService $contractWorkerService;

    public function __construct(ContractWorkerService $contractWorkerService)
    {
        $this->contractWorkerService = $contractWorkerService;
    }

    public function index(Request $request)
    {
        // Get contractor CLAB number from authenticated user
        // Use username as fallback for matching with worker database
        $clabNo = $request->user()->contractor_clab_no ?? $request->user()->username;

        // If user doesn't have a CLAB number or username, show error
        if (! $clabNo) {
            return view('client.dashboard', [
                'error' => 'No contractor identifier assigned to your account. Please contact administrator.',
                'workers' => collect([]),
                'recentWorkers' => collect([]),
                'stats' => [
                    'total_workers' => 0,
                    'active_workers' => 0,
                    'expiring_soon' => 0,
                ],
                'expiringContracts' => collect([]),
                'paymentStats' => [
                    'this_month_amount' => 0,
                    'this_month_deadline' => null,
                    'outstanding_balance' => 0,
                    'year_to_date_paid' => 0,
                    'unsubmitted_workers' => 0,
                ],
                'recentPayments' => collect([]),
            ]);
        }

        // Get contracted workers for this contractor
        $workers = $this->contractWorkerService->getContractedWorkers($clabNo);

        // Get active contracts for this contractor
        $activeContracts = $this->contractWorkerService->getActiveContractsByContractor($clabNo);

        // Get contracts expiring soon (within 30 days)
        $allExpiringContracts = $this->contractWorkerService->getExpiringContracts(30);

        // Filter expiring contracts for this contractor only
        $expiringContracts = $allExpiringContracts->filter(function ($contract) use ($clabNo) {
            return $contract->con_ctr_clab_no === $clabNo;
        });

        // Calculate statistics
        $stats = [
            'total_workers' => $workers->count(),
            'active_workers' => $activeContracts->count(),
            'expiring_soon' => $expiringContracts->count(),
        ];

        // Get recent workers (limit to 4 for dashboard)
        $recentWorkers = $workers->take(4);

        // Get payment statistics
        $currentMonth = now()->month;
        $currentYear = now()->year;

        // Get this month's submission
        $thisMonthSubmission = PayrollSubmission::byContractor($clabNo)
            ->forMonth($currentMonth, $currentYear)
            ->first();

        // Get count of workers who haven't been submitted in any timesheet this month
        $allSubmissionsThisMonth = PayrollSubmission::where('contractor_clab_no', $clabNo)
            ->where('month', $currentMonth)
            ->where('year', $currentYear)
            ->with('workers')
            ->get();

        $submittedWorkerIds = $allSubmissionsThisMonth->flatMap(function ($submission) {
            return $submission->workers->pluck('worker_id');
        })->unique()->toArray();

        $remainingWorkers = $activeContracts->filter(function ($contract) use ($submittedWorkerIds) {
            return ! in_array($contract->worker->wkr_id, $submittedWorkerIds);
        });

        $unsubmittedWorkersCount = $remainingWorkers->count();

        // Calculate estimated payment for unsubmitted workers
        // Using PaymentCalculatorService to get accurate total payment to CLAB
        $paymentCalculator = app(\App\Services\PaymentCalculatorService::class);
        $estimatedUnsubmittedAmount = 0;

        foreach ($remainingWorkers as $contract) {
            $worker = $contract->worker;
            // Use worker's salary if available, otherwise use minimum wage (1700)
            $basicSalary = $worker->wkr_salary ?? 1700;
            // Calculate total payment (basic + employer contributions, no OT assumed)
            $estimatedUnsubmittedAmount += $paymentCalculator->calculateTotalPaymentToCLAB($basicSalary);
        }

        // Get outstanding balance (only finalized unpaid submissions, excluding drafts)
        // Use total_due accessor to include penalty calculation
        $outstandingSubmissions = PayrollSubmission::byContractor($clabNo)
            ->whereIn('status', ['pending_payment', 'overdue'])
            ->get();

        $outstandingBalance = $outstandingSubmissions->sum(function ($submission) {
            return $submission->total_due;
        });

        // Get year to date paid amount (including penalties)
        // Use total_due accessor to include penalty calculation
        $paidSubmissions = PayrollSubmission::byContractor($clabNo)
            ->where('year', $currentYear)
            ->where('status', 'paid')
            ->get();

        $yearToDatePaid = $paidSubmissions->sum(function ($submission) {
            return $submission->total_due;
        });

        $paymentStats = [
            'this_month_amount' => $thisMonthSubmission ? $thisMonthSubmission->total_due : 0,
            'this_month_deadline' => $thisMonthSubmission ? $thisMonthSubmission->payment_deadline : null,
            'this_month_status' => $thisMonthSubmission ? $thisMonthSubmission->status : null,
            'this_month_workers' => $thisMonthSubmission ? $thisMonthSubmission->total_workers : 0,
            'outstanding_balance' => $outstandingBalance,
            'year_to_date_paid' => $yearToDatePaid,
            'unsubmitted_workers' => $unsubmittedWorkersCount,
        ];

        // Get recent payments (last 3 months)
        $recentPayments = PayrollSubmission::byContractor($clabNo)
            ->with('payment')
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->take(3)
            ->get();

        // Get active news items for carousel
        $newsItems = News::active()->get();

        // Get missing/unpaid submissions from past 6 months (excluding current month)
        $missingSubmissions = $this->getMissingSubmissionsHistory($clabNo, 6);
        $overduePayments = $this->getOverduePayments($clabNo);
        $draftSubmissions = $this->getDraftSubmissions($clabNo);

        return view('client.dashboard', compact(
            'workers',
            'recentWorkers',
            'stats',
            'expiringContracts',
            'paymentStats',
            'recentPayments',
            'newsItems',
            'missingSubmissions',
            'overduePayments',
            'draftSubmissions'
        ));
    }

    protected function getDraftSubmissions($clabNo)
    {
        // Get all draft submissions (not finalized/submitted)
        $drafts = PayrollSubmission::byContractor($clabNo)
            ->where('status', 'draft')
            ->with('workers.worker') // Load workers relationship
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->get();

        return $drafts->map(function ($draft) use ($clabNo) {
            // Get total active workers for that period
            $activeWorkerIds = \App\Models\ContractWorker::where('con_ctr_clab_no', $clabNo)
                ->where('con_end', '>=', \Carbon\Carbon::create($draft->year, $draft->month, 1)->startOfMonth()->toDateString())
                ->where('con_start', '<=', \Carbon\Carbon::create($draft->year, $draft->month, 1)->endOfMonth()->toDateString())
                ->pluck('con_wkr_id')
                ->unique();

            // Get workers in draft
            $draftWorkerIds = $draft->workers->pluck('worker_id');

            // Get workers that have already been paid through finalized submissions for this same period
            $paidWorkerIds = \App\Models\PayrollWorker::whereHas('payrollSubmission', function ($query) use ($clabNo, $draft) {
                $query->where('contractor_clab_no', $clabNo)
                    ->where('month', $draft->month)
                    ->where('year', $draft->year)
                    ->where('status', '!=', 'draft') // Finalized submissions only
                    ->where('status', 'paid'); // Only paid submissions
            })
                ->whereIn('worker_id', $activeWorkerIds)
                ->pluck('worker_id')
                ->unique();

            // Get missing workers (not in draft AND not already paid)
            $missingWorkerIds = $activeWorkerIds->diff($draftWorkerIds)->diff($paidWorkerIds);

            $missingWorkerDetails = \App\Models\ContractWorker::whereIn('con_wkr_id', $missingWorkerIds)
                ->where('con_ctr_clab_no', $clabNo)
                ->with('worker')
                ->get()
                ->map(function ($contractWorker) {
                    return [
                        'worker_id' => $contractWorker->con_wkr_id,
                        'name' => $contractWorker->worker ? $contractWorker->worker->wkr_name : 'Unknown Worker',
                        'passport' => $contractWorker->worker ? $contractWorker->worker->wkr_passno : ($contractWorker->con_wkr_passno ?? 'N/A'),
                    ];
                });

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
        })->filter(function ($draft) {
            // Only show drafts that still have workers to submit (either in draft or missing)
            return ($draft['draft_workers'] + $draft['missing_workers']) > 0;
        })->values(); // Re-index array after filtering
    }

    protected function getMissingSubmissionsHistory($clabNo, $monthsBack = 6)
    {
        $result = collect();
        $currentDate = now();

        // Check last N months (excluding current month)
        for ($i = 1; $i <= $monthsBack; $i++) {
            $checkDate = $currentDate->copy()->subMonths($i);
            $month = $checkDate->month;
            $year = $checkDate->year;

            // Get active workers for that month
            $activeWorkerIds = \App\Models\ContractWorker::where('con_ctr_clab_no', $clabNo)
                ->where('con_end', '>=', $checkDate->startOfMonth()->toDateString())
                ->where('con_start', '<=', $checkDate->endOfMonth()->toDateString())
                ->pluck('con_wkr_id')
                ->unique();

            if ($activeWorkerIds->isEmpty()) {
                continue; // No workers that month, skip
            }

            // Check if a draft submission exists for that period
            $draftSubmission = PayrollSubmission::byContractor($clabNo)
                ->forMonth($month, $year)
                ->where('status', 'draft')
                ->first();

            // Skip if draft exists (will show in draft section)
            if ($draftSubmission) {
                continue;
            }

            // Get ALL finalized submissions for this period (there could be multiple)
            $finalizedSubmissions = PayrollSubmission::byContractor($clabNo)
                ->forMonth($month, $year)
                ->where('status', '!=', 'draft')
                ->get();

            // Get worker IDs that have been submitted in finalized submissions
            $submittedWorkerIds = collect();
            foreach ($finalizedSubmissions as $submission) {
                $workerIds = \App\Models\PayrollWorker::where('payroll_submission_id', $submission->id)
                    ->pluck('worker_id');
                $submittedWorkerIds = $submittedWorkerIds->merge($workerIds);
            }
            $submittedWorkerIds = $submittedWorkerIds->unique();

            // Calculate missing workers (active workers who were NOT submitted)
            $missingWorkerIds = $activeWorkerIds->diff($submittedWorkerIds);
            $missingCount = $missingWorkerIds->count();

            // Only show this period if there are missing workers
            if ($missingCount > 0) {
                // Load worker details for missing workers
                $missingWorkerDetails = \App\Models\ContractWorker::whereIn('con_wkr_id', $missingWorkerIds)
                    ->where('con_ctr_clab_no', $clabNo)
                    ->with('worker') // Load worker relationship for names
                    ->get()
                    ->map(function ($contractWorker) {
                        return [
                            'worker_id' => $contractWorker->con_wkr_id,
                            'name' => $contractWorker->worker ? $contractWorker->worker->wkr_name : 'Unknown Worker',
                            'passport' => $contractWorker->worker ? $contractWorker->worker->wkr_passno : ($contractWorker->con_wkr_passno ?? 'N/A'),
                        ];
                    });

                $result->push([
                    'month' => $month,
                    'year' => $year,
                    'month_label' => $checkDate->format('F Y'),
                    'total_workers' => $activeWorkerIds->count(),
                    'submitted_workers' => $submittedWorkerIds->count(),
                    'missing_workers' => $missingCount,
                    'missing_worker_details' => $missingWorkerDetails,
                    'has_submission' => $finalizedSubmissions->count() > 0,
                    'submission_status' => $finalizedSubmissions->first()?->status ?? null,
                ]);
            }
        }

        return $result;
    }

    protected function getOverduePayments($clabNo)
    {
        return PayrollSubmission::byContractor($clabNo)
            ->whereNotIn('status', ['paid', 'draft']) // Exclude paid and draft
            ->where('payment_deadline', '<', now())
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->get();
    }
}
