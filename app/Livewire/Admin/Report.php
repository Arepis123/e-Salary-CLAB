<?php

namespace App\Livewire\Admin;

use App\Exports\OTTransactionExport;
use App\Exports\PaymentSummaryExport;
use App\Exports\PayrollSubmissionsExport;
use App\Exports\TimesheetExport;
use App\Models\MonthlyOTEntry;
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

    public $timesheetData = [];

    public $otTransactionData = [];

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
        $this->timesheetData = [];
        $this->otTransactionData = [];
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
            case 'timesheet':
                $this->loadTimesheetData();
                break;
            case 'ot_transaction':
                $this->loadOTTransactionData();
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

    protected function loadTimesheetData()
    {
        $selectedMonth = $this->selectedMonth ?? now()->month;
        $selectedYear = $this->selectedYear ?? now()->year;

        // Get all payroll submissions for the period with workers
        $submissions = PayrollSubmission::where('month', $selectedMonth)
            ->where('year', $selectedYear)
            ->with(['user', 'workers'])
            ->orderBy('contractor_clab_no')
            ->get();

        if ($submissions->isEmpty()) {
            $this->timesheetData = [];

            return;
        }

        // Get contractor information
        $clabNos = $submissions->pluck('contractor_clab_no')->unique()->toArray();

        // Get OT entries with transactions for this submission period (previous month's OT)
        // e.g., for Jan 2026 payroll, get OT entries submitted in Jan 2026 (which are Dec 2025 OT)
        $otEntries = MonthlyOTEntry::with('transactions')
            ->where('submission_month', $selectedMonth)
            ->where('submission_year', $selectedYear)
            ->whereIn('contractor_clab_no', $clabNos)
            ->whereIn('status', ['submitted', 'locked'])
            ->get()
            ->groupBy(function ($entry) {
                return $entry->contractor_clab_no.'_'.$entry->worker_id;
            });

        // Get all active deduction templates
        $deductionTemplates = \App\Models\DeductionTemplate::active()
            ->orderBy('name')
            ->get();

        // Get contractor deductions (for contractor-level deductions)
        $contractorConfigs = \App\Models\ContractorConfiguration::whereIn('contractor_clab_no', $clabNos)
            ->with('deductions')
            ->get()
            ->keyBy('contractor_clab_no');

        // Get worker deductions (for worker-level deductions)
        $workerIds = $submissions->flatMap(fn ($s) => $s->workers->pluck('worker_id'))->unique()->toArray();
        $workerDeductions = \App\Models\WorkerDeduction::whereIn('worker_id', $workerIds)
            ->with('deductionTemplate')
            ->get()
            ->groupBy('worker_id');

        // Get worker emails from worker_db
        $workerEmails = \App\Models\Worker::whereIn('wkr_id', $workerIds)
            ->pluck('wkr_email', 'wkr_id');

        // Build timesheet data from payroll workers
        $timesheetData = [];

        foreach ($submissions as $submission) {
            $contractor = $submission->user;
            $contractorConfig = $contractorConfigs[$submission->contractor_clab_no] ?? null;
            $contractorDeductionIds = $contractorConfig
                ? $contractorConfig->deductions->pluck('id')->toArray()
                : [];

            foreach ($submission->workers as $worker) {
                // Get OT entry for this worker (from MonthlyOTEntry submitted this month)
                $otKey = $submission->contractor_clab_no.'_'.$worker->worker_id;
                $otEntry = $otEntries[$otKey]->first() ?? null;

                // Get allowance, advance, and client deduction from OT entry transactions
                $allowance = 0;
                $advanceSalary = 0;
                $clientDeduction = 0;

                if ($otEntry) {
                    foreach ($otEntry->transactions as $transaction) {
                        if ($transaction->type === 'allowance') {
                            $allowance += $transaction->amount;
                        } elseif ($transaction->type === 'advance_payment') {
                            $advanceSalary += $transaction->amount;
                        } elseif ($transaction->type === 'deduction') {
                            $clientDeduction += $transaction->amount;
                        }
                    }
                }

                // Get admin template deductions for this worker
                $workerDeductionsList = $workerDeductions[$worker->worker_id] ?? collect();

                $templateDeductions = [];
                foreach ($deductionTemplates as $template) {
                    $hasDeduction = false;

                    if ($template->type === 'contractor') {
                        // Contractor-level: check if contractor has this deduction enabled
                        $hasDeduction = in_array($template->id, $contractorDeductionIds);
                    } else {
                        // Worker-level: check if this specific worker has this deduction assigned
                        $hasDeduction = $workerDeductionsList->contains(function ($deduction) use ($template) {
                            return $deduction->deduction_template_id === $template->id;
                        });
                    }

                    if ($hasDeduction) {
                        $templateDeductions[] = [
                            'name' => $template->name,
                            'amount' => $template->amount,
                        ];
                    }
                }

                // Use OT hours from MonthlyOTEntry if available, otherwise from PayrollWorker
                $otNormal = $otEntry ? $otEntry->ot_normal_hours : $worker->ot_normal_hours;
                $otRest = $otEntry ? $otEntry->ot_rest_hours : $worker->ot_rest_hours;
                $otPublic = $otEntry ? $otEntry->ot_public_hours : $worker->ot_public_hours;

                $timesheetData[] = [
                    'worker_id' => $worker->worker_id,
                    'worker_email' => $workerEmails[$worker->worker_id] ?? '',
                    'worker_name' => $worker->worker_name,
                    'contractor_name' => $contractor ? $contractor->name : 'Unknown',
                    'contractor_state' => $contractor->state ?? '',
                    'salary' => $worker->basic_salary,
                    'allowance' => $allowance,
                    'advance_salary' => $advanceSalary,
                    'client_deduction' => $clientDeduction,
                    'template_deductions' => $templateDeductions,
                    'ot_normal' => $otNormal,
                    'ot_rest' => $otRest,
                    'ot_public' => $otPublic,
                    'status' => $submission->status,
                ];
            }
        }

        // Sort by contractor name then worker name
        usort($timesheetData, function ($a, $b) {
            $contractorCompare = strcmp($a['contractor_name'], $b['contractor_name']);
            if ($contractorCompare !== 0) {
                return $contractorCompare;
            }

            return strcmp($a['worker_name'], $b['worker_name']);
        });

        $this->timesheetData = $timesheetData;
    }

    public function exportTimesheetReport()
    {
        if (empty($this->timesheetData)) {
            Flux::toast(variant: 'error', text: 'No data to export. Please generate the Timesheet Report first.');

            return;
        }

        $selectedMonth = $this->selectedMonth ?? now()->month;
        $selectedYear = $this->selectedYear ?? now()->year;

        // Get all payroll submissions for the period with workers
        $submissions = PayrollSubmission::where('month', $selectedMonth)
            ->where('year', $selectedYear)
            ->with(['user', 'workers'])
            ->orderBy('contractor_clab_no')
            ->get();

        // Get contractor information
        $clabNos = $submissions->pluck('contractor_clab_no')->unique()->toArray();

        // Get OT entries with transactions for this submission period (previous month's OT)
        $otEntries = MonthlyOTEntry::with('transactions')
            ->where('submission_month', $selectedMonth)
            ->where('submission_year', $selectedYear)
            ->whereIn('contractor_clab_no', $clabNos)
            ->whereIn('status', ['submitted', 'locked'])
            ->get()
            ->groupBy(function ($entry) {
                return $entry->contractor_clab_no.'_'.$entry->worker_id;
            });

        // Get worker emails from worker_db
        $workerIds = $submissions->flatMap(fn ($s) => $s->workers->pluck('worker_id'))->unique()->toArray();
        $workerEmails = \App\Models\Worker::whereIn('wkr_id', $workerIds)
            ->pluck('wkr_email', 'wkr_id');

        // Build entries collection for export with all required data
        $entries = collect();

        foreach ($submissions as $submission) {
            $contractor = $submission->user;

            foreach ($submission->workers as $worker) {
                // Get OT entry for this worker
                $otKey = $submission->contractor_clab_no.'_'.$worker->worker_id;
                $otEntry = $otEntries[$otKey]->first() ?? null;

                // Use OT hours from MonthlyOTEntry if available, otherwise from PayrollWorker
                $otNormal = $otEntry ? $otEntry->ot_normal_hours : $worker->ot_normal_hours;
                $otRest = $otEntry ? $otEntry->ot_rest_hours : $worker->ot_rest_hours;
                $otPublic = $otEntry ? $otEntry->ot_public_hours : $worker->ot_public_hours;

                // Create an object with all required properties for TimesheetExport
                $entry = (object) [
                    'worker_id' => $worker->worker_id,
                    'worker_email' => $workerEmails[$worker->worker_id] ?? '',
                    'worker_name' => $worker->worker_name,
                    'contractor_clab_no' => $submission->contractor_clab_no,
                    'contractor_name' => $contractor ? $contractor->name : 'Unknown',
                    'contractor_state' => $contractor->state ?? '',
                    'worker_salary' => $worker->basic_salary,
                    'ot_normal_hours' => $otNormal,
                    'ot_rest_hours' => $otRest,
                    'ot_public_hours' => $otPublic,
                    'transactions' => $otEntry ? $otEntry->transactions : collect(),
                ];

                $entries->push($entry);
            }
        }

        // Sort by contractor name then worker name
        $entries = $entries->sortBy([
            ['contractor_name', 'asc'],
            ['worker_name', 'asc'],
        ])->values();

        $period = \Carbon\Carbon::create($selectedYear, $selectedMonth)->format('Y_m');
        $filename = 'timesheet_report_'.$period.'_'.now()->format('Ymd_His').'.xlsx';

        return Excel::download(
            new TimesheetExport($entries, ['month' => $selectedMonth, 'year' => $selectedYear]),
            $filename
        );
    }

    protected function loadOTTransactionData()
    {
        $selectedMonth = $this->selectedMonth ?? now()->month;
        $selectedYear = $this->selectedYear ?? now()->year;

        // Get all OT entries for the selected period (with their transactions)
        // This shows OT entries where entry_month/year matches the selected period
        $entries = MonthlyOTEntry::with('transactions')
            ->where('entry_month', $selectedMonth)
            ->where('entry_year', $selectedYear)
            ->whereIn('status', ['submitted', 'locked'])
            ->orderBy('contractor_clab_no')
            ->orderBy('worker_name')
            ->get();

        if ($entries->isEmpty()) {
            $this->otTransactionData = [];

            return;
        }

        // Get contractor information
        $clabNos = $entries->pluck('contractor_clab_no')->unique()->toArray();
        $contractors = \App\Models\User::whereIn('contractor_clab_no', $clabNos)
            ->get()
            ->keyBy('contractor_clab_no');

        // Get worker emails from worker_db
        $workerIds = $entries->pluck('worker_id')->unique()->toArray();
        $workerEmails = \App\Models\Worker::whereIn('wkr_id', $workerIds)
            ->pluck('wkr_email', 'wkr_id');

        // Get worker salary information from worker_db
        $workerSalaries = \App\Models\Worker::whereIn('wkr_id', $workerIds)
            ->pluck('wkr_salary', 'wkr_id');

        // Map entries with additional data
        $this->otTransactionData = $entries->map(function ($entry) use ($contractors, $workerEmails, $workerSalaries) {
            $contractor = $contractors[$entry->contractor_clab_no] ?? null;

            // Get allowance, advance, and deduction from transactions
            $allowance = 0;
            $advanceSalary = 0;
            $deduction = 0;

            foreach ($entry->transactions as $transaction) {
                if ($transaction->type === 'allowance') {
                    $allowance += $transaction->amount;
                } elseif ($transaction->type === 'advance_payment') {
                    $advanceSalary += $transaction->amount;
                } elseif ($transaction->type === 'deduction') {
                    $deduction += $transaction->amount;
                }
            }

            return [
                'id' => $entry->id,
                'worker_id' => $entry->worker_id,
                'worker_email' => $workerEmails[$entry->worker_id] ?? '',
                'worker_name' => $entry->worker_name,
                'contractor_clab_no' => $entry->contractor_clab_no,
                'contractor_name' => $contractor ? $contractor->name : 'Unknown',
                'contractor_state' => $contractor->state ?? '',
                'salary' => $workerSalaries[$entry->worker_id] ?? '',
                'entry_month' => $entry->entry_month,
                'entry_year' => $entry->entry_year,
                'entry_period' => $entry->entry_period,
                'allowance' => $allowance,
                'advance_salary' => $advanceSalary,
                'deduction' => $deduction,
                'ot_normal' => $entry->ot_normal_hours,
                'ot_rest' => $entry->ot_rest_hours,
                'ot_public' => $entry->ot_public_hours,
                'status' => $entry->status,
                'submitted_at' => $entry->submitted_at,
            ];
        })->toArray();
    }

    public function exportOTTransactionReport()
    {
        if (empty($this->otTransactionData)) {
            Flux::toast(variant: 'error', text: 'No data to export. Please generate the OT & Transaction Report first.');

            return;
        }

        $selectedMonth = $this->selectedMonth ?? now()->month;
        $selectedYear = $this->selectedYear ?? now()->year;

        // Get all OT entries for the selected period
        $entries = MonthlyOTEntry::with('transactions')
            ->where('entry_month', $selectedMonth)
            ->where('entry_year', $selectedYear)
            ->whereIn('status', ['submitted', 'locked'])
            ->orderBy('contractor_clab_no')
            ->orderBy('worker_name')
            ->get();

        // Get contractor information
        $clabNos = $entries->pluck('contractor_clab_no')->unique()->toArray();
        $contractors = \App\Models\User::whereIn('contractor_clab_no', $clabNos)
            ->get()
            ->keyBy('contractor_clab_no');

        // Get worker emails and salaries from worker_db
        $workerIds = $entries->pluck('worker_id')->unique()->toArray();
        $workers = \App\Models\Worker::whereIn('wkr_id', $workerIds)
            ->get()
            ->keyBy('wkr_id');

        // Add contractor and worker info to entries
        $entries = $entries->map(function ($entry) use ($contractors, $workers) {
            $contractor = $contractors[$entry->contractor_clab_no] ?? null;
            $worker = $workers[$entry->worker_id] ?? null;

            $entry->contractor_name = $contractor ? $contractor->name : 'Unknown';
            $entry->contractor_state = $contractor->state ?? '';
            $entry->worker_email = $worker->wkr_email ?? '';
            $entry->worker_salary = $worker->wkr_salary ?? '';

            return $entry;
        });

        $period = \Carbon\Carbon::create($selectedYear, $selectedMonth)->format('Y_m');
        $filename = 'ot_transaction_report_'.$period.'_'.now()->format('Ymd_His').'.xlsx';

        return Excel::download(
            new OTTransactionExport($entries, ['month' => $selectedMonth, 'year' => $selectedYear]),
            $filename
        );
    }

    public function render()
    {
        return view('livewire.admin.report');
    }
}
