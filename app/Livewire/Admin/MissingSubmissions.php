<?php

namespace App\Livewire\Admin;

use App\Mail\PayrollReminderMail;
use App\Models\Contractor;
use App\Models\ContractWorker;
use App\Models\PayrollReminder;
use App\Models\PayrollSubmission;
use App\Models\PayrollWorker;
use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Response;
use Livewire\Component;

class MissingSubmissions extends Component
{
    public $missingContractors = [];

    public $showRemindModal = false;

    public $selectedContractor = null;

    public $reminderMessage = '';

    public $pastReminders;

    // Bulk submission properties
    public $showBulkSubmitModal = false;

    public $bulkSubmitContractor = null;

    public $bulkSubmitMessage = '';

    // Filter properties
    public $selectedMonth;

    public $selectedYear;

    public $availableMonths = [];

    public $availableYears = [];

    // Historical tracking
    public $historicalSummary = [];

    public $showHistoricalSummary = true;

    // Pagination
    public $historicalPage = 1;

    public $historicalPerPage = 10;

    public $currentPage = 1;

    public $currentPerPage = 10;

    public function mount()
    {
        $this->pastReminders = collect();

        // Set default to current month/year
        $this->selectedMonth = now()->month;
        $this->selectedYear = now()->year;

        // Generate available months and years
        $this->generateAvailablePeriodsFromSubmissions();

        $this->loadMissingContractors();
        $this->loadHistoricalSummary();
    }

    public function toggleHistoricalSummary()
    {
        $this->showHistoricalSummary = ! $this->showHistoricalSummary;
    }

    public function refresh()
    {
        $previousCount = $this->missingContractors->count();

        $this->currentPage = 1;
        $this->loadMissingContractors();

        $newCount = $this->missingContractors->count();

        if ($previousCount === 0 && $newCount === 0) {
            Flux::toast(
                variant: 'success',
                heading: 'Data refreshed',
                text: 'All contractors have submitted their payroll.'
            );
        } elseif ($newCount < $previousCount) {
            $difference = $previousCount - $newCount;
            Flux::toast(
                variant: 'success',
                heading: 'Data refreshed',
                text: "{$difference} ".\Illuminate\Support\Str::plural('contractor', $difference).' submitted since last refresh!'
            );
        } elseif ($newCount > $previousCount) {
            $difference = $newCount - $previousCount;
            Flux::toast(
                variant: 'warning',
                heading: 'Data refreshed',
                text: "{$difference} new ".\Illuminate\Support\Str::plural('contractor', $difference).' with missing submissions.'
            );
        } else {
            Flux::toast(
                variant: 'info',
                heading: 'Data refreshed',
                text: 'No changes. Still '.$newCount.' '.\Illuminate\Support\Str::plural('contractor', $newCount).' with missing submissions.'
            );
        }
    }

    public function openRemindModal($clabNo)
    {
        $this->selectedContractor = collect($this->missingContractors)->firstWhere('clab_no', $clabNo);

        if ($this->selectedContractor) {
            // Load past reminders for this contractor (selected month/year)
            $this->pastReminders = PayrollReminder::where('contractor_clab_no', $clabNo)
                ->where('month', $this->selectedMonth)
                ->where('year', $this->selectedYear)
                ->orderBy('created_at', 'desc')
                ->get();

            // Set default reminder message
            $periodLabel = \Carbon\Carbon::create($this->selectedYear, $this->selectedMonth, 1)->format('F Y');
            $this->reminderMessage = "Dear {$this->selectedContractor['name']},\n\n";

            // Build message based on issue types
            $issues = [];
            if ($this->selectedContractor['not_submitted'] > 0) {
                $issues[] = "{$this->selectedContractor['not_submitted']} worker(s) payroll not yet submitted";
            }
            if ($this->selectedContractor['submitted_not_paid'] > 0) {
                $issues[] = "{$this->selectedContractor['submitted_not_paid']} worker(s) submitted but payment not completed";
            }

            $this->reminderMessage .= "This is a friendly reminder regarding the following outstanding items for {$periodLabel}:\n\n";
            foreach ($issues as $issue) {
                $this->reminderMessage .= "• {$issue}\n";
            }
            $this->reminderMessage .= "\nPlease complete the required actions at your earliest convenience to avoid any delays or penalties.\n\n";
            $this->reminderMessage .= "Thank you for your cooperation.\n\n";
            $this->reminderMessage .= "Best regards,\ne-Salary CLAB System";

            $this->showRemindModal = true;
        }
    }

