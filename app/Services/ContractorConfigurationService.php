<?php

namespace App\Services;

use App\Models\ContractorConfigChange;
use App\Models\ContractorConfiguration;
use App\Models\DeductionTemplate;
use App\Models\User;
use Illuminate\Support\Collection;

class ContractorConfigurationService
{
    /**
     * Get contractor configuration (creates if not exists)
     */
    public function getContractorConfiguration(string $clabNo): ContractorConfiguration
    {
        return ContractorConfiguration::with('deductions')->firstOrCreate(
            ['contractor_clab_no' => $clabNo],
            [
                'contractor_name' => $this->getContractorName($clabNo),
                'service_charge_exempt' => false,
            ]
        );
    }

    /**
     * Get all contractor configurations (creates for contractors that don't have configs yet)
     */
    public function getAllContractorConfigurations(): Collection
    {
        // Get all active contractors from users table
        $contractors = User::where('role', 'client')->orderBy('name')->get();

        return $contractors->map(function ($contractor) {
            return ContractorConfiguration::with('deductions')->firstOrCreate(
                ['contractor_clab_no' => $contractor->contractor_clab_no],
                [
                    'contractor_name' => $contractor->name,
                    'service_charge_exempt' => false,
                ]
            );
        });
    }

    /**
     * Update contractor configuration
     */
    public function updateConfiguration(
        string $clabNo,
        bool $serviceChargeExempt,
        bool $penaltyExempt = false,
        bool $paymentEnabled = true
    ): ContractorConfiguration {
        $config = $this->getContractorConfiguration($clabNo);

        $before = [
            'service_charge_exempt' => (bool) $config->service_charge_exempt,
            'penalty_exempt' => (bool) $config->penalty_exempt,
            'payment_enabled' => (bool) $config->payment_enabled,
        ];

        $config->update([
            'service_charge_exempt' => $serviceChargeExempt,
            'penalty_exempt' => $penaltyExempt,
            'payment_enabled' => $paymentEnabled,
            'updated_by' => auth()->id(),
        ]);

        $this->recordChange(
            $config,
            ContractorConfigChange::SETTING_SERVICE_CHARGE,
            $this->exemptLabel($before['service_charge_exempt']),
            $this->exemptLabel($serviceChargeExempt)
        );

        $this->recordChange(
            $config,
            ContractorConfigChange::SETTING_PENALTY,
            $this->exemptLabel($before['penalty_exempt']),
            $this->exemptLabel($penaltyExempt)
        );

        $this->recordChange(
            $config,
            ContractorConfigChange::SETTING_PAYMENT,
            $this->paymentLabel($before['payment_enabled']),
            $this->paymentLabel($paymentEnabled)
        );

        return $config->fresh(['deductions']);
    }

    /**
     * Enable or disable a contractor's ability to make payments.
     * Used as a temporary lock while a wrong payroll is being regenerated.
     */
    public function setPaymentEnabled(string $clabNo, bool $enabled): ContractorConfiguration
    {
        $config = $this->getContractorConfiguration($clabNo);
        $wasEnabled = (bool) $config->payment_enabled;

        $config->update([
            'payment_enabled' => $enabled,
            'updated_by' => auth()->id(),
        ]);

        $this->recordChange(
            $config,
            ContractorConfigChange::SETTING_PAYMENT,
            $this->paymentLabel($wasEnabled),
            $this->paymentLabel($enabled)
        );

        return $config->fresh(['deductions']);
    }

    /**
     * Enable deductions for a contractor
     */
    public function enableDeductions(string $clabNo, array $deductionTemplateIds): void
    {
        $config = $this->getContractorConfiguration($clabNo);
        $before = $this->deductionNames($config);

        // Sync deductions (will remove unchecked ones and add new ones)
        $syncData = [];
        foreach ($deductionTemplateIds as $deductionId) {
            $syncData[$deductionId] = [
                'enabled_by' => auth()->id(),
                'enabled_at' => now(),
            ];
        }

        $config->deductions()->sync($syncData);

        $this->recordChange(
            $config,
            ContractorConfigChange::SETTING_DEDUCTIONS,
            $before,
            $this->deductionNames($config->fresh(['deductions']))
        );
    }

    /**
     * Enable a specific deduction for a contractor
     */
    public function enableDeductionForContractor(int $contractorConfigId, int $deductionTemplateId): void
    {
        $config = ContractorConfiguration::with('deductions')->findOrFail($contractorConfigId);

        // Attach if not already attached
        if (! $config->deductions()->where('deduction_template_id', $deductionTemplateId)->exists()) {
            $before = $this->deductionNames($config);

            $config->deductions()->attach($deductionTemplateId, [
                'enabled_by' => auth()->id(),
                'enabled_at' => now(),
            ]);

            $this->recordChange(
                $config,
                ContractorConfigChange::SETTING_DEDUCTIONS,
                $before,
                $this->deductionNames($config->fresh(['deductions']))
            );
        }
    }

    /**
     * Disable a specific deduction for a contractor
     */
    public function disableDeductionForContractor(int $contractorConfigId, int $deductionTemplateId): void
    {
        $config = ContractorConfiguration::with('deductions')->findOrFail($contractorConfigId);
        $before = $this->deductionNames($config);

        $config->deductions()->detach($deductionTemplateId);

        $this->recordChange(
            $config,
            ContractorConfigChange::SETTING_DEDUCTIONS,
            $before,
            $this->deductionNames($config->fresh(['deductions']))
        );
    }

