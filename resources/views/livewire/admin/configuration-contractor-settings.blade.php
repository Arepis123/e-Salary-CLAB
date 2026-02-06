<!-- Deduction Templates Management -->
<flux:card class="p-4 sm:p-6 dark:bg-zinc-900 rounded-lg">
    <div class="mb-4 flex items-center justify-between">
        <div>
            <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">Deduction Templates</h2>
            <p class="text-xs text-zinc-600 dark:text-zinc-400 mt-1">
                Create reusable deduction templates that can be applied to contractors
            </p>
        </div>
        <flux:button
            variant="filled"
            size="sm"
            wire:click="openTemplateModal"
            icon="plus"
            icon-variant="outline"
        >
            Add Template
        </flux:button>
    </div>

    @if($deductionTemplates->isEmpty())
        <div class="py-12 text-center">
            <flux:icon.exclamation-triangle class="mx-auto size-7 text-zinc-400 dark:text-zinc-600 mb-4" />
            <p class="text-md font-medium text-zinc-900 dark:text-zinc-100 mb-2">No Deduction Templates</p>
            <p class="text-sm text-zinc-600 dark:text-zinc-400">
                Create a template to get started.
            </p>
        </div>
    @else
        <div class="overflow-x-auto">
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>
                        <span class="text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Template Name</span>
                    </flux:table.column>
                    <flux:table.column>
                        <span class="text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Type</span>
                    </flux:table.column>
                    <flux:table.column>
                        <span class="text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Amount (RM)</span>
                    </flux:table.column>
                    <flux:table.column>
                        <span class="text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Apply in Months</span>
                    </flux:table.column>
                    <flux:table.column>
                        <span class="text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Target Periods</span>
                    </flux:table.column>
                    <flux:table.column>
                        <span class="text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Assigned Workers</span>
                    </flux:table.column>
                    <flux:table.column>
                        <span class="text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Status</span>
                    </flux:table.column>
                    <flux:table.column>
                        <span class="text-center text-xs font-medium text-zinc-600 dark:text-zinc-400">Actions</span>
                    </flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach($deductionTemplates as $template)
                        <flux:table.row :key="$template->id">
                            <flux:table.cell variant="strong">
                                <div>
                                    <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $template->name }}</p>
                                    @if($template->description)
                                        <p class="text-xs text-zinc-600 dark:text-zinc-400">{{ $template->description }}</p>
                                    @endif
                                </div>
                            </flux:table.cell>

                            <flux:table.cell variant="strong">
                                <flux:badge color="{{ $template->type === 'contractor' ? 'blue' : 'purple' }}" size="sm">
                                    {{ ucfirst($template->type) }}
                                </flux:badge>
                            </flux:table.cell>

                            <flux:table.cell variant="strong">
                                <span class="font-semibold text-blue-600 dark:text-blue-400">
                                    {{ number_format($template->amount, 2) }}
                                </span>
                            </flux:table.cell>

                            <flux:table.cell variant="strong">
                                @php
                                    $monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                                    $months = $template->apply_months && count($template->apply_months) > 0
                                        ? collect($template->apply_months)->map(fn($m) => $monthNames[$m - 1])->join(', ')
                                        : null;
                                @endphp
                                @if($months)
                                    <span class="text-sm text-zinc-900 dark:text-zinc-100">{{ $months }}</span>
                                @else
                                    <span class="text-xs text-zinc-500 dark:text-zinc-400">All months</span>
                                @endif
                            </flux:table.cell>

                            <flux:table.cell variant="strong">
                                @if($template->apply_periods && count($template->apply_periods) > 0)
                                    <span class="text-sm text-zinc-900 dark:text-zinc-100">
                                        {{ collect($template->apply_periods)->sort()->join(', ') }}
                                    </span>
                                @else
                                    <span class="text-xs text-zinc-500 dark:text-zinc-400">All periods</span>
                                @endif
                            </flux:table.cell>

                            <flux:table.cell variant="strong">
                                @if($template->type === 'worker')
                                    @php
                                        $assignmentCount = $template->workerAssignments->count();
                                        $contractorCount = $template->workerAssignments->pluck('contractor_clab_no')->unique()->count();
                                    @endphp
                                    @if($assignmentCount > 0)
                                        <div class="flex flex-col gap-1">
                                            <flux:badge color="green" size="sm">
                                                {{ $assignmentCount }} {{ $assignmentCount === 1 ? 'worker' : 'workers' }}
                                            </flux:badge>
                                            @if($contractorCount > 1)
                                                <span class="text-xs text-zinc-500 dark:text-zinc-400">
                                                    across {{ $contractorCount }} contractors
                                                </span>
                                            @endif
                                        </div>
                                    @else
                                        <span class="text-xs text-amber-600 dark:text-amber-400">No workers assigned</span>
                                    @endif
                                @else
                                    @php
                                        $contractorCount = $template->contractors->count();
                                    @endphp
                                    @if($contractorCount > 0)
                                        <flux:badge color="blue" size="sm">
                                            {{ $contractorCount }} {{ $contractorCount === 1 ? 'contractor' : 'contractors' }}
                                        </flux:badge>
                                    @else
                                        <span class="text-xs text-amber-600 dark:text-amber-400">No contractors assigned</span>
                                    @endif
                                @endif
                            </flux:table.cell>

                            <flux:table.cell variant="strong">
                                <flux:badge color="{{ $template->is_active ? 'green' : 'zinc' }}" size="sm">
                                    {{ $template->is_active ? 'Active' : 'Inactive' }}
                                </flux:badge>
                            </flux:table.cell>

                            <flux:table.cell>
                                <div class="flex justify-center gap-2 flex-wrap">
                                    <flux:button
                                        size="sm"
                                        variant="ghost"
                                        wire:click="openTemplateModal({{ $template->id }})"
                                        icon="pencil"
                                        icon-variant="outline"
                                    >
                                        Edit
                                    </flux:button>

                                    @if($template->type === 'worker')
                                        <flux:button
                                            size="sm"
                                            variant="filled"
                                            wire:click="openWorkerAssignmentModal({{ $template->id }})"
                                            icon="users"
                                            icon-variant="outline"
                                        >
                                            Manage Workers
                                        </flux:button>
                                    @else
                                        <flux:button
                                            size="sm"
                                            variant="filled"
                                            wire:click="openContractorAssignmentModal({{ $template->id }})"
                                            icon="building-office"
                                            icon-variant="outline"
                                        >
                                            Manage Contractors
                                        </flux:button>
                                    @endif

                                    <flux:button
                                        size="sm"
                                        variant="ghost"
                                        wire:click="toggleTemplate({{ $template->id }})"
                                    >
                                        {{ $template->is_active ? 'Deactivate' : 'Activate' }}
                                    </flux:button>
                                    <flux:button
                                        size="sm"
                                        variant="danger"
                                        wire:click="deleteTemplate({{ $template->id }})"
                                        wire:confirm="Are you sure you want to delete this template? This will remove it from all contractors using it."
                                        icon="trash"
                                        icon-variant="outline"
                                    >
                                        Delete
                                    </flux:button>
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </div>
    @endif
