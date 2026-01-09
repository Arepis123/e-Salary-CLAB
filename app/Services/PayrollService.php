<?php

namespace App\Services;

use App\Models\PayrollSubmission;
use App\Models\PayrollWorker;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class PayrollService
{
    /**
     * Get current month's latest draft payroll submission for a contractor
     * Returns the latest draft submission or null (does NOT create)
     * Note: This method is deprecated - use direct queries instead
     */
    public function getCurrentMonthSubmission(string $clabNo): ?PayrollSubmission
    {
        $now = now();
        $month = $now->month;
        $year = $now->year;

        // Find the latest draft submission for this month
        return PayrollSubmission::where('contractor_clab_no', $clabNo)
            ->where('month', $month)
            ->where('year', $year)
            ->where('status', 'draft')
            ->latest('created_at')
            ->first();
    }

    /**
     * Get payroll submission for specific month/year
     */
    public function getSubmissionForMonth(string $clabNo, int $month, int $year): ?PayrollSubmission
    {
        return PayrollSubmission::byContractor($clabNo)
            ->forMonth($month, $year)
            ->first();
    }

    /**
     * Save payroll as DRAFT (not submitted for payment yet)
     * Draft submissions can be edited later before final submission
     *
     * NEW SYSTEM: Contractor enters PREVIOUS month's OT hours in current month's payroll
     * Example: In November payroll, contractor enters October's OT hours
     * The OT is calculated and paid in the same month (November)
     */
    public function savePayrollDraft(string $clabNo, array $workersData, ?int $month = null, ?int $year = null): PayrollSubmission
    {
        // Create a NEW draft submission
        // Use provided month/year or default to current month
        $month = $month ?? now()->month;
        $year = $year ?? now()->year;
        // Deadline is the last day of the payroll month
        $deadline = Carbon::create($year, $month, 1)->endOfMonth();

        $submission = PayrollSubmission::create([
            'contractor_clab_no' => $clabNo,
            'month' => $month,
            'year' => $year,
            'payment_deadline' => $deadline,
            'status' => 'draft',
            'submitted_at' => null,  // Not submitted yet
        ]);

        $totalAmount = 0;

        // Create payroll workers and calculate totals
        foreach ($workersData as $workerData) {
            $payrollWorker = new PayrollWorker($workerData);
            $payrollWorker->payroll_submission_id = $submission->id;

            // Save worker first (without final calculations)
            $payrollWorker->save();

            // Save transactions BEFORE calculating salary
            if (isset($workerData['transactions']) && is_array($workerData['transactions'])) {
                foreach ($workerData['transactions'] as $transaction) {
                    $payrollWorker->transactions()->create([
                        'type' => $transaction['type'],
                        'amount' => $transaction['amount'],
                        'remarks' => $transaction['remarks'],
                    ]);
                }
            }

            // Calculate salary with OT included THIS MONTH (no deferral)
            // The OT hours entered are for PREVIOUS month and paid THIS month
            // Only calculate if auto-calculation is enabled (disabled in admin review workflow)
            if (config('payroll.use_auto_calculations', false)) {
                $payrollWorker->calculateSalary(0); // No previous month OT to add, it's already in the hours
                $payrollWorker->save();
            } else {
                // Just save inputs, no calculations (admin will enter final amount)
                $payrollWorker->save();
            }

            // Total amount is what the system collects (Gross + Employer contributions)
            $totalAmount += $payrollWorker->total_payment;
        }

        // Apply all configured deductions for this month
        $this->applyConfiguredDeductions($submission);

        // Calculate service charge, SST, and grand total
        // Only charge service fee for active workers (exclude workers with ended contracts)
        $activeWorkersCount = count(array_filter($workersData, function ($worker) {
            return ! ($worker['contract_ended'] ?? false);
        }));
        $serviceCharge = $activeWorkersCount * 200; // RM200 per active worker only
        $sst = $serviceCharge * 0.08; // 8% SST on service charge

        // Update submission totals
        $submission->update([
            'total_workers' => count($workersData),
            'admin_final_amount' => $totalAmount, // Set payroll amount
            'service_charge' => $serviceCharge,
            'sst' => $sst,
            // Note: total_amount, grand_total, total_with_penalty are deprecated
        ]);

        return $submission->fresh(['workers']);
    }

    /**
     * Create or update payroll submission with workers data
     *
     * Payment Calculation (based on FORMULA PENGIRAAN GAJI DAN OVERTIME.csv):
     * - System collects: Basic Salary + Employer Contributions (EPF + SOCSO) + OT
     * - Worker receives: Basic Salary - Worker Deductions (EPF + SOCSO) + OT
     *
     * NEW SYSTEM: Contractor enters PREVIOUS month's OT hours in current month's payroll
     * Example: In November payroll, contractor enters October's OT hours
     * The OT is calculated and paid in the same month (November)
     */
    public function savePayrollSubmission(string $clabNo, array $workersData, ?int $month = null, ?int $year = null): PayrollSubmission
    {
        // Create a NEW submission for this batch of workers
        // Use provided month/year or default to current month
        $month = $month ?? now()->month;
        $year = $year ?? now()->year;
        // Deadline is the last day of the payroll month
        $deadline = Carbon::create($year, $month, 1)->endOfMonth();

        $submission = PayrollSubmission::create([
            'contractor_clab_no' => $clabNo,
            'month' => $month,
            'year' => $year,
            'payment_deadline' => $deadline,
            'status' => 'submitted', // Changed from 'pending_payment' for admin review workflow
            'submitted_at' => now(),
        ]);

        $totalAmount = 0;

        // Create payroll workers and calculate totals
        foreach ($workersData as $workerData) {
            $payrollWorker = new PayrollWorker($workerData);
            $payrollWorker->payroll_submission_id = $submission->id;

            // Save worker first (without final calculations)
            $payrollWorker->save();

            // Save transactions BEFORE calculating salary
            if (isset($workerData['transactions']) && is_array($workerData['transactions'])) {
                foreach ($workerData['transactions'] as $transaction) {
                    $payrollWorker->transactions()->create([
                        'type' => $transaction['type'],
                        'amount' => $transaction['amount'],
                        'remarks' => $transaction['remarks'],
                    ]);
                }
            }

            // Calculate salary with OT included THIS MONTH (no deferral)
            // The OT hours entered are for PREVIOUS month and paid THIS month
            // Only calculate if auto-calculation is enabled (disabled in admin review workflow)
            if (config('payroll.use_auto_calculations', false)) {
                $payrollWorker->calculateSalary(0); // No previous month OT to add, it's already in the hours
                $payrollWorker->save();
            } else {
                // Just save inputs, no calculations (admin will enter final amount)
                $payrollWorker->save();
            }

            // Total amount is what the system collects (Gross + Employer contributions)
            $totalAmount += $payrollWorker->total_payment;
        }

        // Apply all configured deductions for this month
        $this->applyConfiguredDeductions($submission);

        // Calculate service charge, SST, and grand total
        // Only charge service fee for active workers (exclude workers with ended contracts)
        $activeWorkersCount = count(array_filter($workersData, function ($worker) {
            return ! ($worker['contract_ended'] ?? false);
        }));
        $serviceCharge = $activeWorkersCount * 200; // RM200 per active worker only
        $sst = $serviceCharge * 0.08; // 8% SST on service charge

        // Update submission totals
        $submission->update([
            'total_workers' => count($workersData),
            'admin_final_amount' => $totalAmount, // Set payroll amount
            'service_charge' => $serviceCharge,
            'sst' => $sst,
            // Note: total_amount, grand_total, total_with_penalty are deprecated
        ]);

        return $submission->fresh(['workers']);
    }

    /**
     * Get all submissions for a contractor
     */
    public function getContractorSubmissions(string $clabNo): Collection
    {
        return PayrollSubmission::byContractor($clabNo)
            ->with(['workers', 'payment'])
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->get();
    }

    /**
     * Check and update penalties for overdue submissions
     */
    public function updateOverduePenalties(string $clabNo): void
    {
        $overdueSubmissions = PayrollSubmission::byContractor($clabNo)
            ->overdue()
            ->get();

        foreach ($overdueSubmissions as $submission) {
            $submission->updatePenalty();
        }
    }

    /**
     * Get payroll statistics for contractor
     */
    public function getContractorStatistics(string $clabNo, ?int $unsubmittedWorkersCount = null): array
    {
        $submissions = PayrollSubmission::byContractor($clabNo)->get();

        return [
            'total_submissions' => $submissions->count(),
            'paid_submissions' => $submissions->where('status', 'paid')->count(),
            'pending_submissions' => $submissions->whereIn('status', ['submitted', 'approved'])->count(),
            'overdue_submissions' => $submissions->where('status', 'overdue')->count(),
            'total_paid_amount' => $submissions->where('status', 'paid')->sum('total_with_penalty'),
            'total_pending_amount' => $submissions->whereIn('status', ['submitted', 'approved', 'overdue'])->sum('total_with_penalty'),
            'unsubmitted_workers' => $unsubmittedWorkersCount ?? 0,
        ];
    }

    /**
     * Calculate what the current month's payroll would be based on contracted workers
     */
    public function calculateEstimatedPayroll(string $clabNo, ContractWorkerService $contractWorkerService): array
    {
        $workers = $contractWorkerService->getContractedWorkers($clabNo);
        $calculator = app(PaymentCalculatorService::class);

        $estimatedTotal = 0;
        $workerEstimates = [];

        foreach ($workers as $worker) {
            $basicSalary = $worker->basic_salary ?? 1700;

            // Calculate total payment to CLAB (Basic + Employer contributions)
            $totalPayment = $calculator->calculateTotalPaymentToCLAB($basicSalary);

            $workerEstimates[] = [
                'worker_id' => $worker->wkr_id,
                'worker_name' => $worker->name,
                'basic_salary' => $basicSalary,
                'estimated_payment' => $totalPayment,
            ];

            $estimatedTotal += $totalPayment;
        }

        return [
            'total_workers' => $workers->count(),
            'estimated_total' => $estimatedTotal,
            'workers' => $workerEstimates,
        ];
    }

    /**
     * Get current month and year for payroll
     */
    public function getCurrentPayrollPeriod(): array
    {
        $now = now();
        $deadline = Carbon::create($now->year, $now->month, 1)->endOfMonth();

        return [
            'month' => $now->month,
            'year' => $now->year,
            'month_name' => $now->format('F'),
            'deadline' => $deadline,
            'days_until_deadline' => (int) now()->diffInDays($deadline, false),
        ];
    }

    /**
     * Apply all enabled deductions for this contractor and month
     * Applies both contractor-level (all workers) and worker-level (specific workers) deductions
     */
    protected function applyConfiguredDeductions(PayrollSubmission $submission): void
    {
        $configService = app(\App\Services\ContractorConfigurationService::class);
        $workerDeductionService = app(\App\Services\WorkerDeductionService::class);

        // Get all deductions for this month
        $deductions = $configService->getDeductionsForMonth($submission->contractor_clab_no, $submission->month);
        $contractorDeductions = $deductions->filter(fn ($d) => $d->isContractorLevel());

        // Process each worker
        foreach ($submission->workers as $worker) {
            // Calculate current payroll period for this worker
            $currentPeriod = $workerDeductionService->getWorkerPayrollPeriodCount(
                $worker->worker_id,
                $submission->contractor_clab_no
            );

            // 1. Apply CONTRACTOR-LEVEL deductions (check period for each worker)
            foreach ($contractorDeductions as $deduction) {
                // Check if deduction applies to this worker's current period
                if ($deduction->shouldApplyInPeriod($currentPeriod)) {
                    $this->createDeductionTransaction(
                        $worker,
                        $deduction,
                        "Contractor-level deduction (Period: {$currentPeriod})"
                    );
                }
            }

            // 2. Apply WORKER-LEVEL deductions for this specific worker
            $workerDeductions = $workerDeductionService->getApplicableDeductionsForWorker(
                $worker->worker_id,
                $submission->contractor_clab_no,
                $submission->month,
                $currentPeriod
            );

            foreach ($workerDeductions as $deduction) {
                $this->createDeductionTransaction(
                    $worker,
                    $deduction,
                    "Worker-level deduction (Period: {$currentPeriod})"
                );
            }
        }
    }

    /**
     * Helper method to create deduction transaction (avoids duplicates)
     */
    protected function createDeductionTransaction(
        $worker,
        $deduction,
        string $context
    ): void {
        // Check if this deduction already exists for this worker
        $existingDeduction = $worker->transactions()
            ->where('type', 'deduction')
            ->where('description', $deduction->name)
            ->first();

        // Only create if doesn't exist yet
        if (! $existingDeduction) {
            $worker->transactions()->create([
                'type' => 'deduction',
                'amount' => $deduction->amount,
                'description' => $deduction->name,
                'remarks' => "Auto-applied: {$context}",
            ]);
        }
    }
}