    /**
     * Get all contractors that have a specific deduction template enabled
     */
    public function getContractorsWithDeduction(int $deductionTemplateId): Collection
    {
        return ContractorConfiguration::whereHas('deductions', function ($query) use ($deductionTemplateId) {
            $query->where('deduction_template_id', $deductionTemplateId);
        })->with('deductions')->get();
    }

    /**
     * Get deductions that should be applied for a contractor in a given month
     */
    public function getDeductionsForMonth(string $clabNo, int $month): Collection
    {
        $config = $this->getContractorConfiguration($clabNo);

        return $config->getDeductionsForMonth($month);
    }

    /**
     * Check if contractor is exempt from service charges
     */
    public function isServiceChargeExempt(string $clabNo): bool
    {
        $config = $this->getContractorConfiguration($clabNo);

        return $config->service_charge_exempt ?? false;
    }

    /**
     * Check if contractor is exempt from penalty
     */
    public function isPenaltyExempt(string $clabNo): bool
    {
        $config = $this->getContractorConfiguration($clabNo);

        return $config->penalty_exempt ?? false;
    }

    /**
     * Check if the contractor is currently allowed to make payments.
     * Defaults to true when no configuration exists yet.
     */
    public function isPaymentEnabled(string $clabNo): bool
    {
        $config = $this->getContractorConfiguration($clabNo);

        return $config->payment_enabled ?? true;
    }

    // === Change history ===

    /**
     * Write one entry to the contractor configuration history.
     * Skipped automatically when the value did not actually change.
     */
    protected function recordChange(
        ContractorConfiguration $config,
        string $setting,
        ?string $oldValue,
        ?string $newValue
    ): void {
        ContractorConfigChange::record(
            clabNo: $config->contractor_clab_no,
            setting: $setting,
            oldValue: $oldValue,
            newValue: $newValue,
            contractorName: $config->contractor_name,
        );
    }

    /**
     * Comma separated list of the contractor's enabled deduction templates.
     */
    protected function deductionNames(ContractorConfiguration $config): string
    {
        $names = $config->deductions->pluck('name')->sort()->values();

        return $names->isEmpty() ? 'None' : $names->implode(', ');
    }

    protected function exemptLabel(bool $exempt): string
    {
        return $exempt ? 'Exempt' : 'Not Exempt';
    }

    protected function paymentLabel(bool $enabled): string
    {
        return $enabled ? 'Enabled' : 'Disabled';
    }

    /**
     * Get contractor name from users table
     */
    protected function getContractorName(string $clabNo): string
    {
        return User::where('contractor_clab_no', $clabNo)->value('name') ?? 'Unknown Contractor';
    }

    // === Deduction Template Management ===

    /**
     * Get all deduction templates
     */
    public function getAllDeductionTemplates(): Collection
    {
        return DeductionTemplate::orderBy('name')->get();
    }

    /**
     * Get active deduction templates
     */
    public function getActiveDeductionTemplates(): Collection
    {
        return DeductionTemplate::active()->orderBy('name')->get();
    }

    /**
     * Get contractor-level templates only
     */
    public function getContractorLevelTemplates(): Collection
    {
        return DeductionTemplate::contractorLevel()->orderBy('name')->get();
    }

    /**
     * Get worker-level templates only
     */
    public function getWorkerLevelTemplates(): Collection
    {
        return DeductionTemplate::workerLevel()->orderBy('name')->get();
    }

    /**
     * Create a new deduction template
     */
    public function createDeductionTemplate(array $data): DeductionTemplate
    {
        return DeductionTemplate::create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'amount' => $data['amount'],
            'type' => $data['type'] ?? 'contractor', // 'contractor' or 'worker'
            'apply_months' => $data['apply_months'],
            'apply_periods' => $data['apply_periods'] ?? null, // Target payroll periods for worker-level
            'is_active' => $data['is_active'] ?? true,
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);
    }

    /**
     * Update a deduction template
     */
    public function updateDeductionTemplate(int $id, array $data): DeductionTemplate
    {
        $template = DeductionTemplate::findOrFail($id);

        $template->update([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'amount' => $data['amount'],
            'type' => $data['type'] ?? $template->type, // Preserve existing type if not provided
            'apply_months' => $data['apply_months'],
            'apply_periods' => $data['apply_periods'] ?? null, // Target payroll periods for worker-level
            'is_active' => $data['is_active'] ?? true,
            'updated_by' => auth()->id(),
        ]);

        return $template->fresh();
    }

    /**
     * Delete a deduction template
     */
    public function deleteDeductionTemplate(int $id): void
    {
        $template = DeductionTemplate::findOrFail($id);
        $template->delete();
    }

    /**
     * Toggle deduction template active status
     */
    public function toggleDeductionTemplate(int $id): DeductionTemplate
    {
        $template = DeductionTemplate::findOrFail($id);

        $template->update([
            'is_active' => ! $template->is_active,
            'updated_by' => auth()->id(),
        ]);

        return $template->fresh();
    }
}