</flux:card>

<!-- Contractor-Specific Settings -->
<flux:card class="p-4 sm:p-6 dark:bg-zinc-900 rounded-lg">
    <div class="mb-4 flex items-center justify-between">
        <div>
            <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">Contractor-Specific Settings</h2>
            <p class="text-xs text-zinc-600 dark:text-zinc-400 mt-1">
                Configure which deduction templates apply to each contractor and manage service charge exemptions
            </p>
        </div>
    </div>

    <div class="overflow-x-auto">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>
                    <span class="text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Contractor</span>
                </flux:table.column>
                <flux:table.column>
                    <span class="text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">CLAB No</span>
                </flux:table.column>
                <flux:table.column>
                    <span class="text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Enabled Deductions</span>
                </flux:table.column>
                <flux:table.column>
                    <span class="text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Service Charge</span>
                </flux:table.column>
                <flux:table.column>
                    <span class="text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Penalty</span>
                </flux:table.column>
                <flux:table.column>
                    <span class="text-center text-xs font-medium text-zinc-600 dark:text-zinc-400">Actions</span>
                </flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse($contractorConfigs as $config)
                    <flux:table.row :key="$config->id">
                        <flux:table.cell variant="strong">
                            <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $config->contractor_name }}</p>
                        </flux:table.cell>

                        <flux:table.cell variant="strong">
                            {{ $config->contractor_clab_no }}
                        </flux:table.cell>

                        <flux:table.cell variant="strong">
                            @if($config->deductions->isEmpty())
                                <span class="text-sm text-zinc-400">None</span>
                            @else
                                <div class="flex flex-wrap gap-1">
                                    @foreach($config->deductions as $deduction)
                                        <flux:badge color="blue" size="sm">
                                            {{ $deduction->name }}
                                        </flux:badge>
                                    @endforeach
                                </div>
                            @endif
                        </flux:table.cell>

                        <flux:table.cell variant="strong">
                            <flux:badge color="{{ $config->service_charge_exempt ? 'amber' : 'zinc' }}" size="sm">
                                {{ $config->service_charge_exempt ? 'Exempt (RM 0)' : 'Standard (RM 200)' }}
                            </flux:badge>
                        </flux:table.cell>

                        <flux:table.cell variant="strong">
                            <flux:badge color="{{ $config->penalty_exempt ? 'amber' : 'zinc' }}" size="sm">
                                {{ $config->penalty_exempt ? 'Exempt' : 'Standard (8%)' }}
                            </flux:badge>
                        </flux:table.cell>

                        <flux:table.cell>
                            <div class="flex justify-center">
                                <flux:button
                                    size="sm"
                                    variant="filled"
                                    wire:click="openContractorEditModal('{{ $config->contractor_clab_no }}')"
                                    icon="pencil"
                                    icon-variant="outline"
                                >
                                    Edit
                                </flux:button>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6">
                            <div class="py-12 text-center">
                                <flux:icon.users class="mx-auto size-7 text-zinc-400 dark:text-zinc-600 mb-4" />
                                <p class="text-md font-medium text-zinc-900 dark:text-zinc-100 mb-2">No Contractors Found</p>
                                <p class="text-sm text-zinc-600 dark:text-zinc-400">
                                    Contractors will appear here automatically.
                                </p>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>
