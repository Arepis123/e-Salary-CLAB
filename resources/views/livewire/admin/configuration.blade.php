<div class="flex h-full w-full flex-1 flex-col gap-6">
    <!-- Page Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">Configuration</h1>
            <p class="text-sm text-zinc-600 dark:text-zinc-400">
                Manage system configuration and contractor settings
            </p>
        </div>

        <!-- Payment Sync Button -->
        <div>
            <flux:button
                wire:click="syncAllPendingPayments"
                variant="primary"
                :disabled="$isSyncingPayments"
                icon="arrow-path"
                class="{{ $isSyncingPayments ? 'animate-spin' : '' }}"
            >                
                {{ $isSyncingPayments ? 'Syncing...' : 'Sync Pending Payments' }}
            </flux:button>
        </div>
    </div>

    <!-- Sync Results -->
    @if(count($syncResults) > 0)
        <flux:card class="p-6 dark:bg-zinc-900 rounded-lg">
            <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100 mb-4">Payment Sync Results</h3>
            <div class="space-y-2 max-h-96 overflow-y-auto">
                @foreach($syncResults as $result)
                    <div class="flex items-start gap-3 p-3 rounded-lg border
                        @if($result['status'] === 'success') border-green-200 bg-green-50 dark:bg-green-900/20 dark:border-green-800
                        @elseif($result['status'] === 'error') border-red-200 bg-red-50 dark:bg-red-900/20 dark:border-red-800
                        @else border-yellow-200 bg-yellow-50 dark:bg-yellow-900/20 dark:border-yellow-800
                        @endif">
                        @if($result['status'] === 'success')
                            <flux:icon.check-circle class="size-5 text-green-600 dark:text-green-400 flex-shrink-0" />
                        @elseif($result['status'] === 'error')
                            <flux:icon.x-circle class="size-5 text-red-600 dark:text-red-400 flex-shrink-0" />
                        @else
                            <flux:icon.clock class="size-5 text-yellow-600 dark:text-yellow-400 flex-shrink-0" />
                        @endif
                        <div class="flex-1 text-sm">
                            <div class="font-medium
                                @if($result['status'] === 'success') text-green-900 dark:text-green-100
                                @elseif($result['status'] === 'error') text-red-900 dark:text-red-100
                                @else text-yellow-900 dark:text-yellow-100
                                @endif">
                                {{ $result['message'] }}
                            </div>
                            <div class="text-xs mt-1
                                @if($result['status'] === 'success') text-green-700 dark:text-green-300
                                @elseif($result['status'] === 'error') text-red-700 dark:text-red-300
                                @else text-yellow-700 dark:text-yellow-300
                                @endif">
                                Payment ID: {{ $result['payment_id'] }} • Bill ID: {{ $result['bill_id'] }}
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="mt-4 flex justify-end">
                <flux:button wire:click="$set('syncResults', [])" variant="ghost" size="sm">
                    Clear Results
                </flux:button>
            </div>
        </flux:card>
    @endif

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
