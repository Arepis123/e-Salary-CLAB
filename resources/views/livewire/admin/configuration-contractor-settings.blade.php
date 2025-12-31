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
                        <span class="text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Amount (RM)</span>
                    </flux:table.column>
                    <flux:table.column>
                        <span class="text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Apply in Months</span>
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
                                <span class="font-semibold text-blue-600 dark:text-blue-400">
                                    {{ number_format($template->amount, 2) }}
                                </span>
                            </flux:table.cell>

                            <flux:table.cell variant="strong">
                                @php
                                    $monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                                    $months = collect($template->apply_months)->map(fn($m) => $monthNames[$m - 1])->join(', ');
                                @endphp
                                <span class="text-sm text-zinc-900 dark:text-zinc-100">{{ $months }}</span>
                            </flux:table.cell>

                            <flux:table.cell variant="strong">
                                <flux:badge color="{{ $template->is_active ? 'green' : 'zinc' }}" size="sm">
                                    {{ $template->is_active ? 'Active' : 'Inactive' }}
                                </flux:badge>
                            </flux:table.cell>

                            <flux:table.cell>
                                <div class="flex justify-center gap-2">
                                    <flux:button
                                        size="sm"
                                        variant="ghost"
                                        wire:click="openTemplateModal({{ $template->id }})"
                                        icon="pencil"
                                        icon-variant="outline"
                                    >
                                        Edit
                                    </flux:button>
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
                        <flux:table.cell colspan="5">
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
                    @foreach($deductionTemplates as $template)
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
                                        $months = collect($template->apply_months)->map(fn($m) => $monthNames[$m - 1])->join(', ');
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
            <flux:label>Apply in Months</flux:label>
            <flux:description>Select one or more months when this deduction should be automatically applied</flux:description>

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