    public function closeRemindModal()
    {
        $this->showRemindModal = false;
        $this->selectedContractor = null;
        $this->reminderMessage = '';
        $this->pastReminders = collect();
    }

    public function openBulkSubmitModal($clabNo)
    {
        $this->bulkSubmitContractor = collect($this->missingContractors)->firstWhere('clab_no', $clabNo);

        if ($this->bulkSubmitContractor) {
            $periodLabel = \Carbon\Carbon::create($this->selectedYear, $this->selectedMonth, 1)->format('F Y');
            $this->bulkSubmitMessage = "You are about to create and <strong>submit</strong> a payroll submission for <strong>{$this->bulkSubmitContractor['name']}</strong> for the period of <strong>{$periodLabel}</strong>.<br><br>This will include all their active workers with basic salary and zero overtime. This action is final for this submission period and will move directly to the approval stage.<br><br>Are you sure you want to proceed?";
            $this->showBulkSubmitModal = true;
        }
    }

    public function closeBulkSubmitModal()
    {
        $this->showBulkSubmitModal = false;
        $this->bulkSubmitContractor = null;
        $this->bulkSubmitMessage = '';
    }

    public function performBulkSubmission()
    {
        if (! $this->bulkSubmitContractor) {
            return;
        }

        $clabNo = $this->bulkSubmitContractor['clab_no'];
        $month = $this->selectedMonth;
        $year = $this->selectedYear;

        try {
            DB::transaction(function () use ($clabNo, $month, $year) {
                $existingSubmission = PayrollSubmission::where('contractor_clab_no', $clabNo)
                    ->where('month', $month)
                    ->where('year', $year)
                    ->first();

                if ($existingSubmission) {
                    throw new \Exception('A submission for this period already exists.');
                }

                $targetDate = \Carbon\Carbon::create($year, $month, 1);
                $activeContractWorkers = ContractWorker::with('worker')
                    ->where('con_ctr_clab_no', $clabNo)
                    ->where('con_end', '>=', $targetDate->startOfMonth()->toDateString())
                    ->where('con_start', '<=', $targetDate->endOfMonth()->toDateString())
                    ->get();

                if ($activeContractWorkers->isEmpty()) {
                    throw new \Exception('No active workers found for this contractor for the selected period.');
                }

                // Find the Client User to assign ownership (so they see it in their dashboard)
                $user = User::where('contractor_clab_no', $clabNo)
                    ->where('role', 'client')
                    ->first();

                if (! $user) {
                    // Auto-provision user account if they haven't logged in yet
                    $contractorInfo = Contractor::where('ctr_clab_no', $clabNo)->first();

                    $name = $contractorInfo ? $contractorInfo->ctr_comp_name : $this->bulkSubmitContractor['name'];
                    $email = ($contractorInfo && $contractorInfo->ctr_email) ? $contractorInfo->ctr_email : ($this->bulkSubmitContractor['email'] ?? $clabNo.'@placeholder.local');

                    $user = User::create([
                        'username' => $clabNo,
                        'name' => $name,
                        'email' => $email,
                        'password' => \Illuminate\Support\Facades\Hash::make(\Illuminate\Support\Str::random(32)),
                        'role' => 'client',
                        'contractor_clab_no' => $clabNo,
                        'phone' => $contractorInfo ? ($contractorInfo->ctr_contact_mobileno ?? $contractorInfo->ctr_telno) : null,
                    ]);
                }

                // Create Submission: user_id = Client, submitted_by = Admin
                $submission = PayrollSubmission::create([
                    'contractor_clab_no' => $clabNo,
                    'user_id' => $user->id,
                    'month' => $month,
                    'year' => $year,
                    'status' => 'submitted',
                    'submitted_by' => auth()->id(),
                    'submitted_at' => now(),
                    'payment_deadline' => $targetDate->copy()->endOfMonth(),
                ]);

                $totalAmount = 0;
                foreach ($activeContractWorkers as $contractWorker) {
                    $worker = $contractWorker->worker;
                    if (! $worker) {
                        continue;
                    }

                    $payrollWorker = new PayrollWorker(['worker_id' => $worker->wkr_id, 'worker_name' => $worker->wkr_name, 'worker_passport' => $worker->wkr_passno, 'basic_salary' => $worker->wkr_salary ?? 1700, 'ot_normal_hours' => 0, 'ot_rest_hours' => 0, 'ot_public_hours' => 0]);
                    $payrollWorker->payroll_submission_id = $submission->id;
                    $payrollWorker->calculateSalary(0);
                    $payrollWorker->save();
                    $totalAmount += $payrollWorker->total_payment;
                }

                $serviceCharge = $activeContractWorkers->count() * 200;
                $sst = $serviceCharge * 0.08;
                $grandTotal = $totalAmount + $serviceCharge + $sst;

                $submission->update(['total_workers' => $activeContractWorkers->count(), 'total_amount' => $totalAmount, 'service_charge' => $serviceCharge, 'sst' => $sst, 'grand_total' => $grandTotal, 'total_with_penalty' => $grandTotal]);
            });

            Flux::toast(variant: 'success', heading: 'Submission Created', text: 'Payroll for '.\Carbon\Carbon::create($year, $month, 1)->format('F Y')." submitted successfully on behalf of {$this->bulkSubmitContractor['name']}.");
            $this->closeBulkSubmitModal();
            $this->loadMissingContractors();
        } catch (\Exception $e) {
            Flux::toast(variant: 'danger', heading: 'Error', text: 'Failed to create draft submission: '.$e->getMessage());
            \Log::error('Bulk submission failed: '.$e->getMessage());
            $this->closeBulkSubmitModal();
        }
    }