</flux:card>

<!-- Edit Contractor Configuration Modal -->
<flux:modal name="edit-contractor-config" wire:model="showEditModal" class="md:w-2xl space-y-6">
    <div>
        <flux:heading size="lg">Edit Contractor Configuration</flux:heading>
        <flux:subheading>{{ $editingContractorName }} ({{ $editingContractorClab }})</flux:subheading>
    </div>

    <div class="space-y-4">
        <!-- Deduction Templates Selection -->
        <div>
            <flux:label>Enabled Deductions</flux:label>
            <flux:description>Select which deduction templates should be applied to this contractor's workers</flux:description>

            @if($deductionTemplates->isEmpty())
                <div class="mt-2 p-4 bg-zinc-50 dark:bg-zinc-800 rounded-lg text-sm text-zinc-600 dark:text-zinc-400">
                    No deduction templates available. Create templates first before enabling them for contractors.
                </div>
            @else
                <div class="mt-3 space-y-2">
                    @foreach($deductionTemplates->where('type', 'contractor') as $template)
                        <label class="flex items-start space-x-3 p-3 rounded-lg border {{ $template->is_active ? 'border-zinc-200 dark:border-zinc-700 hover:bg-zinc-50 dark:hover:bg-zinc-800' : 'border-zinc-100 dark:border-zinc-800 opacity-60' }} cursor-pointer">
                            <input
                                type="checkbox"
                                wire:model="editEnabledDeductions"
                                value="{{ $template->id }}"
                                {{ $template->is_active ? '' : 'disabled' }}
                                class="mt-1 rounded border-zinc-300 dark:border-zinc-600"
                            >
                            <div class="flex-1">
                                <div class="font-medium text-sm {{ $template->is_active ? 'text-zinc-900 dark:text-zinc-100' : 'text-zinc-500 dark:text-zinc-400' }}">
                                    {{ $template->name }}
                                    @if(!$template->is_active)
                                        <flux:badge color="zinc" size="sm" class="ml-2">Inactive</flux:badge>
                                    @endif
                                </div>
                                <div class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">
                                    RM {{ number_format($template->amount, 2) }} •
                                    @php
                                        $monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                                        $months = $template->apply_months && count($template->apply_months) > 0
                                            ? collect($template->apply_months)->map(fn($m) => $monthNames[$m - 1])->join(', ')
                                            : 'All months';
                                    @endphp
                                    Months: {{ $months }}
                                </div>
                                @if($template->description)
                                    <div class="text-xs text-zinc-400 dark:text-zinc-500 mt-1">{{ $template->description }}</div>
                                @endif
                            </div>
                        </label>
                    @endforeach
                </div>
            @endif
        </div>

        <!-- Service Charge Exemption -->
        <flux:checkbox
            wire:model="editServiceChargeExempt"
            label="Exempt from Service Charge"
            description="This contractor will pay RM 0 service charge instead of RM 200 per worker"
        />

        <!-- Penalty Exemption -->
        <flux:checkbox
            wire:model="editPenaltyExempt"
            label="Exempt from Late Payment Penalty"
            description="This contractor will not be charged 8% penalty for overdue payments"
        />
    </div>

    <div class="flex justify-end gap-2">
        <flux:button variant="ghost" wire:click="closeContractorEditModal">Cancel</flux:button>
        <flux:button variant="primary" wire:click="saveContractorConfig">
            <flux:icon.check class="size-4" />
            Save Changes
        </flux:button>
    </div>
