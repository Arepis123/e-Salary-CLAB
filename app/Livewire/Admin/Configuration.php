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

    public $editEnabledDeductions = []; // Array of deduction template IDs

    // Deduction template management
    public $deductionTemplates = [];

    public $showTemplateModal = false;

    public $editingTemplateId = null;

    public $templateName = '';

    public $templateDescription = '';

    public $templateAmount = '';

    public $templateMonths = [];

    public $templateIsActive = true;

    protected WorkerService $workerService;

    protected ContractorWindowService $windowService;

    protected \App\Services\ContractorConfigurationService $configService;

    protected \App\Services\BillplzService $billplzService;

    // Payment sync properties
    public $isSyncingPayments = false;

    public $syncResults = [];

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
        $this->deductionTemplates = $this->configService->getAllDeductionTemplates();
    }

    public function openContractorEditModal(string $clabNo)
    {
        $config = $this->configService->getContractorConfiguration($clabNo);

        $this->editingContractorClab = $config->contractor_clab_no;
        $this->editingContractorName = $config->contractor_name;
        $this->editServiceChargeExempt = $config->service_charge_exempt;

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
        $this->editEnabledDeductions = [];
    }

    public function saveContractorConfig()
    {
        $this->validate([
            'editEnabledDeductions' => 'array',
            'editEnabledDeductions.*' => 'integer|exists:deduction_templates,id',
        ]);

        try {
            // Update service charge exemption
            $this->configService->updateConfiguration(
                $this->editingContractorClab,
                $this->editServiceChargeExempt
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
                $this->templateMonths = $template->apply_months ?? [];
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
        $this->templateMonths = [];
        $this->templateIsActive = true;
    }

    public function saveTemplate()
    {
        $this->validate([
            'templateName' => 'required|string|max:255',
            'templateDescription' => 'nullable|string|max:500',
            'templateAmount' => 'required|numeric|min:0|max:9999.99',
            'templateMonths' => 'required|array|min:1',
            'templateMonths.*' => 'integer|min:1|max:12',
        ]);

        try {
            $data = [
                'name' => $this->templateName,
                'description' => $this->templateDescription,
                'amount' => $this->templateAmount,
                'apply_months' => $this->templateMonths,
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

                        $payment->update([
                            'status' => 'completed',
                            'completed_at' => $bill['paid_at'] ?? now(),
                            'payment_response' => json_encode($bill),
                            'transaction_id' => $bill['id'],
                        ]);

                        // Update submission status
                        $submission = $payment->payrollSubmission;
                        $submission->update([
                            'status' => 'paid',
                            'paid_at' => $bill['paid_at'] ?? now(),
                        ]);

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
        if ($this->activeTab === 'contractor-settings') {
            $contractorConfigs = $this->contractorConfigs;
            $deductionTemplates = $this->deductionTemplates;
        }

        return view('livewire.admin.configuration', [
            'workers' => $workers,
            'countries' => $countries,
            'positions' => $positions,
            'stats' => $this->stats,
            'salaryHistory' => $salaryHistory,
            'contractors' => $contractors,
            'windowStats' => $this->windowStats,
            'contractorConfigs' => $contractorConfigs,
            'deductionTemplates' => $deductionTemplates,
        ])->layout('components.layouts.app', ['title' => __('Configuration')]);
    }
}
