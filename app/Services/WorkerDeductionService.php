<?php

namespace App\Services;

use App\Models\ContractWorker;
use App\Models\PayrollWorker;
use App\Models\Worker;
use App\Models\WorkerDeduction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class WorkerDeductionService
{
    /**
     * Calculate current payroll period count for a worker under a contractor
     * Counts submitted/approved/paid payroll submissions where worker appeared
     */
    public function getWorkerPayrollPeriodCount(string $workerId, string $clabNo): int
    {
        return PayrollWorker::whereHas('payrollSubmission', function ($query) use ($clabNo) {
            $query->where('contractor_clab_no', $clabNo)
                ->whereIn('status', ['submitted', 'approved', 'paid']);
        })
            ->where('worker_id', $workerId)
            ->count();
    }

    /**
     * Calculate payroll period counts for multiple workers
     * Returns array: ['worker_id' => period_count]
     */
    public function getWorkersPayrollPeriodCounts(array $workerIds, string $clabNo): array
    {
        $counts = [];

        // Optimize with single query
        $results = PayrollWorker::select('worker_id', DB::raw('COUNT(*) as period_count'))
            ->whereHas('payrollSubmission', function ($query) use ($clabNo) {
                $query->where('contractor_clab_no', $clabNo)
                    ->whereIn('status', ['submitted', 'approved', 'paid']);
            })
            ->whereIn('worker_id', $workerIds)
            ->groupBy('worker_id')
            ->get();

        foreach ($results as $result) {
            $counts[$result->worker_id] = $result->period_count;
        }

        // Fill in zeros for workers with no submissions
        foreach ($workerIds as $workerId) {
            if (! isset($counts[$workerId])) {
                $counts[$workerId] = 0;
            }
        }

        return $counts;
    }

    /**
     * Filter workers by contractor and target payroll periods
     * Returns collection of workers with their current period counts
     */
    public function filterWorkersByPeriods(string $clabNo, array $targetPeriods = []): Collection
    {
        // Get all workers under this contractor
        $contractWorkers = ContractWorker::byContractor($clabNo)
            ->with('worker')
            ->get();

        $workerIds = $contractWorkers->pluck('con_wkr_id')->toArray();

        // Get period counts for all workers
        $periodCounts = $this->getWorkersPayrollPeriodCounts($workerIds, $clabNo);

        // Build result collection
        $results = collect();
        foreach ($contractWorkers as $contract) {
            $worker = $contract->worker;
            if (! $worker) {
                continue;
            }

            $currentPeriod = $periodCounts[$worker->wkr_id] ?? 0;

            // Apply period filter if specified
            if (! empty($targetPeriods) && ! in_array($currentPeriod, $targetPeriods)) {
                continue;
            }

            $results->push([
                'worker_id' => $worker->wkr_id,
                'worker_name' => $worker->wkr_name,
                'worker_passport' => $worker->wkr_passno,
                'current_period' => $currentPeriod,
                'basic_salary' => $worker->wkr_salary,
                'position' => $worker->position ?? '-',
                'contract_start' => $contract->con_start,
                'contract_end' => $contract->con_end,
            ]);
        }

        return $results->sortBy('worker_name');
    }

    /**
     * Assign deduction template to specific workers
     */
    public function assignDeductionToWorkers(
        int $templateId,
        array $workerIds,
        string $clabNo,
        ?string $notes = null
    ): int {
        $assignedCount = 0;

        foreach ($workerIds as $workerId) {
            // Check if already assigned
            $existing = WorkerDeduction::byTemplate($templateId)
                ->byWorker($workerId)
                ->byContractor($clabNo)
                ->first();

            if (! $existing) {
                WorkerDeduction::create([
                    'deduction_template_id' => $templateId,
                    'worker_id' => $workerId,
                    'contractor_clab_no' => $clabNo,
                    'assigned_by' => auth()->id(),
                    'assigned_at' => now(),
                    'assignment_notes' => $notes,
                ]);
                $assignedCount++;
            }
        }

        return $assignedCount;
    }

    /**
     * Remove deduction assignment from workers
     */
    public function removeDeductionFromWorkers(
        int $templateId,
        array $workerIds,
        string $clabNo
    ): int {
        return WorkerDeduction::byTemplate($templateId)
            ->byContractor($clabNo)
            ->whereIn('worker_id', $workerIds)
            ->delete();
    }

    /**
     * Get all workers assigned to a template under a contractor
     */
    public function getAssignedWorkers(int $templateId, string $clabNo): Collection
    {
        return WorkerDeduction::byTemplate($templateId)
            ->byContractor($clabNo)
            ->with(['worker', 'assignedBy'])
            ->get()
            ->map(function ($assignment) use ($clabNo) {
                $periodCount = $this->getWorkerPayrollPeriodCount($assignment->worker_id, $clabNo);

                return [
                    'assignment_id' => $assignment->id,
                    'worker_id' => $assignment->worker_id,
                    'worker_name' => $assignment->worker?->wkr_name,
                    'worker_passport' => $assignment->worker?->wkr_passno,
                    'current_period' => $periodCount,
                    'assigned_by' => $assignment->assignedBy?->name,
                    'assigned_at' => $assignment->assigned_at,
                    'notes' => $assignment->assignment_notes,
                ];
            });
    }

    /**
     * Get worker-level deductions that should apply to a specific worker in current month/period
     */
    public function getApplicableDeductionsForWorker(
        string $workerId,
        string $clabNo,
        int $month,
        int $currentPeriod
    ): Collection {
        // Get all worker-level templates assigned to this worker
        $assignments = WorkerDeduction::byWorker($workerId)
            ->byContractor($clabNo)
            ->with('deductionTemplate')
            ->get();

        return $assignments
            ->pluck('deductionTemplate')
            ->filter(function ($template) use ($month, $currentPeriod) {
                if (! $template || ! $template->is_active) {
                    return false;
                }

                // Check month criteria
                if (! $template->shouldApplyInMonth($month)) {
                    return false;
                }

                // Check period criteria
                if (! $template->shouldApplyInPeriod($currentPeriod)) {
                    return false;
                }

                return true;
            });
    }
}
