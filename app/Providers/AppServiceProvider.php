<?php

namespace App\Providers;

use App\Auth\ThirdPartyUserProvider;
use App\Models\PayrollSubmission;
use App\Services\ContractWorkerService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register custom authentication provider for third-party database
        Auth::provider('third_party', function ($app, array $config) {
            return new ThirdPartyUserProvider;
        });

        // Share notification counts with client sidebar
        View::composer('components.layouts.app.client-sidebar', function ($view) {
            $pendingNotifications = 0;
            $unpaidInvoicesCount = 0;

            if (auth()->check() && auth()->user()->role === 'client') {
                $clabNo = auth()->user()->contractor_clab_no ?? auth()->user()->username;

                if ($clabNo) {
                    // Check if we're within 7 days before month end
                    $daysUntilMonthEnd = now()->diffInDays(now()->endOfMonth(), false);
                    $isNearMonthEnd = $daysUntilMonthEnd >= 0 && $daysUntilMonthEnd <= 7;

                    // DEBUG MODE: Set to true to always show badge for testing
                    $debugMode = false;

                    if ($isNearMonthEnd || $debugMode) {
                        $currentMonth = now()->month;
                        $currentYear = now()->year;

                        // Get unsubmitted workers count
                        $contractWorkerService = app(ContractWorkerService::class);
                        $activeContracts = $contractWorkerService->getActiveContractsByContractor($clabNo);

                        $allSubmissionsThisMonth = PayrollSubmission::where('contractor_clab_no', $clabNo)
                            ->where('month', $currentMonth)
                            ->where('year', $currentYear)
                            ->with('workers')
                            ->get();

                        $submittedWorkerIds = $allSubmissionsThisMonth->flatMap(function ($submission) {
                            return $submission->workers->pluck('worker_id');
                        })->unique()->toArray();

                        $unsubmittedWorkersCount = $activeContracts->filter(function ($contract) use ($submittedWorkerIds) {
                            return ! in_array($contract->worker->wkr_id, $submittedWorkerIds);
                        })->count();

                        // Get pending payments count (submissions that need payment)
                        $pendingPaymentsCount = PayrollSubmission::byContractor($clabNo)
                            ->whereIn('status', ['pending_payment', 'overdue'])
                            ->count();

                        // Total pending notifications for Timesheet
                        $pendingNotifications = $unsubmittedWorkersCount + $pendingPaymentsCount;

                        // Unpaid invoices count for Invoices badge
                        $unpaidInvoicesCount = $pendingPaymentsCount;
                    }
                }
            }

            $view->with('pendingNotifications', $pendingNotifications);
            $view->with('unpaidInvoicesCount', $unpaidInvoicesCount);
        });
    }
}