</flux:modal>

<!-- Add/Edit Deduction Template Modal -->
<flux:modal name="template-modal" wire:model="showTemplateModal" class="md:w-2xl space-y-6">
    <div>
        <flux:heading size="lg">{{ $editingTemplateId ? 'Edit' : 'Add' }} Deduction Template</flux:heading>
        <flux:subheading>{{ $editingTemplateId ? 'Update the deduction template details' : 'Create a new deduction template' }}</flux:subheading>
    </div>

    <div class="space-y-4">
        <!-- Template Type -->
        <div>
            <flux:label>Template Type</flux:label>
            <flux:description>Choose whether this deduction applies to all workers (contractor-level) or specific workers only</flux:description>
            <div class="flex gap-4 mt-3">
                <label class="flex items-center space-x-2 cursor-pointer">
                    <input
                        type="radio"
                        wire:model.live="templateType"
                        value="contractor"
                        class="rounded-full border-zinc-300 dark:border-zinc-600"
                    >
                    <div>
                        <span class="text-sm font-medium">Contractor-level</span>
                        <p class="text-xs text-zinc-600 dark:text-zinc-400">Applies to ALL workers under selected contractors</p>
                    </div>
                </label>
                <label class="flex items-center space-x-2 cursor-pointer">
                    <input
                        type="radio"
                        wire:model.live="templateType"
                        value="worker"
                        class="rounded-full border-zinc-300 dark:border-zinc-600"
                    >
                    <div>
                        <span class="text-sm font-medium">Worker-level</span>
                        <p class="text-xs text-zinc-600 dark:text-zinc-400">Applies to SPECIFIC workers only</p>
                    </div>
                </label>
            </div>
        </div>

        <!-- Template Name -->
        <flux:input
            wire:model="templateName"
            label="Template Name"
            placeholder="e.g., Phone Topup, Uniform Fee, Safety Equipment"
            required
        />

        <!-- Description -->
        <flux:textarea
            wire:model="templateDescription"
            label="Description (Optional)"
            placeholder="Brief description of this deduction"
            rows="2"
        />

        <!-- Amount -->
        <flux:input
            wire:model="templateAmount"
            type="number"
            step="0.01"
            min="0"
            label="Amount (RM)"
            placeholder="0.00"
            required
        />

        <!-- Months Selection -->
        <div>
            <flux:label>Apply in Months <span class="text-zinc-500 dark:text-zinc-400">(Optional)</span></flux:label>
            <flux:description>
                Select specific months or leave empty to apply in all months. Must specify either months or target periods.
            </flux:description>

            <div class="grid grid-cols-4 gap-2 mt-3">
                @foreach(['Jan' => 1, 'Feb' => 2, 'Mar' => 3, 'Apr' => 4, 'May' => 5, 'Jun' => 6, 'Jul' => 7, 'Aug' => 8, 'Sep' => 9, 'Oct' => 10, 'Nov' => 11, 'Dec' => 12] as $monthName => $monthNum)
                    <label class="flex items-center space-x-2 p-2 rounded border border-zinc-200 dark:border-zinc-700 hover:bg-zinc-50 dark:hover:bg-zinc-800 cursor-pointer">
                        <input
                            type="checkbox"
                            wire:model="templateMonths"
                            value="{{ $monthNum }}"
                            class="rounded border-zinc-300 dark:border-zinc-600"
                        >
                        <span class="text-sm">{{ $monthName }}</span>
                    </label>
                @endforeach
            </div>

            @error('templateMonths')
                <flux:error>{{ $message }}</flux:error>
            @enderror
        </div>

        <!-- Payroll Periods Selection (Both contractor and worker level) -->
        <div>
            <flux:label>Target Payroll Periods <span class="text-zinc-500 dark:text-zinc-400">(Optional)</span></flux:label>
            <flux:description>
                @if($templateType === 'contractor')
                    Select which payroll submission counts this deduction should apply to (applies to ALL workers).
                    For example, select "2" to apply when workers reach their 2nd payroll submission.
                    Leave empty to apply in all periods.
                @else
                    Select which payroll submission counts this deduction should apply to (applies to assigned workers only).
                    For example, select "2" to apply when workers reach their 2nd payroll submission.
                    Leave empty to apply in all periods.
                @endif
            </flux:description>

            <div class="grid grid-cols-6 gap-2 mt-3">
                @foreach(range(1, 18) as $period)
                    <label class="flex items-center space-x-2 p-2 rounded border border-zinc-200 dark:border-zinc-700 hover:bg-zinc-50 dark:hover:bg-zinc-800 cursor-pointer">
                        <input
                            type="checkbox"
                            wire:model="templatePeriods"
                            value="{{ $period }}"
                            class="rounded border-zinc-300 dark:border-zinc-600"
                        >
                        <span class="text-sm">{{ $period }}</span>
                    </label>
                @endforeach
            </div>

            @error('templatePeriods')
                <flux:error>{{ $message }}</flux:error>
            @enderror
        </div>

        <!-- Active Status -->
        <flux:checkbox
            wire:model="templateIsActive"
            label="Active"
            description="Only active templates can be enabled for contractors"
        />
    </div>

    <div class="flex justify-end gap-2">
        <flux:button variant="ghost" wire:click="closeTemplateModal">Cancel</flux:button>
        <flux:button variant="primary" wire:click="saveTemplate">
            <flux:icon.check class="size-4" />
            {{ $editingTemplateId ? 'Update Template' : 'Create Template' }}
        </flux:button>
    </div>
