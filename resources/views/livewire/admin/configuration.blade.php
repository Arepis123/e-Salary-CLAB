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
        <nav class="flex space-x-2 sm:space-x-8">
            <button
                wire:click="switchTab('contractor-settings')"
                class="py-4 px-2 sm:px-1 border-b-2 font-medium text-sm {{ $activeTab === 'contractor-settings' ? 'border-blue-500 text-blue-600 dark:text-blue-400' : 'border-transparent text-zinc-500 hover:text-zinc-700 hover:border-zinc-300 dark:text-zinc-400 dark:hover:text-zinc-300' }}"
            >
                <flux:icon.building-office class="size-5 inline sm:mr-2" />
                <span class="hidden sm:inline">Contractors</span>
            </button>
            <button
                wire:click="switchTab('workers')"
                class="py-4 px-2 sm:px-1 border-b-2 font-medium text-sm {{ $activeTab === 'workers' ? 'border-blue-500 text-blue-600 dark:text-blue-400' : 'border-transparent text-zinc-500 hover:text-zinc-700 hover:border-zinc-300 dark:text-zinc-400 dark:hover:text-zinc-300' }}"
            >
                <flux:icon.users class="size-5 inline sm:mr-2" />
                <span class="hidden sm:inline">Workers</span>
            </button>
            <button
                wire:click="switchTab('pic')"
                class="py-4 px-2 sm:px-1 border-b-2 font-medium text-sm {{ $activeTab === 'pic' ? 'border-blue-500 text-blue-600 dark:text-blue-400' : 'border-transparent text-zinc-500 hover:text-zinc-700 hover:border-zinc-300 dark:text-zinc-400 dark:hover:text-zinc-300' }}"
            >
                <flux:icon.user-group class="size-5 inline sm:mr-2" />
                <span class="hidden sm:inline">PIC Assignment</span>
            </button>
            <button
                wire:click="switchTab('payments')"
                class="py-4 px-2 sm:px-1 border-b-2 font-medium text-sm {{ $activeTab === 'payments' ? 'border-blue-500 text-blue-600 dark:text-blue-400' : 'border-transparent text-zinc-500 hover:text-zinc-700 hover:border-zinc-300 dark:text-zinc-400 dark:hover:text-zinc-300' }}"
            >
                <flux:icon.credit-card class="size-5 inline sm:mr-2" />
                <span class="hidden sm:inline">Payments</span>
            </button>
            <button
                wire:click="switchTab('uploads')"
                class="py-4 px-2 sm:px-1 border-b-2 font-medium text-sm {{ $activeTab === 'uploads' ? 'border-blue-500 text-blue-600 dark:text-blue-400' : 'border-transparent text-zinc-500 hover:text-zinc-700 hover:border-zinc-300 dark:text-zinc-400 dark:hover:text-zinc-300' }}"
            >
                <flux:icon.document-arrow-up class="size-5 inline sm:mr-2" />
                <span class="hidden sm:inline">Uploads</span>
            </button>
        </nav>
    </div>

    <!-- Tab Content -->
    @if($activeTab === 'contractor-settings')
        @include('livewire.admin.configuration-contractor-settings')
    @endif

    @if($activeTab === 'pic')
        @include('livewire.admin.configuration-pic')
    @endif

    @if($activeTab === 'workers')
        @include('livewire.admin.configuration-workers')
    @endif

    @if($activeTab === 'payments')
        @include('livewire.admin.configuration-payments')
    @endif

    @if($activeTab === 'uploads')
        @include('livewire.admin.configuration-uploads')
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

</div>
