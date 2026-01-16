<?php

namespace App\Livewire\Admin;

use App\Exports\PaymentSummaryExport;
use App\Exports\PayrollSubmissionsExport;
use App\Models\PayrollPayment;
use App\Models\PayrollSubmission;
use App\Models\PayrollWorker;
use Flux\Flux;
use Livewire\Component;
use Maatwebsite\Excel\Facades\Excel;

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

    public $reportGenerated = false;

    public $taxInvoices = [];

    public $selectedInvoices = [];

    public $selectAll = false;

    public $downloadingReceipts = false;

    public $downloadCount = 0;

    public function updatedSelectAll($value)
    {
        if ($value) {
            $this->selectedInvoices = collect($this->taxInvoices)->pluck('id')->toArray();
        } else {
            $this->selectedInvoices = [];
        }
    }

    public function updatedSelectedInvoices()
    {
        $this->selectAll = count($this->selectedInvoices) === count($this->taxInvoices);
    }

    public function updatedReportType()
    {
        // Reset report when report type changes
        $this->reportGenerated = false;
        $this->initializeEmptyData();
    }

    public function mount()
    {
        // Set default to current month/year
        $this->selectedMonth = now()->month;
        $this->selectedYear = now()->year;

        // Generate available months (last 12 months)
        $this->generateAvailableMonths();

        // Initialize empty data
        $this->initializeEmptyData();
    }

    protected function initializeEmptyData()
    {
        $this->stats = [
            'total_paid' => 0,
            'pending_amount' => 0,
            'average_salary' => 0,
            'completed_payments' => 0,
            'pending_payments' => 0,
        ];

        $this->clientPayments = [];
        $this->topWorkers = [];
        $this->chartData = [
            'trend' => ['labels' => [], 'data' => []],
            'distribution' => ['labels' => [], 'data' => []],
        ];
        $this->taxInvoices = [];
        $this->selectedInvoices = [];
        $this->selectAll = false;
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
        if ($this->reportGenerated) {
            $this->generateReport();
        }
    }

    public function updatedSelectedYear()
    {
        if ($this->reportGenerated) {
            $this->generateReport();
        }
    }

    public function filterByMonthYear($monthYear)
    {
        [$year, $month] = explode('-', $monthYear);
        $this->selectedYear = (int) $year;
        $this->selectedMonth = (int) $month;

        if ($this->reportGenerated) {
            $this->loadClientPayments();
            $this->loadTopWorkers();
        }
    }

    protected function loadStats()
    {
        $selectedMonth = $this->selectedMonth ?? now()->month;
        $selectedYear = $this->selectedYear ?? now()->year;

        // Total paid for selected month
        $totalPaid = PayrollPayment::where('status', 'completed')
            ->whereYear('completed_at', $selectedYear)
            ->whereMonth('completed_at', $selectedMonth)
            ->sum('amount');

        // Pending amount for selected month
        $pendingAmount = PayrollSubmission::whereIn('status', ['pending_payment', 'overdue'])
            ->where('year', $selectedYear)
            ->where('month', $selectedMonth)
            ->sum('total_with_penalty');

        // Average salary (from all paid submissions)
        $totalWorkers = PayrollWorker::whereHas('submission', function ($query) use ($selectedYear, $selectedMonth) {
            $query->where('year', $selectedYear)
                ->where('month', $selectedMonth);
        })->count();

        $averageSalary = $totalWorkers > 0
            ? PayrollWorker::whereHas('submission', function ($query) use ($selectedYear, $selectedMonth) {
                $query->where('year', $selectedYear)
                    ->where('month', $selectedMonth);
            })->avg('net_salary')
            : 0;

        // Completed and pending payments for selected month
        $completedPayments = PayrollPayment::where('status', 'completed')
            ->whereYear('completed_at', $selectedYear)
            ->whereMonth('completed_at', $selectedMonth)
            ->count();

        $pendingPayments = PayrollSubmission::whereIn('status', ['pending_payment', 'overdue'])
            ->where('year', $selectedYear)
            ->where('month', $selectedMonth)
            ->count();

        $this->stats = [
            'total_paid' => $totalPaid,
            'pending_amount' => $pendingAmount,
            'average_salary' => round($averageSalary, 2),
            'completed_payments' => $completedPayments,
            'pending_payments' => $pendingPayments,
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

            $totalAmount = $contractorSubmissions->sum(function ($submission) {
                return ($submission->admin_final_amount ?? 0) + ($submission->total_with_penalty ?? 0);
            });

            // Determine status based on submissions
            $paidCount = $contractorSubmissions->filter(function ($submission) {
                return $submission->status === 'paid';
            })->count();

            $pendingCount = $contractorSubmissions->filter(function ($submission) {
                return in_array($submission->status, ['pending_payment', 'overdue', 'pending_review', 'submitted']);
            })->count();

            $totalSubmissions = $contractorSubmissions->count();

            if ($paidCount === $totalSubmissions) {
                $status = 'Paid';
            } elseif ($paidCount > 0 && $pendingCount > 0) {
                $status = 'Partially Paid';
            } else {
                $status = 'Pending';
            }

            $clientPayments[] = [
                'client' => $clientName,
                'workers' => $totalWorkers,
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
                $totalEarned = $worker->gross_salary;

                return [
                    'worker_id' => $worker->worker_id,
                    'name' => $worker->worker_name ?? ($worker->worker ? $worker->worker->wkr_name : 'Worker '.$worker->worker_id),
                    'position' => 'General Worker',
                    'client' => $worker->payrollSubmission && $worker->payrollSubmission->user
                        ? ($worker->payrollSubmission->user->company_name ?? $worker->payrollSubmission->user->name)
                        : 'N/A',
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

    public function generateReport()
    {
        // Reset all data before loading
        $this->initializeEmptyData();

        // Load data based on report type
        switch ($this->reportType) {
            case 'payment':
                $this->loadStats();
                $this->loadChartData();
                break;
            case 'worker':
                $this->loadTopWorkers();
                break;
            case 'client':
                $this->loadClientPayments();
                break;
            case 'tax':
                $this->loadTaxInvoices();
                break;
            default: // All Reports
                $this->loadStats();
                $this->loadClientPayments();
                $this->loadTopWorkers();
                $this->loadChartData();
                break;
        }

        $this->reportGenerated = true;

        Flux::toast(variant: 'success', text: 'Report generated successfully!');
    }

    public function exportClientPayments()
    {
        if (empty($this->clientPayments)) {
            Flux::toast(variant: 'error', text: 'No data to export. Please generate a report first.');

            return;
        }

        $filename = 'client_payment_summary_'.\Carbon\Carbon::create($this->selectedYear, $this->selectedMonth)->format('Y_m').'.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ];

        $callback = function () {
            $file = fopen('php://output', 'w');

            // Add BOM for Excel UTF-8 support
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            // Headers
            fputcsv($file, [
                'Client Name',
                'Total Workers',
                'Basic Salary (RM)',
                'Overtime (RM)',
                'Allowances (RM)',
                'Deductions (RM)',
                'Total Amount (RM)',
                'Status',
            ]);

            // Data rows
            foreach ($this->clientPayments as $client) {
                fputcsv($file, [
                    $client['client'],
                    $client['workers'],
                    number_format($client['basic_salary'], 2, '.', ''),
                    number_format($client['overtime'], 2, '.', ''),
                    number_format($client['allowances'], 2, '.', ''),
                    number_format($client['deductions'], 2, '.', ''),
                    number_format($client['total'], 2, '.', ''),
                    $client['status'],
                ]);
            }

            // Totals row
            fputcsv($file, [
                'TOTAL',
                collect($this->clientPayments)->sum('workers'),
                number_format(collect($this->clientPayments)->sum('basic_salary'), 2, '.', ''),
                number_format(collect($this->clientPayments)->sum('overtime'), 2, '.', ''),
                number_format(collect($this->clientPayments)->sum('allowances'), 2, '.', ''),
                number_format(collect($this->clientPayments)->sum('deductions'), 2, '.', ''),
                number_format(collect($this->clientPayments)->sum('total'), 2, '.', ''),
                '',
            ]);

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function exportPaymentSummary()
    {
        if (! $this->reportGenerated) {
            Flux::toast(
                variant: 'warning',
                heading: 'No data to export',
                text: 'Please generate a report first.'
            );

            return;
        }

        // Generate filename with current date
        $period = \Carbon\Carbon::create($this->selectedYear, $this->selectedMonth)->format('Y_m');
        $filename = 'payment_summary_'.$period.'_'.now()->format('Ymd_His').'.xlsx';

        // Return Excel download
        return Excel::download(
            new PaymentSummaryExport(
                $this->stats,
                $this->clientPayments,
                $this->topWorkers,
                $this->chartData,
                $this->selectedMonth,
                $this->selectedYear
            ),
            $filename
        );
    }

    public function exportAllReports()
    {
        if (! $this->reportGenerated) {
            Flux::toast(
                variant: 'warning',
                heading: 'No data to export',
                text: 'Please generate a report first.'
            );

            return;
        }

        $selectedMonth = $this->selectedMonth ?? now()->month;
        $selectedYear = $this->selectedYear ?? now()->year;

        // Count submissions first
        $submissionCount = PayrollSubmission::where('month', $selectedMonth)
            ->where('year', $selectedYear)
            ->count();

        // Check if there are submissions to export
        if ($submissionCount === 0) {
            Flux::toast(
                variant: 'warning',
                heading: 'No data to export',
                text: 'No payroll submissions found for the selected period.'
            );

            return;
        }

        // Increase memory limit and execution time for large exports
        ini_set('memory_limit', '512M');
        set_time_limit(300); // 5 minutes

        // Get all submissions for selected period with optimized loading
        $submissions = PayrollSubmission::where('month', $selectedMonth)
            ->where('year', $selectedYear)
            ->with([
                'user',
                'payment',
                'workers',
            ])
            ->orderBy('created_at', 'desc')
            ->get();

        // Prepare filter information for export
        $filters = [
            'period' => \Carbon\Carbon::create($selectedYear, $selectedMonth)->format('F Y'),
            'contractor' => $this->clientFilter ?? null,
        ];

        // Generate filename with period
        $period = \Carbon\Carbon::create($selectedYear, $selectedMonth)->format('Y_m');
        $filename = 'payroll_submissions_'.$period.'_'.now()->format('Ymd_His').'.xlsx';

        // Return Excel download with memory optimization
        return Excel::download(new PayrollSubmissionsExport($submissions, $filters), $filename);
    }

    protected function loadTaxInvoices()
    {
        $selectedMonth = $this->selectedMonth ?? now()->month;
        $selectedYear = $this->selectedYear ?? now()->year;

        // Get paid submissions with tax invoices for the selected period
        $invoices = PayrollSubmission::where('month', $selectedMonth)
            ->where('year', $selectedYear)
            ->where('status', 'paid')
            ->with(['user', 'payment', 'workers'])
            ->orderBy('created_at', 'desc')
            ->get();

        $this->taxInvoices = $invoices->map(function ($invoice) {
            // Generate tax invoice number if not exists
            if (! $invoice->hasTaxInvoice()) {
                $invoice->generateTaxInvoiceNumber();
                $invoice->refresh();
            }

            return [
                'id' => $invoice->id,
                'tax_invoice_number' => $invoice->tax_invoice_number,
                'contractor' => $invoice->user ? ($invoice->user->company_name ?? $invoice->user->name) : 'N/A',
                'contractor_clab_no' => $invoice->contractor_clab_no,
                'total_amount' => $invoice->total_with_penalty + ($invoice->admin_final_amount ?? 0),
                'paid_date' => $invoice->payment ? $invoice->payment->completed_at : null,
                'total_workers' => $invoice->workers->count(),
                'month_year' => $invoice->month_year,
            ];
        })->toArray();
    }

    public function downloadAllReceipts()
    {
        if (empty($this->taxInvoices)) {
            Flux::toast(variant: 'error', text: 'No receipts to download. Please generate the report first.');

            return;
        }

        $this->downloadReceipts(collect($this->taxInvoices)->pluck('id')->toArray());
    }

    public function downloadSelectedReceipts()
    {
        if (empty($this->selectedInvoices)) {
            Flux::toast(variant: 'warning', text: 'Please select at least one receipt to download.');

            return;
        }

        $this->downloadReceipts($this->selectedInvoices);
    }

    protected function downloadReceipts($invoiceIds)
    {
        // Set loading state
        $this->downloadingReceipts = true;
        $this->downloadCount = count($invoiceIds);

        // Redirect to controller route with invoice IDs as form data
        $this->dispatch('download-receipts', [
            'invoices' => $invoiceIds,
            'month' => $this->selectedMonth,
            'year' => $this->selectedYear,
        ]);
    }

    public function render()
    {
        return view('livewire.admin.report');
    }
}
