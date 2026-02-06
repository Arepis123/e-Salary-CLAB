<?php

namespace App\Livewire\Admin;

use App\Models\SalaryAdjustment;
use App\Models\Worker;
use App\Services\ContractorWindowService;
use App\Services\WorkerService;
use Flux\Flux;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Configuration extends Component
{
    use WithPagination;

    #[Url]
    public $search = '';

    #[Url]
    public $countryFilter = '';

    #[Url]
    public $positionFilter = '';

    #[Url]
    public $sortBy = 'name';

    #[Url]
    public $sortDirection = 'asc';

    public $showEditModal = false;

    public $editingWorkerId = null;

    public $editingWorkerName = '';

    public $editingWorkerPassport = '';

    public $editingBasicSalary = '';

    public $remarks = '';

    public $perPage = 15;

    public $stats = [];

    public $showHistory = false;

    // Window management properties
    public $activeTab = 'salary';

    public $showWindowModal = false;

    public $showHistoryModal = false;

    public $selectedContractorClab = '';

    public $selectedContractorName = '';

    public $windowAction = '';

    public $windowRemarks = '';

    public $contractorHistory = [];

    public $windowStats = [];

    // Contractor configuration properties
    public $contractorConfigs = [];

    public $editingContractorClab = '';

    public $editingContractorName = '';

    public $editServiceChargeExempt = false;

    public $editPenaltyExempt = false;

    public $editEnabledDeductions = []; // Array of deduction template IDs

    // Deduction template management
    public $deductionTemplates = [];

    public $showTemplateModal = false;

    public $editingTemplateId = null;

    public $templateName = '';

    public $templateDescription = '';

    public $templateAmount = '';

    public $templateType = 'contractor'; // 'contractor' or 'worker'

    public $templateMonths = [];

    public $templatePeriods = []; // Target payroll periods for worker-level deductions

    public $templateIsActive = true;

    // Worker assignment modal properties
    public $showWorkerAssignmentModal = false;

    public $selectedTemplateId = null;

    public $selectedTemplateName = '';

    public $workerFilterContractor = '';

    public $workerFilterPeriods = [];

    public $availableWorkers = [];

    public $assignedWorkers = [];

    public $selectedWorkerIds = [];

    public $assignmentNotes = '';

    // Contractor assignment modal properties
    public $showContractorAssignmentModal = false;

    public $selectedContractorIds = [];

    public $selectAllContractors = false;

    protected WorkerService $workerService;

    protected ContractorWindowService $windowService;

    protected \App\Services\ContractorConfigurationService $configService;

    protected \App\Services\BillplzService $billplzService;

    // Payment sync properties
    public $isSyncingPayments = false;

    public $syncResults = [];

    // Cancelled payment sync properties
    public $isSyncingCancelledPayments = false;

    public $cancelledSyncResults = [];

    public $cancelledSyncMonth;

    public $cancelledSyncYear;

    // Worker settings properties
    public $workerSearch = '';

    public $workerContractorFilter = '';

    public $workerStatusFilter = '';

    public $workersPage = 1;

    public $workersPerPage = 15;

    public $showDeactivateModal = false;

    public $deactivatingWorkerId = '';

    public $deactivatingWorkerName = '';

    public $deactivatingWorkerPassport = '';

    public $deactivatingContractorClab = '';

    public $deactivateReason = '';

    public function boot(
        WorkerService $workerService,
        ContractorWindowService $windowService,
        \App\Services\ContractorConfigurationService $configService,
        \App\Services\BillplzService $billplzService
    ) {
        $this->workerService = $workerService;
        $this->windowService = $windowService;
        $this->configService = $configService;
        $this->billplzService = $billplzService;
    }

    public function mount()
    {
        // Check if user is super admin
        if (! auth()->user()->isSuperAdmin()) {
            abort(403, 'Unauthorized access. Only Super Admin can access this page.');
        }

        // Set default cancelled sync month/year to current
        $this->cancelledSyncMonth = now()->month;
        $this->cancelledSyncYear = now()->year;

        $this->loadStats();
        $this->loadWindowStats();
        $this->loadContractorConfigs();
        $this->loadDeductionTemplates();
    }

    public function loadStats()
    {
        // Get worker IDs that have contracts
        $contractedWorkerIds = \App\Models\ContractWorker::pluck('con_wkr_id')->unique();

        $this->stats = [
            'total_workers' => Worker::whereIn('wkr_id', $contractedWorkerIds)->count(),
            'active_workers' => Worker::whereIn('wkr_id', $contractedWorkerIds)->active()->count(),
            'avg_salary' => Worker::whereIn('wkr_id', $contractedWorkerIds)->active()->avg('wkr_salary') ?? 0,
            'total_salary_cost' => Worker::whereIn('wkr_id', $contractedWorkerIds)->active()->sum('wkr_salary') ?? 0,
        ];
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedCountryFilter()
    {
        $this->resetPage();
    }

    public function updatedPositionFilter()
    {
        $this->resetPage();
    }

    public function sortByColumn($column)
    {
        if ($this->sortBy === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDirection = 'asc';
        }
        $this->resetPage();
    }

    public function clearFilters()
    {
        $this->search = '';
        $this->countryFilter = '';
        $this->positionFilter = '';
        $this->resetPage();
    }

    public function openEditModal($workerId)
    {
        $worker = Worker::find($workerId);

        if (! $worker) {
            Flux::toast(variant: 'danger', text: 'Worker not found.');

            return;
        }

        $this->editingWorkerId = $worker->wkr_id;
        $this->editingWorkerName = $worker->wkr_name;
        $this->editingWorkerPassport = $worker->wkr_passno;
        $this->editingBasicSalary = number_format($worker->wkr_salary, 2, '.', '');
        $this->remarks = '';
        $this->showEditModal = true;
    }

    public function closeEditModal()
    {
        $this->showEditModal = false;
        $this->editingWorkerId = null;
        $this->editingWorkerName = '';
        $this->editingWorkerPassport = '';
        $this->editingBasicSalary = '';
        $this->remarks = '';
    }

    public function toggleHistory()
    {
        $this->showHistory = ! $this->showHistory;
    }

    public function updateBasicSalary()
    {
        $this->validate([
            'editingBasicSalary' => 'required|numeric|min:0|max:99999.99',
            'remarks' => 'nullable|string|max:500',
        ]);

        try {
            // Update in second database (worker_db)
            $worker = Worker::find($this->editingWorkerId);

            if (! $worker) {
                Flux::toast(variant: 'danger', text: 'Worker not found.');

                return;
            }

            $oldSalary = $worker->wkr_salary;
            $newSalary = $this->editingBasicSalary;

            // Only update if salary changed
            if ($oldSalary == $newSalary) {
                Flux::toast(variant: 'warning', text: 'Salary has not changed.');

                return;
            }

            // Update the worker salary in the second database
            DB::connection('worker_db')
                ->table('workers')
                ->where('wkr_id', $this->editingWorkerId)
                ->update([
                    'wkr_salary' => $newSalary,
                ]);

            // Log the adjustment in our main database
            SalaryAdjustment::create([
                'worker_id' => $this->editingWorkerId,
                'worker_name' => $this->editingWorkerName,
                'worker_passport' => $this->editingWorkerPassport,
                'old_salary' => $oldSalary,
                'new_salary' => $newSalary,
                'adjusted_by' => auth()->id(),
                'remarks' => $this->remarks,
            ]);

            // Clear cache for this worker
            \Cache::forget("worker:{$this->editingWorkerId}");
            \Cache::forget('contract_workers:active');

            Flux::toast(
                variant: 'success',
                heading: 'Salary Updated!',
                text: "Basic salary for {$this->editingWorkerName} updated from RM ".number_format($oldSalary, 2).' to RM '.number_format($newSalary, 2)
            );

            $this->closeEditModal();
            $this->loadStats();
        } catch (\Exception $e) {
            Flux::toast(
                variant: 'danger',
                heading: 'Update Failed',
                text: 'Failed to update basic salary: '.$e->getMessage()
            );
        }
    }

    // Window management methods
    public function loadWindowStats()
    {
        $this->windowStats = $this->windowService->getWindowStatistics();
    }

    public function switchTab(string $tab)
    {
        $this->activeTab = $tab;
    }

    public function openWindowModal(string $clabNo, string $contractorName, string $action)
    {
        $this->selectedContractorClab = $clabNo;
        $this->selectedContractorName = $contractorName;
        $this->windowAction = $action;
        $this->windowRemarks = '';
        $this->showWindowModal = true;
    }

    public function closeWindowModal()
    {
        $this->showWindowModal = false;
        $this->selectedContractorClab = '';
        $this->selectedContractorName = '';
        $this->windowAction = '';
        $this->windowRemarks = '';
    }

    public function confirmWindowAction()
    {
        $this->validate([
            'windowRemarks' => 'nullable|string|max:500',
        ]);

        try {
            if ($this->windowAction === 'open') {
                $setting = $this->windowService->openWindow(
                    $this->selectedContractorClab,
                    auth()->id(),
                    $this->windowRemarks
                );

                Flux::toast(
                    variant: 'success',
                    heading: 'Window Opened',
                    text: "OT entry and transaction window opened for {$this->selectedContractorName}. Locked entries have been unlocked."
                );
            } else {
                $setting = $this->windowService->closeWindow(
                    $this->selectedContractorClab,
                    auth()->id(),
                    $this->windowRemarks
                );

                Flux::toast(
                    variant: 'success',
                    heading: 'Window Closed',
                    text: "OT entry and transaction window closed for {$this->selectedContractorName}."
                );
            }

            $this->closeWindowModal();
            $this->loadWindowStats();
        } catch (\Exception $e) {
            Flux::toast(
                variant: 'danger',
                heading: 'Error',
                text: 'Failed to update window: '.$e->getMessage()
            );
        }
    }

    public function viewContractorHistory(string $clabNo, string $contractorName)
    {
        $this->selectedContractorClab = $clabNo;
        $this->selectedContractorName = $contractorName;
        $this->contractorHistory = $this->windowService->getContractorHistory($clabNo);
        $this->showHistoryModal = true;
    }

    public function closeHistoryModal()
    {
        $this->showHistoryModal = false;
        $this->selectedContractorClab = '';
        $this->selectedContractorName = '';
        $this->contractorHistory = [];
    }

    // Contractor configuration methods
    public function loadContractorConfigs()
    {
        $this->contractorConfigs = $this->configService->getAllContractorConfigurations();
    }

    public function loadDeductionTemplates()
    {
        // Eager load relationships for template table display
        $this->deductionTemplates = \App\Models\DeductionTemplate::with(['contractors', 'workerAssignments'])
            ->orderBy('name')
            ->get();
    }

    public function openContractorEditModal(string $clabNo)
    {
        $config = $this->configService->getContractorConfiguration($clabNo);

        $this->editingContractorClab = $config->contractor_clab_no;
        $this->editingContractorName = $config->contractor_name;
        $this->editServiceChargeExempt = $config->service_charge_exempt;
        $this->editPenaltyExempt = $config->penalty_exempt;

        // Load currently enabled deduction template IDs
        $this->editEnabledDeductions = $config->deductions->pluck('id')->toArray();

        $this->showEditModal = true;
    }

    public function closeContractorEditModal()
    {
        $this->showEditModal = false;
        $this->editingContractorClab = '';
        $this->editingContractorName = '';
        $this->editServiceChargeExempt = false;
        $this->editPenaltyExempt = false;
        $this->editEnabledDeductions = [];
    }

    public function saveContractorConfig()
    {
        $this->validate([
            'editEnabledDeductions' => 'array',
            'editEnabledDeductions.*' => 'integer|exists:deduction_templates,id',
        ]);

        try {
            // Update service charge and penalty exemption
            $this->configService->updateConfiguration(
                $this->editingContractorClab,
                $this->editServiceChargeExempt,
                $this->editPenaltyExempt
            );

            // Update enabled deductions
            $this->configService->enableDeductions(
                $this->editingContractorClab,
                $this->editEnabledDeductions
            );

            Flux::toast(
                variant: 'success',
                heading: 'Configuration Updated',
                text: "Settings updated for {$this->editingContractorName}"
            );

            $this->closeContractorEditModal();
            $this->loadContractorConfigs();
        } catch (\Exception $e) {
            Flux::toast(
                variant: 'danger',
                heading: 'Error',
                text: 'Failed to update configuration: '.$e->getMessage()
            );
        }
    }

    // Deduction template management methods
    public function openTemplateModal(?int $templateId = null)
    {
        if ($templateId) {
            $template = \App\Models\DeductionTemplate::find($templateId);
            if ($template) {
                $this->editingTemplateId = $template->id;
                $this->templateName = $template->name;
                $this->templateDescription = $template->description ?? '';
                $this->templateAmount = number_format($template->amount, 2, '.', '');
                $this->templateType = $template->type ?? 'contractor';
                $this->templateMonths = $template->apply_months ?? [];
                $this->templatePeriods = $template->apply_periods ?? [];
                $this->templateIsActive = $template->is_active;
            }
        } else {
            $this->resetTemplateForm();
        }

        $this->showTemplateModal = true;
    }

    public function closeTemplateModal()
    {
        $this->showTemplateModal = false;
        $this->resetTemplateForm();
    }

    protected function resetTemplateForm()
    {
        $this->editingTemplateId = null;
        $this->templateName = '';
        $this->templateDescription = '';
        $this->templateAmount = '';
        $this->templateType = 'contractor';
        $this->templateMonths = [];
        $this->templatePeriods = [];
        $this->templateIsActive = true;
    }

    public function saveTemplate()
    {
        $rules = [
            'templateName' => 'required|string|max:255',
            'templateDescription' => 'nullable|string|max:500',
            'templateAmount' => 'required|numeric|min:0|max:9999.99',
            'templateType' => 'required|in:contractor,worker',
            'templateMonths' => 'nullable|array',
            'templateMonths.*' => 'integer|min:1|max:12',
            'templatePeriods' => 'nullable|array',
            'templatePeriods.*' => 'integer|min:1|max:100',
        ];

        // Ensure at least one criteria is specified (months or periods)
        $this->validate($rules);

        // Custom validation: at least months or periods must be specified (both types)
        if (empty($this->templateMonths) && empty($this->templatePeriods)) {
            $this->addError('templateMonths', 'Please select at least one month or one target period.');
            $this->addError('templatePeriods', 'Please select at least one month or one target period.');

            return;
        }

        try {
            $data = [
                'name' => $this->templateName,
                'description' => $this->templateDescription,
                'amount' => $this->templateAmount,
                'type' => $this->templateType,
                'apply_months' => $this->templateMonths,
                'apply_periods' => $this->templatePeriods, // Save for both contractor and worker level
                'is_active' => $this->templateIsActive,
            ];

            if ($this->editingTemplateId) {
                $this->configService->updateDeductionTemplate($this->editingTemplateId, $data);
                $message = 'Deduction template updated successfully';
            } else {
                $this->configService->createDeductionTemplate($data);
                $message = 'Deduction template created successfully';
            }

            Flux::toast(
                variant: 'success',
                heading: 'Success',
                text: $message
            );

            $this->closeTemplateModal();
            $this->loadDeductionTemplates();
            $this->loadContractorConfigs(); // Reload to refresh deduction relationships
        } catch (\Exception $e) {
            Flux::toast(
                variant: 'danger',
                heading: 'Error',
                text: 'Failed to save template: '.$e->getMessage()
            );
        }
    }

    public function deleteTemplate(int $templateId)
    {
        try {
            $this->configService->deleteDeductionTemplate($templateId);

            Flux::toast(
                variant: 'success',
                heading: 'Template Deleted',
                text: 'Deduction template deleted successfully'
            );

            $this->loadDeductionTemplates();
            $this->loadContractorConfigs();
        } catch (\Exception $e) {
            Flux::toast(
                variant: 'danger',
                heading: 'Error',
                text: 'Failed to delete template: '.$e->getMessage()
            );
        }
    }

    public function toggleTemplate(int $templateId)
    {
        try {
            $template = $this->configService->toggleDeductionTemplate($templateId);

            Flux::toast(
                variant: 'success',
                heading: 'Status Updated',
                text: "Template {$template->name} is now ".($template->is_active ? 'active' : 'inactive')
            );

            $this->loadDeductionTemplates();
        } catch (\Exception $e) {
            Flux::toast(
                variant: 'danger',
                heading: 'Error',
                text: 'Failed to update template status: '.$e->getMessage()
            );
        }
    }

    // Worker assignment modal methods
    public function openWorkerAssignmentModal(int $templateId)
    {
        $template = \App\Models\DeductionTemplate::find($templateId);

        if (! $template || ! $template->isWorkerLevel()) {
            Flux::toast(variant: 'danger', text: 'Invalid template or not a worker-level template');

            return;
        }

        $this->selectedTemplateId = $templateId;
        $this->selectedTemplateName = $template->name;
        $this->workerFilterContractor = '';
        $this->workerFilterPeriods = $template->apply_periods ?? [];
        $this->availableWorkers = [];
        $this->assignedWorkers = [];
        $this->selectedWorkerIds = [];
        $this->assignmentNotes = '';
        $this->showWorkerAssignmentModal = true;
    }

    public function closeWorkerAssignmentModal()
    {
        $this->showWorkerAssignmentModal = false;
        $this->selectedTemplateId = null;
        $this->selectedTemplateName = '';
        $this->workerFilterContractor = '';
        $this->workerFilterPeriods = [];
        $this->availableWorkers = [];
        $this->assignedWorkers = [];
        $this->selectedWorkerIds = [];
        $this->assignmentNotes = '';
    }

    public function loadAvailableWorkers()
    {
        if (empty($this->workerFilterContractor)) {
            Flux::toast(variant: 'warning', text: 'Please select a contractor');

            return;
        }

        try {
            $workerDeductionService = app(\App\Services\WorkerDeductionService::class);

            // Load ALL workers under contractor (no period filtering)
            // Deduction will apply when they REACH the target periods in the future
            $this->availableWorkers = $workerDeductionService->filterWorkersByPeriods(
                $this->workerFilterContractor,
                [] // Empty array = show all workers regardless of current period
            )->toArray();

            // Load currently assigned workers for this template
            $this->assignedWorkers = $workerDeductionService->getAssignedWorkers(
                $this->selectedTemplateId,
                $this->workerFilterContractor
            )->toArray();

            // Pre-select already assigned workers
            $this->selectedWorkerIds = collect($this->assignedWorkers)
                ->pluck('worker_id')
                ->toArray();

            if (empty($this->availableWorkers)) {
                Flux::toast(
                    variant: 'info',
                    text: 'No workers found under this contractor'
                );
            }
        } catch (\Exception $e) {
            Flux::toast(
                variant: 'danger',
                heading: 'Error',
                text: 'Failed to load workers: '.$e->getMessage()
            );
        }
    }

    public function saveWorkerAssignments()
    {
        $this->validate([
            'selectedWorkerIds' => 'required|array|min:1',
            'assignmentNotes' => 'nullable|string|max:500',
        ]);

        try {
            $workerDeductionService = app(\App\Services\WorkerDeductionService::class);

            // Get currently assigned worker IDs
            $currentlyAssigned = collect($this->assignedWorkers)->pluck('worker_id')->toArray();

            // Determine adds and removes
            $toAdd = array_diff($this->selectedWorkerIds, $currentlyAssigned);
            $toRemove = array_diff($currentlyAssigned, $this->selectedWorkerIds);

            // Add new assignments
            if (! empty($toAdd)) {
                $workerDeductionService->assignDeductionToWorkers(
                    $this->selectedTemplateId,
                    $toAdd,
                    $this->workerFilterContractor,
                    $this->assignmentNotes
                );
            }

            // Remove unselected assignments
            if (! empty($toRemove)) {
                $workerDeductionService->removeDeductionFromWorkers(
                    $this->selectedTemplateId,
                    $toRemove,
                    $this->workerFilterContractor
                );
            }

            Flux::toast(
                variant: 'success',
                heading: 'Workers Updated',
                text: 'Deduction template assignments saved successfully'
            );

            $this->closeWorkerAssignmentModal();
            $this->loadDeductionTemplates();
        } catch (\Exception $e) {
            Flux::toast(
                variant: 'danger',
                heading: 'Error',
                text: 'Failed to save assignments: '.$e->getMessage()
            );
        }
    }

    // Contractor assignment modal methods
    public function openContractorAssignmentModal(int $templateId)
    {
        $template = \App\Models\DeductionTemplate::find($templateId);

        if (! $template || ! $template->isContractorLevel()) {
            Flux::toast(variant: 'danger', text: 'Invalid template or not a contractor-level template');

            return;
        }

        $this->selectedTemplateId = $templateId;
        $this->selectedTemplateName = $template->name;

        // Get contractors that have this template enabled
        $enabledContractors = $this->configService->getContractorsWithDeduction($templateId);
        $this->selectedContractorIds = $enabledContractors->pluck('id')->toArray();

        $this->selectAllContractors = false;
        $this->showContractorAssignmentModal = true;
    }

    public function closeContractorAssignmentModal()
    {
        $this->showContractorAssignmentModal = false;
        $this->selectedTemplateId = null;
        $this->selectedTemplateName = '';
        $this->selectedContractorIds = [];
        $this->selectAllContractors = false;
    }

    public function updatedSelectAllContractors($value)
    {
        if ($value) {
            // Select all contractors
            $allContractors = $this->configService->getAllContractorConfigurations();
            $this->selectedContractorIds = $allContractors->pluck('id')->toArray();
        } else {
            // Deselect all
            $this->selectedContractorIds = [];
        }
    }

    public function updatedSelectedContractorIds()
    {
        // Update selectAllContractors checkbox state based on selection
        $allContractors = $this->configService->getAllContractorConfigurations();
        $this->selectAllContractors = count($this->selectedContractorIds) === $allContractors->count() && $allContractors->count() > 0;
    }

    public function saveContractorAssignments()
    {
        $this->validate([
            'selectedContractorIds' => 'nullable|array',
        ]);

        try {
            // Get current contractors with this template
            $currentlyEnabled = $this->configService->getContractorsWithDeduction($this->selectedTemplateId)
                ->pluck('id')
                ->toArray();

            // Determine adds and removes
            $toEnable = array_diff($this->selectedContractorIds, $currentlyEnabled);
            $toDisable = array_diff($currentlyEnabled, $this->selectedContractorIds);

            // Enable for new contractors
            foreach ($toEnable as $contractorId) {
                $this->configService->enableDeductionForContractor($contractorId, $this->selectedTemplateId);
            }

            // Disable for removed contractors
            foreach ($toDisable as $contractorId) {
                $this->configService->disableDeductionForContractor($contractorId, $this->selectedTemplateId);
            }

            Flux::toast(
                variant: 'success',
                heading: 'Contractors Updated',
                text: 'Deduction template applied to '.count($this->selectedContractorIds).' contractor(s)'
            );

            $this->closeContractorAssignmentModal();
            $this->loadContractorConfigs();
            $this->loadDeductionTemplates();
        } catch (\Exception $e) {
            Flux::toast(
                variant: 'danger',
                heading: 'Error',
                text: 'Failed to save contractor assignments: '.$e->getMessage()
            );
        }
    }

    public function fixMissingReceipts()
    {
        // Check if user is super admin
        if (! auth()->user()->isSuperAdmin()) {
            Flux::toast(variant: 'danger', text: 'Unauthorized access.');

            return;
        }

        try {
            // Find all paid submissions to check for missing data
            $submissions = \App\Models\PayrollSubmission::where('status', 'paid')
                ->with(['payments' => function ($q) {
                    $q->where('status', 'completed');
                }])
                ->get();

            if ($submissions->isEmpty()) {
                Flux::toast(
                    variant: 'info',
                    heading: 'No Paid Submissions',
                    text: 'There are no paid submissions to check.'
                );

                return;
            }

            $fixedReceipts = 0;
            $fixedDates = 0;
            $fixedTransactions = 0;

            foreach ($submissions as $submission) {
                $changes = [];

                // Fix missing tax invoice number
                if (! $submission->tax_invoice_number) {
                    $submission->generateTaxInvoiceNumber();
                    $fixedReceipts++;
                    $changes[] = 'tax_invoice_number';
                }

                // Fix missing paid_at date from payment record
                $completedPayment = $submission->payments->first();
                if (! $submission->paid_at && $completedPayment) {
                    $paidAt = $completedPayment->completed_at;

                    // Try to get paid_at from payment_response if completed_at is null
                    if (! $paidAt && $completedPayment->payment_response) {
                        // Handle both array (already cast) and string (JSON) formats
                        $response = is_array($completedPayment->payment_response)
                            ? $completedPayment->payment_response
                            : json_decode($completedPayment->payment_response, true);
                        if (isset($response['paid_at'])) {
                            $paidAt = $response['paid_at'];
                        }
                    }

                    if ($paidAt) {
                        $submission->update(['paid_at' => $paidAt]);
                        $fixedDates++;
                        $changes[] = 'paid_at';
                    }
                }

                // Fix missing transaction_id on payment record
                if ($completedPayment && ! $completedPayment->transaction_id) {
                    // Try to get transaction_id from payment_response
                    if ($completedPayment->payment_response) {
                        // Handle both array (already cast) and string (JSON) formats
                        $response = is_array($completedPayment->payment_response)
                            ? $completedPayment->payment_response
                            : json_decode($completedPayment->payment_response, true);
                        if (isset($response['id'])) {
                            $completedPayment->update(['transaction_id' => $response['id']]);
                            $fixedTransactions++;
                            $changes[] = 'transaction_id';
                        }
                    }
                    // If still no transaction_id, use billplz_bill_id
                    if (! $completedPayment->transaction_id && $completedPayment->billplz_bill_id) {
                        $completedPayment->update(['transaction_id' => $completedPayment->billplz_bill_id]);
                        $fixedTransactions++;
                        $changes[] = 'transaction_id';
                    }
                }

                if (! empty($changes)) {
                    \Log::info('Fixed missing submission data', [
                        'submission_id' => $submission->id,
                        'fixed_fields' => $changes,
                        'tax_invoice_number' => $submission->tax_invoice_number,
                        'paid_at' => $submission->paid_at,
                        'fixed_by' => auth()->user()->name,
                    ]);
                }
            }

            $messages = [];
            if ($fixedReceipts > 0) {
                $messages[] = "{$fixedReceipts} receipt number(s)";
            }
            if ($fixedDates > 0) {
                $messages[] = "{$fixedDates} paid date(s)";
            }
            if ($fixedTransactions > 0) {
                $messages[] = "{$fixedTransactions} transaction ID(s)";
            }

            if (empty($messages)) {
                Flux::toast(
                    variant: 'info',
                    heading: 'All Data Complete',
                    text: 'All paid submissions already have complete data.'
                );

                return;
            }

            Flux::toast(
                variant: 'success',
                heading: 'Data Fixed',
                text: 'Generated: '.implode(', ', $messages)
            );
        } catch (\Exception $e) {
            Flux::toast(
                variant: 'danger',
                heading: 'Fix Failed',
                text: 'Failed to fix missing data: '.$e->getMessage()
            );

            \Log::error('Failed to fix missing receipts', [
                'error' => $e->getMessage(),
                'fixed_by' => auth()->user()->name,
            ]);
        }
    }

    public function syncAllPendingPayments()
    {
        // Check if user is super admin
        if (! auth()->user()->isSuperAdmin()) {
            Flux::toast(variant: 'danger', text: 'Unauthorized access.');

            return;
        }

        $this->isSyncingPayments = true;
        $this->syncResults = [];

        try {
            // Find all pending payments with Billplz bill IDs
            $pendingPayments = \App\Models\PayrollPayment::where('status', 'pending')
                ->whereNotNull('billplz_bill_id')
                ->with('payrollSubmission')
                ->get();

            if ($pendingPayments->isEmpty()) {
                Flux::toast(
                    variant: 'info',
                    heading: 'No Pending Payments',
                    text: 'There are no pending payments to sync.'
                );
                $this->isSyncingPayments = false;

                return;
            }

            $totalPending = $pendingPayments->count();
            $updated = 0;
            $failed = 0;
            $stillPending = 0;

            foreach ($pendingPayments as $payment) {
                try {
                    // Fetch bill status from Billplz
                    $bill = $this->billplzService->getBill($payment->billplz_bill_id);

                    if (! $bill) {
                        $this->syncResults[] = [
                            'payment_id' => $payment->id,
                            'bill_id' => $payment->billplz_bill_id,
                            'status' => 'error',
                            'message' => 'Failed to retrieve bill from Billplz API',
                        ];
                        $failed++;

                        continue;
                    }

                    // Check if bill is paid
                    if ($bill['paid']) {
                        // Update payment status in a transaction
                        DB::beginTransaction();

                        $paidAt = $bill['paid_at'] ?? now();

                        $payment->update([
                            'status' => 'completed',
                            'completed_at' => $paidAt,
                            'payment_response' => json_encode($bill),
                            'transaction_id' => $bill['id'],
                        ]);

                        // Update submission status
                        $submission = $payment->payrollSubmission;
                        $submission->update([
                            'status' => 'paid',
                            'paid_at' => $paidAt,
                        ]);

                        // Generate tax invoice number (receipt number)
                        if (! $submission->hasTaxInvoice()) {
                            $submission->generateTaxInvoiceNumber();
                        }

                        DB::commit();

                        $this->syncResults[] = [
                            'payment_id' => $payment->id,
                            'bill_id' => $payment->billplz_bill_id,
                            'submission_id' => $submission->id,
                            'status' => 'success',
                            'message' => "Payment completed for submission {$submission->month_year}",
                        ];
                        $updated++;

                        \Log::info('Payment auto-synced from Configuration page', [
                            'payment_id' => $payment->id,
                            'bill_id' => $payment->billplz_bill_id,
                            'submission_id' => $submission->id,
                            'synced_by' => auth()->user()->name,
                        ]);
                    } else {
                        $this->syncResults[] = [
                            'payment_id' => $payment->id,
                            'bill_id' => $payment->billplz_bill_id,
                            'status' => 'pending',
                            'message' => 'Payment still pending on Billplz',
                        ];
                        $stillPending++;
                    }
                } catch (\Exception $e) {
                    $this->syncResults[] = [
                        'payment_id' => $payment->id,
                        'bill_id' => $payment->billplz_bill_id,
                        'status' => 'error',
                        'message' => $e->getMessage(),
                    ];
                    $failed++;

                    \Log::error('Failed to sync payment', [
                        'payment_id' => $payment->id,
                        'bill_id' => $payment->billplz_bill_id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Show summary toast
            Flux::toast(
                variant: $updated > 0 ? 'success' : 'warning',
                heading: 'Sync Complete',
                text: "Synced {$totalPending} payments: {$updated} updated, {$stillPending} still pending, {$failed} failed"
            );
        } catch (\Exception $e) {
            Flux::toast(
                variant: 'danger',
                heading: 'Sync Failed',
                text: 'Failed to sync payments: '.$e->getMessage()
            );

            \Log::error('Bulk payment sync failed', [
                'error' => $e->getMessage(),
                'synced_by' => auth()->user()->name,
            ]);
        } finally {
            $this->isSyncingPayments = false;
        }
    }

    public function syncCancelledPayments()
    {
        // Check if user is super admin
        if (! auth()->user()->isSuperAdmin()) {
            Flux::toast(variant: 'danger', text: 'Unauthorized access.');

            return;
        }

        $this->isSyncingCancelledPayments = true;
        $this->cancelledSyncResults = [];

        try {
            // Build period filter based on selected month/year
            $periodStart = \Carbon\Carbon::create($this->cancelledSyncYear, $this->cancelledSyncMonth, 1)->startOfMonth();
            $periodEnd = $periodStart->copy()->endOfMonth();

            // Find all cancelled payments with Billplz bill IDs for the selected month
            $cancelledPayments = \App\Models\PayrollPayment::where('status', 'cancelled')
                ->whereNotNull('billplz_bill_id')
                ->whereBetween('created_at', [$periodStart, $periodEnd])
                ->with('payrollSubmission')
                ->get();

            if ($cancelledPayments->isEmpty()) {
                Flux::toast(
                    variant: 'info',
                    heading: 'No Cancelled Payments',
                    text: 'There are no cancelled payments to check for '.$periodStart->format('F Y').'.'
                );
                $this->isSyncingCancelledPayments = false;

                return;
            }

            $totalCancelled = $cancelledPayments->count();
            $updated = 0;
            $failed = 0;
            $stillUnpaid = 0;

            foreach ($cancelledPayments as $payment) {
                try {
                    // Fetch bill status from Billplz
                    $bill = $this->billplzService->getBill($payment->billplz_bill_id);

                    if (! $bill) {
                        $this->cancelledSyncResults[] = [
                            'payment_id' => $payment->id,
                            'bill_id' => $payment->billplz_bill_id,
                            'status' => 'error',
                            'message' => 'Failed to retrieve bill from Billplz API',
                        ];
                        $failed++;

                        continue;
                    }

                    // Check if bill is actually paid
                    if ($bill['paid']) {
                        // Update payment status in a transaction
                        DB::beginTransaction();

                        $paidAt = $bill['paid_at'] ?? now();

                        $payment->update([
                            'status' => 'completed',
                            'completed_at' => $paidAt,
                            'payment_response' => json_encode($bill),
                            'transaction_id' => $bill['id'],
                        ]);

                        // Update submission status
                        $submission = $payment->payrollSubmission;

                        // Only update submission if it's not already paid
                        if ($submission && $submission->status !== 'paid') {
                            $submission->update([
                                'status' => 'paid',
                                'paid_at' => $paidAt,
                            ]);

                            // Generate tax invoice number (receipt number)
                            if (! $submission->hasTaxInvoice()) {
                                $submission->generateTaxInvoiceNumber();
                            }
                        }

                        DB::commit();

                        $this->cancelledSyncResults[] = [
                            'payment_id' => $payment->id,
                            'bill_id' => $payment->billplz_bill_id,
                            'submission_id' => $submission->id ?? 'N/A',
                            'status' => 'success',
                            'message' => 'Cancelled payment was actually PAID! Updated submission '.($submission->month_year ?? 'N/A'),
                        ];
                        $updated++;

                        \Log::info('Cancelled payment found to be paid - synced from Configuration page', [
                            'payment_id' => $payment->id,
                            'bill_id' => $payment->billplz_bill_id,
                            'submission_id' => $submission->id ?? null,
                            'paid_at' => $paidAt,
                            'synced_by' => auth()->user()->name,
                        ]);
                    } else {
                        $this->cancelledSyncResults[] = [
                            'payment_id' => $payment->id,
                            'bill_id' => $payment->billplz_bill_id,
                            'status' => 'unpaid',
                            'message' => 'Cancelled payment confirmed unpaid on Billplz',
                        ];
                        $stillUnpaid++;
                    }
                } catch (\Exception $e) {
                    DB::rollBack();

                    $this->cancelledSyncResults[] = [
                        'payment_id' => $payment->id,
                        'bill_id' => $payment->billplz_bill_id,
                        'status' => 'error',
                        'message' => $e->getMessage(),
                    ];
                    $failed++;

                    \Log::error('Failed to sync cancelled payment', [
                        'payment_id' => $payment->id,
                        'bill_id' => $payment->billplz_bill_id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Show summary toast
            $variant = $updated > 0 ? 'success' : ($failed > 0 ? 'danger' : 'info');
            Flux::toast(
                variant: $variant,
                heading: 'Cancelled Payment Sync Complete',
                text: "Checked {$totalCancelled} cancelled payments for {$periodStart->format('F Y')}: {$updated} found paid, {$stillUnpaid} confirmed unpaid, {$failed} failed"
            );
        } catch (\Exception $e) {
            Flux::toast(
                variant: 'danger',
                heading: 'Sync Failed',
                text: 'Failed to sync cancelled payments: '.$e->getMessage()
            );

            \Log::error('Cancelled payment sync failed', [
                'error' => $e->getMessage(),
                'month' => $this->cancelledSyncMonth,
                'year' => $this->cancelledSyncYear,
                'synced_by' => auth()->user()->name,
            ]);
        } finally {
            $this->isSyncingCancelledPayments = false;
        }
    }

    // Worker settings methods
    public function updatedWorkerSearch()
    {
        $this->workersPage = 1;
    }

    public function updatedWorkerContractorFilter()
    {
        $this->workersPage = 1;
    }

    public function updatedWorkerStatusFilter()
    {
        $this->workersPage = 1;
    }

    public function clearWorkerFilters()
    {
        $this->workerSearch = '';
        $this->workerContractorFilter = '';
        $this->workerStatusFilter = '';
        $this->workersPage = 1;
    }

    public function openDeactivateModal(string $workerId, string $workerName, string $passport, string $contractorClab)
    {
        $this->deactivatingWorkerId = $workerId;
        $this->deactivatingWorkerName = $workerName;
        $this->deactivatingWorkerPassport = $passport;
        $this->deactivatingContractorClab = $contractorClab;
        $this->deactivateReason = '';
        $this->showDeactivateModal = true;
    }

    public function closeDeactivateModal()
    {
        $this->showDeactivateModal = false;
        $this->deactivatingWorkerId = '';
        $this->deactivatingWorkerName = '';
        $this->deactivatingWorkerPassport = '';
        $this->deactivatingContractorClab = '';
        $this->deactivateReason = '';
    }

    public function confirmDeactivate()
    {
        try {
            \App\Models\InactiveWorker::deactivate(
                $this->deactivatingWorkerId,
                $this->deactivatingWorkerName,
                $this->deactivatingWorkerPassport,
                $this->deactivatingContractorClab,
                $this->deactivateReason,
                auth()->id()
            );

            Flux::toast(
                variant: 'success',
                heading: 'Worker Deactivated',
                text: "{$this->deactivatingWorkerName} has been set as inactive."
            );

            $this->closeDeactivateModal();
        } catch (\Exception $e) {
            Flux::toast(
                variant: 'danger',
                heading: 'Error',
                text: 'Failed to deactivate worker: '.$e->getMessage()
            );
        }
    }

    public function reactivateWorker(string $workerId)
    {
        try {
            $inactive = \App\Models\InactiveWorker::where('worker_id', $workerId)->first();
            $workerName = $inactive?->worker_name ?? 'Worker';

            \App\Models\InactiveWorker::reactivate($workerId);

            Flux::toast(
                variant: 'success',
                heading: 'Worker Reactivated',
                text: "{$workerName} has been set as active."
            );
        } catch (\Exception $e) {
            Flux::toast(
                variant: 'danger',
                heading: 'Error',
                text: 'Failed to reactivate worker: '.$e->getMessage()
            );
        }
    }

    protected function getWorkersData(): array
    {
        // Get inactive worker IDs
        $inactiveWorkerIds = \App\Models\InactiveWorker::getInactiveWorkerIds();

        // Get contracted worker IDs
        $contractedWorkerIds = \App\Models\ContractWorker::pluck('con_wkr_id')->unique();

        // Build query
        $query = Worker::query()
            ->with(['country', 'workTrade', 'contractor'])
            ->whereIn('wkr_id', $contractedWorkerIds);

        // Apply search filter
        if ($this->workerSearch) {
            $query->where(function ($q) {
                $q->where('wkr_name', 'like', '%'.$this->workerSearch.'%')
                    ->orWhere('wkr_passno', 'like', '%'.$this->workerSearch.'%')
                    ->orWhere('wkr_id', 'like', '%'.$this->workerSearch.'%');
            });
        }

        // Apply contractor filter
        if ($this->workerContractorFilter) {
            $query->where('wkr_currentemp', $this->workerContractorFilter);
        }

        // Apply status filter
        if ($this->workerStatusFilter === 'inactive') {
            $query->whereIn('wkr_id', $inactiveWorkerIds);
        } elseif ($this->workerStatusFilter === 'active') {
            $query->whereNotIn('wkr_id', $inactiveWorkerIds);
        }

        // Order by name
        $query->orderBy('wkr_name');

        // Paginate
        $total = $query->count();
        $workers = $query
            ->skip(($this->workersPage - 1) * $this->workersPerPage)
            ->take($this->workersPerPage)
            ->get();

        // Transform to array with status
        $workersList = $workers->map(function ($worker) use ($inactiveWorkerIds) {
            return [
                'id' => $worker->wkr_id,
                'name' => $worker->wkr_name,
                'passport' => $worker->wkr_passno,
                'contractor_clab' => $worker->wkr_currentemp,
                'contractor_name' => $worker->contractor?->ctr_comp_name ?? $worker->wkr_currentemp,
                'is_inactive' => in_array($worker->wkr_id, $inactiveWorkerIds),
            ];
        })->toArray();

        // Get contractors for filter dropdown
        $workerContractors = \App\Models\User::where('role', 'client')
            ->whereNotNull('contractor_clab_no')
            ->orderBy('name')
            ->get(['contractor_clab_no', 'name'])
            ->map(fn ($u) => ['clab_no' => $u->contractor_clab_no, 'name' => $u->name])
            ->toArray();

        // Stats
        $totalWorkers = Worker::whereIn('wkr_id', $contractedWorkerIds)->count();
        $inactiveCount = count($inactiveWorkerIds);

        return [
            'workersList' => $workersList,
            'workerContractors' => $workerContractors,
            'workerStats' => [
                'total' => $totalWorkers,
                'active' => $totalWorkers - $inactiveCount,
                'inactive' => $inactiveCount,
            ],
            'workersPagination' => [
                'current_page' => $this->workersPage,
                'per_page' => $this->workersPerPage,
                'total' => $total,
                'last_page' => max(1, ceil($total / $this->workersPerPage)),
                'from' => $total > 0 ? (($this->workersPage - 1) * $this->workersPerPage) + 1 : 0,
                'to' => min($this->workersPage * $this->workersPerPage, $total),
            ],
            'inactiveWorkersList' => \App\Models\InactiveWorker::with('deactivatedBy')
                ->orderBy('deactivated_at', 'desc')
                ->limit(10)
                ->get(),
        ];
    }

    public function render()
    {
        // Get worker IDs that have contracts (only show workers with contracts)
        $contractedWorkerIds = \App\Models\ContractWorker::pluck('con_wkr_id')->unique();

        // Build query for workers (only contracted workers)
        $query = Worker::query()
            ->with(['country', 'workTrade'])
            ->whereIn('wkr_id', $contractedWorkerIds);

        // Apply search filter
        if ($this->search) {
            $query->where(function ($q) {
                $q->where('wkr_name', 'like', '%'.$this->search.'%')
                    ->orWhere('wkr_passno', 'like', '%'.$this->search.'%')
                    ->orWhere('wkr_id', 'like', '%'.$this->search.'%');
            });
        }

        // Apply country filter
        if ($this->countryFilter) {
            $query->where('wkr_country', $this->countryFilter);
        }

        // Apply position filter
        if ($this->positionFilter) {
            $query->where('wkr_wtrade', $this->positionFilter);
        }

        // Apply sorting
        switch ($this->sortBy) {
            case 'name':
                $query->orderBy('wkr_name', $this->sortDirection);
                break;
            case 'salary':
                $query->orderBy('wkr_salary', $this->sortDirection);
                break;
            case 'country':
                $query->orderBy('wkr_nationality', $this->sortDirection);
                break;
            default:
                $query->orderBy('wkr_name', $this->sortDirection);
        }

        $workers = $query->paginate($this->perPage);

        // Get distinct countries for filter (only from contracted workers) with their descriptions
        $countryCodes = Worker::whereIn('wkr_id', $contractedWorkerIds)
            ->select('wkr_country')
            ->distinct()
            ->whereNotNull('wkr_country')
            ->where('wkr_country', '!=', '')
            ->pluck('wkr_country')
            ->unique();

        // Get country descriptions from the Country lookup table
        $countries = \App\Models\Country::whereIn('cty_id', $countryCodes)
            ->orderBy('cty_desc')
            ->pluck('cty_desc', 'cty_id');

        // Get distinct positions for filter (only from contracted workers) with their descriptions
        $positionCodes = Worker::whereIn('wkr_id', $contractedWorkerIds)
            ->select('wkr_wtrade')
            ->distinct()
            ->whereNotNull('wkr_wtrade')
            ->where('wkr_wtrade', '!=', '')
            ->pluck('wkr_wtrade')
            ->unique();

        // Get position descriptions from the WorkTrade lookup table
        $positions = \App\Models\WorkTrade::whereIn('trade_id', $positionCodes)
            ->orderBy('trade_desc')
            ->pluck('trade_desc', 'trade_id');

        // Get recent salary adjustments (last 50)
        $salaryHistory = SalaryAdjustment::with('adjustedBy')
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();

        // Get contractors for window management tab
        $contractors = [];
        if ($this->activeTab === 'windows') {
            $contractors = $this->windowService->getAllContractorSettings();
        }

        // Get contractor configurations if on contractor-settings tab
        $contractorConfigs = [];
        $deductionTemplates = [];
        $allContractors = []; // For worker assignment modal and contractor assignment modal
        if ($this->activeTab === 'contractor-settings') {
            $contractorConfigs = $this->contractorConfigs;
            $deductionTemplates = $this->deductionTemplates;
            // Get all contractor configurations (needed for both modals)
            $allContractors = $this->configService->getAllContractorConfigurations();
        }

        // Get worker settings data if on workers tab
        $workersData = [];
        if ($this->activeTab === 'workers') {
            $workersData = $this->getWorkersData();
        }

        return view('livewire.admin.configuration', array_merge([
            'workers' => $workers,
            'countries' => $countries,
            'positions' => $positions,
            'stats' => $this->stats,
            'salaryHistory' => $salaryHistory,
            'contractors' => $contractors,
            'windowStats' => $this->windowStats,
            'contractorConfigs' => $contractorConfigs,
            'deductionTemplates' => $deductionTemplates,
            'allContractors' => $allContractors,
        ], $workersData))->layout('components.layouts.app', ['title' => __('Configuration')]);
    }
}