    public function export()
    {
        // Check if there are missing contractors
        if ($this->missingContractors->isEmpty()) {
            Flux::toast(
                variant: 'warning',
                heading: 'No data to export',
                text: 'There are no missing submissions for the current period.'
            );

            return;
        }

        // Generate CSV content
        $csvContent = $this->generateCsv();

        // Generate filename with selected period
        $filename = sprintf(
            'missing_submissions_%d-%02d_%s.csv',
            $this->selectedYear,
            $this->selectedMonth,
            now()->format('Ymd_His')
        );

        // Return download response
        return Response::streamDownload(function () use ($csvContent) {
            echo $csvContent;
        }, $filename, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function exportDetailed()
    {
        // Check if there are historical summaries
        if (empty($this->historicalSummary)) {
            Flux::toast(
                variant: 'warning',
                heading: 'No data to export',
                text: 'There are no contractors with multiple missing periods to export.'
            );

            return;
        }

        // Generate detailed CSV content
        $csvContent = $this->generateDetailedCsv();

        // Generate filename
        $filename = sprintf(
            'detailed_missing_submissions_%s.csv',
            now()->format('Ymd_His')
        );

        // Return download response
        return Response::streamDownload(function () use ($csvContent) {
            echo $csvContent;
        }, $filename, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function exportCurrentPeriodDetailed()
    {
        // Check if there are missing contractors
        if ($this->missingContractors->isEmpty()) {
            Flux::toast(
                variant: 'warning',
                heading: 'No data to export',
                text: 'There are no missing submissions for the current period.'
            );

            return;
        }

        // Generate detailed CSV content for current period
        $csvContent = $this->generateCurrentPeriodDetailedCsv();

        // Generate filename with selected period
        $filename = sprintf(
            'detailed_submissions_%d-%02d_%s.csv',
            $this->selectedYear,
            $this->selectedMonth,
            now()->format('Ymd_His')
        );

        // Return download response
        return Response::streamDownload(function () use ($csvContent) {
            echo $csvContent;
        }, $filename, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    protected function generateCsv()
    {
        $periodLabel = \Carbon\Carbon::create($this->selectedYear, $this->selectedMonth, 1)->format('F Y');

        // CSV Header
        $csv = "Missing Payroll Submissions & Payments Report\n";
        $csv .= "Period: {$periodLabel}\n";
        $csv .= 'Generated: '.now()->format('d M Y, h:i A')."\n\n";

        // Column headers
        $csv .= "No,CLAB No,Contractor Name,Email,Phone,Workers With Issues,Not Submitted,Not Paid,Total Workers,Workers Completed,Reminders Sent,Status\n";

        // Data rows
        foreach ($this->missingContractors as $index => $contractor) {
            $csv .= ($index + 1).',';
            $csv .= '"'.$contractor['clab_no'].'",';
            $csv .= '"'.str_replace('"', '""', $contractor['name']).'",';
            $csv .= '"'.($contractor['email'] ?? 'N/A').'",';
            $csv .= '"'.($contractor['phone'] ?? 'N/A').'",';
            $csv .= $contractor['active_workers'].',';
            $csv .= $contractor['not_submitted'].',';
            $csv .= $contractor['submitted_not_paid'].',';
            $csv .= $contractor['total_workers'].',';
            $csv .= ($contractor['total_workers'] - $contractor['active_workers']).',';
            $csv .= $contractor['reminders_sent'].',';
            $csv .= $contractor['reminders_sent'] > 0 ? 'Reminded' : 'Not Reminded';
            $csv .= "\n";
        }

        // Summary
        $csv .= "\nSummary\n";
        $csv .= 'Total Contractors With Issues,'.$this->missingContractors->count()."\n";
        $csv .= 'Total Workers With Issues,'.$this->missingContractors->sum('active_workers')."\n";
        $csv .= 'Total Workers Not Submitted,'.$this->missingContractors->sum('not_submitted')."\n";
        $csv .= 'Total Workers Not Paid,'.$this->missingContractors->sum('submitted_not_paid')."\n";
        $csv .= 'Total Workers Completed,'.$this->missingContractors->sum(function ($c) {
            return $c['total_workers'] - $c['active_workers'];
        })."\n";

        return $csv;
    }

    protected function generateCurrentPeriodDetailedCsv()
    {
        $periodLabel = \Carbon\Carbon::create($this->selectedYear, $this->selectedMonth, 1)->format('F Y');

        // CSV Header
        $csv = "Detailed Submissions & Payments Report\n";
        $csv .= "Period: {$periodLabel}\n";
        $csv .= 'Generated: '.now()->format('d M Y, h:i A')."\n\n";

        // Single table header
        $csv .= "No,CLAB No,Contractor Name,Contractor Email,Contractor Phone,Worker ID,Worker Name,Passport No,Status,Issue Type\n";

        $rowNumber = 1;

        // Process each contractor
        foreach ($this->missingContractors as $contractor) {
            // Get worker details for this contractor
            $workerDetails = $this->getWorkerDetailsForPeriod(
                $contractor['clab_no'],
                $this->selectedMonth,
                $this->selectedYear
            );

            if ($workerDetails->isNotEmpty()) {
                foreach ($workerDetails as $worker) {
                    $csv .= $rowNumber.',';
                    $csv .= '"'.$contractor['clab_no'].'",';
                    $csv .= '"'.str_replace('"', '""', $contractor['name']).'",';
                    $csv .= '"'.($contractor['email'] ?? 'N/A').'",';
                    $csv .= '"'.($contractor['phone'] ?? 'N/A').'",';
                    $csv .= '"'.$worker['worker_id'].'",';
                    $csv .= '"'.str_replace('"', '""', $worker['name']).'",';
                    $csv .= '"'.($worker['passport'] ?? 'N/A').'",';
                    $csv .= '"'.$worker['status'].'",';
                    $csv .= '"'.$worker['issue_type'].'"';
                    $csv .= "\n";
                    $rowNumber++;
                }
            }
        }

        // Overall Summary
        $csv .= "\n";
        $csv .= "SUMMARY\n";
        $csv .= "Period,{$periodLabel}\n";
        $csv .= 'Total Contractors With Issues,'.$this->missingContractors->count()."\n";
        $csv .= 'Total Workers With Issues,'.$this->missingContractors->sum('active_workers')."\n";
        $csv .= 'Total Workers Not Submitted,'.$this->missingContractors->sum('not_submitted')."\n";
        $csv .= 'Total Workers Not Paid,'.$this->missingContractors->sum('submitted_not_paid')."\n";

        return $csv;
    }

    protected function generateDetailedCsv()
    {
        $endDate = \Carbon\Carbon::create($this->selectedYear, $this->selectedMonth, 1);
        $startDate = $endDate->copy()->subMonths(5);

        // CSV Header
        $csv = "Detailed Missing Submissions & Payments Report (Historical)\n";
        $csv .= "Period Range: {$startDate->format('F Y')} - {$endDate->format('F Y')}\n";
        $csv .= 'Generated: '.now()->format('d M Y, h:i A')."\n\n";

        // Single table header
        $csv .= "No,Period,CLAB No,Contractor Name,Contractor Email,Missing Months Count,Worker ID,Worker Name,Passport No,Status,Issue Type\n";

        $rowNumber = 1;

        // Process each contractor in historical summary
        foreach ($this->historicalSummary as $contractor) {
            // Process each missing month for this contractor
            foreach ($contractor['missing_months'] as $period) {
                // Get worker details for this period
                $workerDetails = $this->getWorkerDetailsForPeriod(
                    $contractor['clab_no'],
                    $period['month'],
                    $period['year']
                );

                if ($workerDetails->isNotEmpty()) {
                    foreach ($workerDetails as $worker) {
                        $csv .= $rowNumber.',';
                        $csv .= '"'.$period['label'].'",';
                        $csv .= '"'.$contractor['clab_no'].'",';
                        $csv .= '"'.str_replace('"', '""', $contractor['name']).'",';
                        $csv .= '"'.($contractor['email'] ?? 'N/A').'",';
                        $csv .= $contractor['missing_count'].',';
                        $csv .= '"'.$worker['worker_id'].'",';
                        $csv .= '"'.str_replace('"', '""', $worker['name']).'",';
                        $csv .= '"'.($worker['passport'] ?? 'N/A').'",';
                        $csv .= '"'.$worker['status'].'",';
                        $csv .= '"'.$worker['issue_type'].'"';
                        $csv .= "\n";
                        $rowNumber++;
                    }
                }
            }
        }

        // Overall Summary
        $csv .= "\n";
        $csv .= "SUMMARY\n";
        $csv .= "Period Range,{$startDate->format('F Y')} - {$endDate->format('F Y')}\n";
        $csv .= 'Total Contractors with Repeat Issues,'.count($this->historicalSummary)."\n";
        $csv .= 'Total Records,'.($rowNumber - 1)."\n";

        return $csv;
    }

    protected function getWorkerDetailsForPeriod($clabNo, $month, $year)
    {
        // Get workers with active contracts during this specific period
        $periodStart = \Carbon\Carbon::create($year, $month, 1)->startOfMonth();
        $periodEnd = $periodStart->copy()->endOfMonth();

        $activeWorkers = ContractWorker::where('con_ctr_clab_no', $clabNo)
            ->where('con_start', '<=', $periodEnd->toDateString())
            ->where('con_end', '>=', $periodStart->toDateString())
            ->with('worker') // Eager load worker relationship
            ->get();

        $result = collect();

        foreach ($activeWorkers as $contractWorker) {
            // Check if worker was submitted and paid for this period
            $payrollWorker = PayrollWorker::whereHas('payrollSubmission', function ($query) use ($month, $year) {
                $query->where('month', $month)
                    ->where('year', $year);
            })
                ->where('worker_id', $contractWorker->con_wkr_id)
                ->with('payrollSubmission')
                ->first();

            // Get worker details from relationship
            $workerName = $contractWorker->worker ? $contractWorker->worker->wkr_name : 'Unknown';
            $workerPassport = $contractWorker->worker ? $contractWorker->worker->wkr_passno : ($contractWorker->con_wkr_passno ?? 'N/A');

            if (! $payrollWorker) {
                // Not submitted at all
                $result->push([
                    'worker_id' => $contractWorker->con_wkr_id,
                    'name' => $workerName,
                    'passport' => $workerPassport,
                    'status' => 'Incomplete',
                    'issue_type' => 'Not Submitted',
                ]);
            } elseif ($payrollWorker->payrollSubmission->status !== 'paid') {
                // Submitted but not paid
                $result->push([
                    'worker_id' => $contractWorker->con_wkr_id,
                    'name' => $workerName,
                    'passport' => $workerPassport,
                    'status' => 'Submitted - Payment Pending',
                    'issue_type' => 'Not Paid',
                ]);
            }
        }

        return $result;
    }

    public function sendReminder()
    {
        // Validate
        if (! $this->selectedContractor || empty($this->reminderMessage)) {
            Flux::toast(variant: 'danger', text: 'Cannot send reminder without a message.');

            return;
        }

        // Validate email exists
        if (empty($this->selectedContractor['email'])) {
            Flux::toast(variant: 'danger', text: 'Cannot send reminder: No email address found for this contractor.');

            return;
        }

        // Extract first email if multiple emails are present (separated by comma or semicolon)
        $emailAddress = trim(preg_split('/[,;]/', $this->selectedContractor['email'])[0]);

        try {
            // Send email
            $periodLabel = \Carbon\Carbon::create($this->selectedYear, $this->selectedMonth, 1)->format('F Y');
            Mail::to($emailAddress)->send(
                new PayrollReminderMail(
                    $this->selectedContractor['name'],
                    $this->selectedContractor['clab_no'],
                    $this->selectedContractor['active_workers'],
                    $this->selectedContractor['total_workers'],
                    $periodLabel,
                    $this->reminderMessage
                )
            );

            // Save reminder record to database
            PayrollReminder::create([
                'contractor_clab_no' => $this->selectedContractor['clab_no'],
                'contractor_name' => $this->selectedContractor['name'],
                'contractor_email' => $emailAddress,
                'month' => $this->selectedMonth,
                'year' => $this->selectedYear,
                'message' => $this->reminderMessage,
                'sent_by' => auth()->user()->name ?? 'System',
            ]);

            Flux::toast(
                variant: 'success',
                heading: 'Reminder sent!',
                text: "Email sent to {$this->selectedContractor['name']} ({$emailAddress})"
            );
        } catch (\Exception $e) {
            Flux::toast(variant: 'danger', heading: 'Failed to send', text: $e->getMessage());
        }

        $this->closeRemindModal();
    }

    protected function generateAvailablePeriodsFromSubmissions()
    {
        // Get the earliest submission date or go back 12 months from now
        $earliestSubmission = PayrollSubmission::orderBy('created_at', 'asc')->first();
        $startDate = $earliestSubmission
            ? $earliestSubmission->created_at
            : now()->subMonths(12);

        // Generate years from earliest to current
        $currentYear = now()->year;
        $startYear = $startDate->year;

        $this->availableYears = collect(range($startYear, $currentYear))->reverse()->values()->toArray();

        // Months are always 1-12
        $this->availableMonths = [
            1 => 'January',
            2 => 'February',
            3 => 'March',
            4 => 'April',
            5 => 'May',
            6 => 'June',
            7 => 'July',
            8 => 'August',
            9 => 'September',
            10 => 'October',
            11 => 'November',
            12 => 'December',
        ];
    }

    public function updatedSelectedMonth()
    {
        $this->currentPage = 1;
        $this->historicalPage = 1;
        $this->loadMissingContractors();
        $this->loadHistoricalSummary();
    }

    public function updatedSelectedYear()
    {
        $this->currentPage = 1;
        $this->historicalPage = 1;
        $this->loadMissingContractors();
        $this->loadHistoricalSummary();
    }

    protected function loadMissingContractors()
    {
        $currentMonth = $this->selectedMonth;
        $currentYear = $this->selectedYear;

        // Get period boundaries
        $periodStart = \Carbon\Carbon::create($currentYear, $currentMonth, 1)->startOfMonth();
        $periodEnd = $periodStart->copy()->endOfMonth();

        // Get all contractors with workers who had active contracts during this period
        $contractorsWithActiveWorkers = ContractWorker::where('con_start', '<=', $periodEnd->toDateString())
            ->where('con_end', '>=', $periodStart->toDateString())
            ->distinct()
            ->pluck('con_ctr_clab_no')
            ->unique();

        // Count total active workers per contractor for this period
        $totalActiveWorkers = ContractWorker::where('con_start', '<=', $periodEnd->toDateString())
            ->where('con_end', '>=', $periodStart->toDateString())
            ->select('con_ctr_clab_no', \DB::raw('COUNT(*) as count'))
            ->groupBy('con_ctr_clab_no')
            ->pluck('count', 'con_ctr_clab_no');

        // Get submitted AND paid worker IDs for current month
        $submittedAndPaidWorkerIds = PayrollWorker::whereHas('payrollSubmission', function ($query) use ($currentMonth, $currentYear) {
            $query->where('month', $currentMonth)
                ->where('year', $currentYear)
                ->where('status', 'paid'); // Only count as complete if paid
        })
            ->pluck('worker_id')
            ->unique();

        // Get submitted but NOT paid worker IDs for current month
        $submittedButUnpaidWorkerIds = PayrollWorker::whereHas('payrollSubmission', function ($query) use ($currentMonth, $currentYear) {
            $query->where('month', $currentMonth)
                ->where('year', $currentYear)
                ->where('status', '!=', 'paid'); // Submitted but not paid
        })
            ->pluck('worker_id')
            ->unique();

        // Count workers by issue type per contractor
        $contractors = ContractWorker::where('con_start', '<=', $periodEnd->toDateString())
            ->where('con_end', '>=', $periodStart->toDateString())
            ->select('con_ctr_clab_no')
            ->groupBy('con_ctr_clab_no')
            ->get();

        $contractorIssues = collect();

        foreach ($contractors as $contractor) {
            $clabNo = $contractor->con_ctr_clab_no;

            // Get all worker IDs with active contracts during this period
            $activeWorkerIds = ContractWorker::where('con_ctr_clab_no', $clabNo)
                ->where('con_start', '<=', $periodEnd->toDateString())
                ->where('con_end', '>=', $periodStart->toDateString())
                ->pluck('con_wkr_id');

            if ($activeWorkerIds->isEmpty()) {
                continue;
            }

            // Count workers not submitted at all
            $notSubmitted = $activeWorkerIds->diff($submittedAndPaidWorkerIds)
                ->diff($submittedButUnpaidWorkerIds)
                ->count();

            // Count workers submitted but not paid
            $submittedNotPaid = $activeWorkerIds->intersect($submittedButUnpaidWorkerIds)->count();

            // Total workers with issues
            $totalIssues = $notSubmitted + $submittedNotPaid;

            if ($totalIssues > 0) {
                $contractorIssues->put($clabNo, [
                    'total_issues' => $totalIssues,
                    'not_submitted' => $notSubmitted,
                    'submitted_not_paid' => $submittedNotPaid,
                ]);
            }
        }

        if ($contractorIssues->isEmpty()) {
            $this->missingContractors = collect();

            return;
        }

        // Batch load all users at once
        $contractorCLABs = $contractorIssues->keys();
        $users = User::whereIn('contractor_clab_no', $contractorCLABs)
            ->where('role', 'client')
            ->get()
            ->keyBy('contractor_clab_no');

        // Batch load all contractors at once
        $contractors = Contractor::whereIn('ctr_clab_no', $contractorCLABs)
            ->get()
            ->keyBy('ctr_clab_no');

        // Get reminder counts for current month/year
        $reminderCounts = PayrollReminder::whereIn('contractor_clab_no', $contractorCLABs)
            ->where('month', $currentMonth)
            ->where('year', $currentYear)
            ->select('contractor_clab_no', \DB::raw('COUNT(*) as count'))
            ->groupBy('contractor_clab_no')
            ->pluck('count', 'contractor_clab_no');

        // Build result set
        $result = collect();
        foreach ($contractorIssues as $clabNo => $issues) {
            $totalCount = $totalActiveWorkers->get($clabNo, 0);
            $user = $users->get($clabNo);
            $contractor = $contractors->get($clabNo);

            $result->push([
                'clab_no' => $clabNo,
                'name' => $user
                    ? ($user->company_name ?? $user->name)
                    : ($contractor ? $contractor->ctr_comp_name : 'Contractor '.$clabNo),
                'email' => $user
                    ? $user->email
                    : ($contractor ? $contractor->ctr_email : null),
                'phone' => $user
                    ? $user->phone
                    : ($contractor ? ($contractor->ctr_contact_mobileno ?? $contractor->ctr_telno) : null),
                'active_workers' => $issues['total_issues'],
                'total_workers' => $totalCount,
                'not_submitted' => $issues['not_submitted'],
                'submitted_not_paid' => $issues['submitted_not_paid'],
                'reminders_sent' => $reminderCounts->get($clabNo, 0),
            ]);
        }

        $this->missingContractors = $result->sortBy('name');
    }

    protected function loadHistoricalSummary()
    {
        // Look back 6 months from selected month/year, but exclude current month
        $endDate = \Carbon\Carbon::create($this->selectedYear, $this->selectedMonth, 1);
        $startDate = $endDate->copy()->subMonths(5); // Last 6 months including selected

        // Get current month/year to exclude it
        $currentMonth = now()->month;
        $currentYear = now()->year;

        // Get all contractors who had active contracts during the 6-month period
        $periodStart = $startDate->copy()->startOfMonth();
        $periodEnd = $endDate->copy()->endOfMonth();

        $allContractors = ContractWorker::where('con_start', '<=', $periodEnd->toDateString())
            ->where('con_end', '>=', $periodStart->toDateString())
            ->distinct()
            ->pluck('con_ctr_clab_no')
            ->unique();

        if ($allContractors->isEmpty()) {
            $this->historicalSummary = [];

            return;
        }

        // Batch load all users and contractors
        $users = User::whereIn('contractor_clab_no', $allContractors)
            ->where('role', 'client')
            ->get()
            ->keyBy('contractor_clab_no');

        $contractors = Contractor::whereIn('ctr_clab_no', $allContractors)
            ->get()
            ->keyBy('ctr_clab_no');

        // Track missing submissions by contractor across the last 6 months
        $result = collect();

        foreach ($allContractors as $clabNo) {
            $missingMonths = [];
            $currentPeriod = $startDate->copy();

            for ($i = 0; $i < 6; $i++) {
                $month = $currentPeriod->month;
                $year = $currentPeriod->year;

                // Skip the current month (ongoing period)
                if ($month === $currentMonth && $year === $currentYear) {
                    $currentPeriod->addMonth();

                    continue;
                }

                // Get workers with active contracts during this specific period
                $periodStart = \Carbon\Carbon::create($year, $month, 1)->startOfMonth();
                $periodEnd = $periodStart->copy()->endOfMonth();

                $activeWorkerIds = ContractWorker::where('con_ctr_clab_no', $clabNo)
                    ->where('con_start', '<=', $periodEnd->toDateString())
                    ->where('con_end', '>=', $periodStart->toDateString())
                    ->pluck('con_wkr_id');

                if ($activeWorkerIds->isNotEmpty()) {
                    // Check workers that were submitted AND paid
                    $submittedAndPaidWorkerIds = PayrollWorker::whereHas('payrollSubmission', function ($query) use ($month, $year) {
                        $query->where('month', $month)
                            ->where('year', $year)
                            ->where('status', 'paid'); // Only count if paid
                    })
                        ->whereIn('worker_id', $activeWorkerIds)
                        ->pluck('worker_id')
                        ->unique();

                    // If not all workers were submitted and paid, mark as having issues
                    if ($submittedAndPaidWorkerIds->count() < $activeWorkerIds->count()) {
                        // Count workers submitted but not paid
                        $submittedButUnpaidWorkerIds = PayrollWorker::whereHas('payrollSubmission', function ($query) use ($month, $year) {
                            $query->where('month', $month)
                                ->where('year', $year)
                                ->where('status', '!=', 'paid');
                        })
                            ->whereIn('worker_id', $activeWorkerIds)
                            ->pluck('worker_id')
                            ->unique();

                        $notSubmittedCount = $activeWorkerIds->diff($submittedAndPaidWorkerIds)
                            ->diff($submittedButUnpaidWorkerIds)
                            ->count();
                        $notPaidCount = $submittedButUnpaidWorkerIds->count();

                        $missingMonths[] = [
                            'month' => $month,
                            'year' => $year,
                            'label' => $currentPeriod->format('M Y'),
                            'missing_count' => $activeWorkerIds->count() - $submittedAndPaidWorkerIds->count(),
                            'total_count' => $activeWorkerIds->count(),
                            'not_submitted' => $notSubmittedCount,
                            'not_paid' => $notPaidCount,
                        ];
                    }
                }

                $currentPeriod->addMonth();
            }

            // Only include contractors with at least 2 missing months
            if (count($missingMonths) >= 2) {
                $user = $users->get($clabNo);
                $contractor = $contractors->get($clabNo);

                $result->push([
                    'clab_no' => $clabNo,
                    'name' => $user
                        ? ($user->company_name ?? $user->name)
                        : ($contractor ? $contractor->ctr_comp_name : 'Contractor '.$clabNo),
                    'email' => $user
                        ? $user->email
                        : ($contractor ? $contractor->ctr_email : null),
                    'missing_months' => $missingMonths,
                    'missing_count' => count($missingMonths),
                ]);
            }
        }

        $allResults = $result->sortByDesc('missing_count')->values();
        $this->historicalSummary = $allResults->toArray();
    }

    public function getHistoricalPaginatedProperty()
    {
        $start = ($this->historicalPage - 1) * $this->historicalPerPage;

        return collect($this->historicalSummary)->slice($start, $this->historicalPerPage)->values();
    }

    public function getHistoricalPaginationProperty()
    {
        $total = count($this->historicalSummary);
        $lastPage = ceil($total / $this->historicalPerPage);
        $from = (($this->historicalPage - 1) * $this->historicalPerPage) + 1;
        $to = min($this->historicalPage * $this->historicalPerPage, $total);

        return [
            'current_page' => $this->historicalPage,
            'per_page' => $this->historicalPerPage,
            'total' => $total,
            'last_page' => $lastPage,
            'from' => $from,
            'to' => $to,
        ];
    }

    public function getMissingPaginatedProperty()
    {
        $start = ($this->currentPage - 1) * $this->currentPerPage;

        return $this->missingContractors->slice($start, $this->currentPerPage)->values();
    }

    public function getMissingPaginationProperty()
    {
        $total = $this->missingContractors->count();
        $lastPage = ceil($total / $this->currentPerPage);
        $from = (($this->currentPage - 1) * $this->currentPerPage) + 1;
        $to = min($this->currentPage * $this->currentPerPage, $total);

        return [
            'current_page' => $this->currentPage,
            'per_page' => $this->currentPerPage,
            'total' => $total,
            'last_page' => $lastPage,
            'from' => $from,
            'to' => $to,
        ];
    }

    public function render()
    {
        return view('livewire.admin.missing-submissions');
    }
}
