<div class="flex h-full w-full flex-1 flex-col gap-6">
    <!-- Page Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">Configuration</h1>
            <p class="text-sm text-zinc-600 dark:text-zinc-400">
                Manage system configuration and contractor settings
            </p>
        </div>
    </div>

    <!-- Tab Navigation -->
    <div class="border-b border-zinc-200 dark:border-zinc-700">
        <nav class="flex space-x-8">
            <button
                wire:click="switchTab('salary')"
                class="py-4 px-1 border-b-2 font-medium text-sm {{ $activeTab === 'salary' ? 'border-blue-500 text-blue-600 dark:text-blue-400' : 'border-transparent text-zinc-500 hover:text-zinc-700 hover:border-zinc-300 dark:text-zinc-400 dark:hover:text-zinc-300' }}"
            >
                <flux:icon.currency-dollar class="size-5 inline mr-2" />
                Basic Salary
            </button>
            <button
                wire:click="switchTab('windows')"
                class="py-4 px-1 border-b-2 font-medium text-sm {{ $activeTab === 'windows' ? 'border-blue-500 text-blue-600 dark:text-blue-400' : 'border-transparent text-zinc-500 hover:text-zinc-700 hover:border-zinc-300 dark:text-zinc-400 dark:hover:text-zinc-300' }}"
            >
                <flux:icon.clock class="size-5 inline mr-2" />
                OT Entry Windows
            </button>
            <button
                wire:click="switchTab('contractor-settings')"
                class="py-4 px-1 border-b-2 font-medium text-sm {{ $activeTab === 'contractor-settings' ? 'border-blue-500 text-blue-600 dark:text-blue-400' : 'border-transparent text-zinc-500 hover:text-zinc-700 hover:border-zinc-300 dark:text-zinc-400 dark:hover:text-zinc-300' }}"
            >
                <flux:icon.cog class="size-5 inline mr-2" />
                Contractor Settings
            </button>
        </nav>
    </div>

    <!-- Tab Content -->
    @if($activeTab === 'salary')
        @include('livewire.admin.configuration-salary')
    @endif

    @if($activeTab === 'windows')
        @include('livewire.admin.configuration-windows')
    @endif

    @if($activeTab === 'contractor-settings')
        @include('livewire.admin.configuration-contractor-settings')
    @endif

    <!-- Window Action Modal -->
    <flux:modal name="window-action" class="md:w-96 space-y-6" wire:model="showWindowModal">
        <div>
            <flux:heading size="lg">
                {{ $windowAction === 'open' ? 'Open' : 'Close' }} Window
            </flux:heading>
            <flux:subheading>
                {{ $selectedContractorName }} ({{ $selectedContractorClab }})
            </flux:subheading>
        </div>

        @if($windowAction === 'open')
            <div class="rounded-lg bg-blue-50 dark:bg-blue-950 p-4 border border-blue-200 dark:border-blue-800">
                <div class="flex gap-3">
                    <flux:icon.information-circle class="size-5 text-blue-600 dark:text-blue-400 flex-shrink-0 mt-0.5" />
                    <div class="text-sm text-blue-900 dark:text-blue-100">
                        <strong>Opening window will:</strong>
                        <ul class="list-disc ml-5 mt-2">
                            <li>Allow OT entry and transaction submissions</li>
                            <li>Automatically unlock any locked entries for current period</li>
                            <li>Override default 1-15 date restriction</li>
                        </ul>
                    </div>
                </div>
            </div>
        @else
            <div class="rounded-lg bg-yellow-50 dark:bg-yellow-950 p-4 border border-yellow-200 dark:border-yellow-800">
                <div class="flex gap-3">
                    <flux:icon.exclamation-triangle class="size-5 text-yellow-600 dark:text-yellow-400 flex-shrink-0 mt-0.5" />
                    <div class="text-sm text-yellow-900 dark:text-yellow-100">
                        <strong>Closing window will:</strong>
                        <ul class="list-disc ml-5 mt-2">
                            <li>Prevent OT entry and transaction submissions</li>
                            <li>Entries will remain in current state (not auto-locked)</li>
                            <li>Window can be reopened at any time</li>
                        </ul>
                    </div>
                </div>
            </div>
        @endif

        <flux:textarea
            wire:model="windowRemarks"
            label="Remarks (Optional)"
            rows="3"
            placeholder="Reason for {{ $windowAction === 'open' ? 'opening' : 'closing' }} window..."
        />

        <div class="flex gap-2 justify-end">
            <flux:button variant="ghost" wire:click="closeWindowModal">Cancel</flux:button>
            <flux:button
                variant="{{ $windowAction === 'open' ? 'primary' : 'danger' }}"
                wire:click="confirmWindowAction"
            >
                Confirm {{ $windowAction === 'open' ? 'Open' : 'Close' }}
            </flux:button>
        </div>
    </flux:modal>

    <!-- History Modal -->
    <flux:modal name="window-history" class="md:w-2xl space-y-6" wire:model="showHistoryModal">
        <div>
            <flux:heading size="lg">Window Change History</flux:heading>
            <flux:subheading>
                {{ $selectedContractorName }} ({{ $selectedContractorClab }})
            </flux:subheading>
        </div>

        <div class="max-h-96 overflow-y-auto">
            <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                <thead class="bg-zinc-50 dark:bg-zinc-800">
                    <tr>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase">Date</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase">Action</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase">Changed By</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase">Remarks</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-zinc-900 divide-y divide-zinc-200 dark:divide-zinc-800">
                    @forelse($contractorHistory as $log)
                        <tr>
                            <td class="px-4 py-4 whitespace-nowrap text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                {{ $log->created_at->format('d M Y H:i') }}
                            </td>
                            <td class="px-4 py-4 whitespace-nowrap">
                                <flux:badge
                                    size="sm"
                                    color="{{ $log->action === 'opened' ? 'green' : 'red' }}"
                                >
                                    {{ ucfirst($log->action) }}
                                </flux:badge>
                            </td>
                            <td class="px-4 py-4 whitespace-nowrap text-sm text-zinc-900 dark:text-zinc-100">
                                {{ $log->changedBy->name ?? 'Unknown User' }}
                            </td>
                            <td class="px-4 py-4 text-sm text-zinc-900 dark:text-zinc-100">
                                {{ $log->remarks ?: '-' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-8 text-center">
                                <p class="text-sm text-zinc-600">No history yet</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="flex justify-end">
            <flux:button variant="ghost" wire:click="closeHistoryModal">Close</flux:button>
        </div>
    </flux:modal>
</div>
