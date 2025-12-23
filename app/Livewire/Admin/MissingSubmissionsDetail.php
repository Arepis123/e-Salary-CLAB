<?php

namespace App\Livewire\Admin;

use App\Models\Contractor;
use App\Models\ContractWorker;
use App\Models\PayrollWorker;
use App\Models\User;
use Livewire\Component;

class MissingSubmissionsDetail extends Component
{
    public $clabNo;

    public $contractor;

    public $submittedWorkers = [];

    public $unsubmittedWorkers = [];

    public $stats = [];

    public function mount($clabNo)
    {
        $this->clabNo = $clabNo;
        $this->loadContractorInfo();
        $this->loadWorkers();
        $this->loadStats();
    }

    protected function loadContractorInfo()
    {
        // Try to get contractor from User table first
        $user = User::where('contractor_clab_no', $this->clabNo)
            ->where('role', 'client')
            ->first();

        if ($user) {
            $this->contractor = [
                'clab_no' => $this->clabNo,
                'name' => $user->company_name ?? $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
            ];
        } else {
            // Fallback to Contractor table from worker_db
            $contractorData = Contractor::where('ctr_clab_no', $this->clabNo)->first();

            $this->contractor = [
                'clab_no' => $this->clabNo,
                'name' => $contractorData ? $contractorData->ctr_comp_name : 'Contractor '.$this->clabNo,
                'email' => $contractorData ? $contractorData->ctr_email : null,
                'phone' => $contractorData ? ($contractorData->ctr_contact_mobileno ?? $contractorData->ctr_telno) : null,
            ];
        }
    }

    protected function loadWorkers()
    {
        $currentMonth = now()->month;
        $currentYear = now()->year;

        // Get all active workers for this contractor with worker and country relationships
        $allActiveWorkers = ContractWorker::active()
            ->where('con_ctr_clab_no', $this->clabNo)
            ->with(['worker.country'])
            ->get();

        // Get submitted worker IDs for current month
        $submittedWorkerIds = PayrollWorker::whereHas('payrollSubmission', function ($query) use ($currentMonth, $currentYear) {
            $query->where('month', $currentMonth)
                ->where('year', $currentYear)
                ->where('contractor_clab_no', $this->clabNo);
        })
            ->pluck('worker_id')
            ->toArray();

        // Split workers into submitted and unsubmitted
        foreach ($allActiveWorkers as $contractWorker) {
            $worker = $contractWorker->worker;

            $workerData = [
                'worker_id' => $contractWorker->con_wkr_id,
                'name' => $worker ? $worker->wkr_name : 'N/A',
                'passport' => $contractWorker->con_wkr_passno ?? 'N/A',
                'position' => 'General Worker', // Default position
                'nationality' => $worker && $worker->country ? $worker->country->cty_desc : 'N/A',
                'passport_expiry' => $worker && $worker->wkr_passexp
                    ? $worker->wkr_passexp->format('d/m/Y')
                    : 'N/A',
                'permit_expiry' => $worker && $worker->wkr_permitexp
                    ? $worker->wkr_permitexp->format('d/m/Y')
                    : 'N/A',
                'status' => $contractWorker->isActive() ? 'Active' : 'Inactive',
            ];

            if (in_array($contractWorker->con_wkr_id, $submittedWorkerIds)) {
                $this->submittedWorkers[] = $workerData;
            } else {
                $this->unsubmittedWorkers[] = $workerData;
            }
        }
    }

    protected function loadStats()
    {
        $this->stats = [
            'total_workers' => count($this->submittedWorkers) + count($this->unsubmittedWorkers),
            'submitted_count' => count($this->submittedWorkers),
            'unsubmitted_count' => count($this->unsubmittedWorkers),
            'submission_rate' => count($this->submittedWorkers) + count($this->unsubmittedWorkers) > 0
                ? round((count($this->submittedWorkers) / (count($this->submittedWorkers) + count($this->unsubmittedWorkers))) * 100, 1)
                : 0,
        ];
    }

    public function render()
    {
        return view('livewire.admin.missing-submissions-detail');
    }
}