</flux:modal>

<!-- Worker Assignment Modal -->
<flux:modal name="worker-assignment-modal" wire:model="showWorkerAssignmentModal" class="md:w-4xl space-y-6">
    <div>
        <flux:heading size="lg">Assign Workers</flux:heading>
        <flux:subheading>{{ $selectedTemplateName }}</flux:subheading>
    </div>

    <div class="space-y-4">
        <!-- Contractor Filter -->
        <flux:select wire:model.live="workerFilterContractor" label="Select Contractor">
            <option value="">Choose contractor...</option>
            @foreach($allContractors as $contractor)
                <option value="{{ $contractor->contractor_clab_no }}">
                    {{ $contractor->name }} ({{ $contractor->contractor_clab_no }})
                </option>
            @endforeach
        </flux:select>

        @if($workerFilterContractor)
            <flux:button wire:click="loadAvailableWorkers" variant="primary" icon="arrow-path">
                Load Workers
            </flux:button>

            @if(!empty($workerFilterPeriods))
                <div class="mt-3 p-4 bg-blue-50 dark:bg-blue-950 rounded-lg border border-blue-200 dark:border-blue-800">
                    <div class="flex gap-3">
                        <flux:icon.information-circle class="size-5 text-blue-600 dark:text-blue-400 flex-shrink-0 mt-0.5" />
                        <div class="text-sm text-blue-900 dark:text-blue-100">
                            <strong>Target Periods: {{ collect($workerFilterPeriods)->sort()->join(', ') }}</strong>
                            <p class="mt-1 text-blue-700 dark:text-blue-300">
                                This deduction will automatically apply to selected workers when they reach their
                                {{ collect($workerFilterPeriods)->sort()->map(fn($p) => $p . ($p == 1 ? 'st' : ($p == 2 ? 'nd' : ($p == 3 ? 'rd' : 'th'))))->join(', ') }}
                                payroll period(s).
                            </p>
                        </div>
                    </div>
                </div>
            @endif
        @endif

        <!-- Workers List -->
        @if(!empty($availableWorkers))
            <div class="mt-4">
                <flux:label>Select Workers to Assign</flux:label>
                <flux:description>
                    Showing {{ count($availableWorkers) }} worker(s) under this contractor.
                    Select workers who should receive this deduction when they reach the target periods.
                    Currently assigned workers are pre-selected.
                </flux:description>

                <div class="max-h-96 overflow-y-auto mt-3 border border-zinc-200 dark:border-zinc-700 rounded-lg">
                    <table class="w-full">
                        <thead class="sticky top-0 bg-zinc-50 dark:bg-zinc-800 border-b border-zinc-200 dark:border-zinc-700">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Select</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Worker Name</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Passport</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Current Period</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Basic Salary</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                            @foreach($availableWorkers as $worker)
                                <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800">
                                    <td class="px-4 py-3">
                                        <input
                                            type="checkbox"
                                            wire:model="selectedWorkerIds"
                                            value="{{ $worker['worker_id'] }}"
                                            class="rounded border-zinc-300 dark:border-zinc-600"
                                        >
                                    </td>
                                    <td class="px-4 py-3 text-sm text-zinc-900 dark:text-zinc-100">
                                        {{ $worker['worker_name'] }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-zinc-600 dark:text-zinc-400">
                                        {{ $worker['worker_passport'] }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <flux:badge color="blue" size="sm">
                                            Period {{ $worker['current_period'] }}
                                        </flux:badge>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-zinc-900 dark:text-zinc-100">
                                        RM {{ number_format($worker['basic_salary'], 2) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Assignment Notes -->
                <flux:textarea
                    wire:model="assignmentNotes"
                    label="Assignment Notes (Optional)"
                    placeholder="Why are these workers being assigned this deduction?"
                    rows="2"
                    class="mt-4"
                />

                @error('selectedWorkerIds')
                    <flux:error>{{ $message }}</flux:error>
                @enderror
            </div>
        @endif
    </div>

    <div class="flex gap-2 justify-end">
        <flux:button variant="ghost" wire:click="closeWorkerAssignmentModal">Cancel</flux:button>
        @if(!empty($availableWorkers))
            <flux:button variant="primary" wire:click="saveWorkerAssignments">
                <flux:icon.check class="size-4" />
                Save Assignments
            </flux:button>
        @endif
    </div>
</flux:modal>

<!-- Contractor Assignment Modal -->
<flux:modal name="contractor-assignment-modal" wire:model="showContractorAssignmentModal" class="md:w-3xl space-y-6">
    <div>
        <flux:heading size="lg">Assign Contractors</flux:heading>
        <flux:subheading>{{ $selectedTemplateName }}</flux:subheading>
    </div>

    <div class="space-y-4">
        <div class="p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
            <p class="text-sm text-blue-800 dark:text-blue-200">
                <flux:icon.information-circle class="size-4 inline mr-1" />
                Select contractors that should have this deduction applied to all their workers.
                You can enable or disable for multiple contractors at once.
            </p>
        </div>

        <!-- Contractors List with Checkboxes -->
        @if(count($allContractors) > 0)
            <div class="border border-zinc-200 dark:border-zinc-700 rounded-lg overflow-hidden">
                <div class="max-h-[400px] overflow-y-auto">
                    <table class="w-full">
                        <thead class="bg-zinc-50 dark:bg-zinc-800 sticky top-0 z-10">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">
                                    <input
                                        type="checkbox"
                                        wire:model.live="selectAllContractors"
                                        class="rounded border-zinc-300 dark:border-zinc-600"
                                    >
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Contractor Name</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">CLAB No</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                            @foreach($allContractors as $contractor)
                                <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800">
                                    <td class="px-4 py-3">
                                        <input
                                            type="checkbox"
                                            wire:model="selectedContractorIds"
                                            value="{{ $contractor->id }}"
                                            class="rounded border-zinc-300 dark:border-zinc-600"
                                        >
                                    </td>
                                    <td class="px-4 py-3 text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                        {{ $contractor->contractor_name }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-zinc-600 dark:text-zinc-400">
                                        {{ $contractor->contractor_clab_no }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="text-sm text-zinc-600 dark:text-zinc-400">
                {{ count($selectedContractorIds) }} of {{ count($allContractors) }} contractors selected
            </div>
        @else
            <p class="text-sm text-zinc-600 dark:text-zinc-400 text-center py-4">
                No contractors found in the system.
            </p>
        @endif
    </div>

    <div class="flex gap-2 justify-end">
        <flux:button variant="ghost" wire:click="closeContractorAssignmentModal">Cancel</flux:button>
        <flux:button variant="primary" wire:click="saveContractorAssignments">
            <flux:icon.check class="size-4" />
            Save Assignments ({{ count($selectedContractorIds) }})
        </flux:button>
    </div>
</flux:modal>
