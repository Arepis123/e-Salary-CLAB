<!-- Payment Sync Tools -->
<div class="space-y-6">
    <!-- Section Header -->
    <div>
        <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">Payment Synchronization Tools</h2>
        <p class="text-sm text-zinc-600 dark:text-zinc-400 mt-1">
            Tools to sync payment statuses with Billplz and fix data inconsistencies.
        </p>
    </div>

    <!-- Sync Pending Payments Card -->
    <flux:card class="p-6 dark:bg-zinc-900 rounded-lg">
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
            <div class="flex-1">
                <div class="flex items-center gap-3">
                    <div class="rounded-full bg-blue-100 dark:bg-blue-900/30 p-3">
                        <flux:icon.arrow-path class="size-6 text-blue-600 dark:text-blue-400" />
                    </div>
                    <div>
                        <h3 class="text-base font-semibold text-zinc-900 dark:text-zinc-100">Sync Pending Payments</h3>
                        <p class="text-sm text-zinc-600 dark:text-zinc-400">
                            Check all pending payments with Billplz API to update their status if they have been paid.
                        </p>
                    </div>
                </div>
            </div>
            <flux:button
                wire:click="syncAllPendingPayments"
                variant="primary"
                :disabled="$isSyncingPayments"
                icon="arrow-path"
                wire:loading.attr="disabled"
                wire:target="syncAllPendingPayments"
            >
                <span wire:loading.remove wire:target="syncAllPendingPayments">Sync Pending Payments</span>
                <span wire:loading wire:target="syncAllPendingPayments">Syncing...</span>
            </flux:button>
        </div>
    </flux:card>

    <!-- Fix Missing Receipts Card -->
    <flux:card class="p-6 dark:bg-zinc-900 rounded-lg">
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
            <div class="flex-1">
                <div class="flex items-center gap-3">
                    <div class="rounded-full bg-green-100 dark:bg-green-900/30 p-3">
                        <flux:icon.document-check class="size-6 text-green-600 dark:text-green-400" />
                    </div>
                    <div>
                        <h3 class="text-base font-semibold text-zinc-900 dark:text-zinc-100">Fix Missing Receipts</h3>
                        <p class="text-sm text-zinc-600 dark:text-zinc-400">
                            Generate missing receipt numbers, paid dates, and transaction IDs for paid submissions.
                        </p>
                    </div>
                </div>
            </div>
            <flux:button
                wire:click="fixMissingReceipts"
                variant="primary"
                icon="document-check"
                wire:loading.attr="disabled"
                wire:target="fixMissingReceipts"
            >
                <span wire:loading.remove wire:target="fixMissingReceipts">Fix Missing Receipts</span>
                <span wire:loading wire:target="fixMissingReceipts">Fixing...</span>
            </flux:button>
        </div>
    </flux:card>

    <!-- Sync Cancelled Payments Card -->
    <flux:card class="p-6 dark:bg-zinc-900 rounded-lg">
        <div class="flex flex-col gap-4">
            <div class="flex items-center gap-3">
                <div class="rounded-full bg-red-100 dark:bg-red-900/30 p-3">
                    <flux:icon.x-circle class="size-6 text-red-600 dark:text-red-400" />
                </div>
                <div>
                    <h3 class="text-base font-semibold text-zinc-900 dark:text-zinc-100">Sync Cancelled Payments</h3>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">
                        Check if any cancelled payments were actually paid (e.g., late bank B2B approval after system timeout).
                    </p>
                </div>
            </div>

            <div class="flex flex-col sm:flex-row items-start sm:items-end gap-4 pl-14">
                <div class="flex gap-3">
                    <div class="w-32">
                        <flux:select wire:model="cancelledSyncMonth" label="Month" size="sm">
                            <flux:select.option value="1">January</flux:select.option>
                            <flux:select.option value="2">February</flux:select.option>
                            <flux:select.option value="3">March</flux:select.option>
                            <flux:select.option value="4">April</flux:select.option>
                            <flux:select.option value="5">May</flux:select.option>
                            <flux:select.option value="6">June</flux:select.option>
                            <flux:select.option value="7">July</flux:select.option>
                            <flux:select.option value="8">August</flux:select.option>
                            <flux:select.option value="9">September</flux:select.option>
                            <flux:select.option value="10">October</flux:select.option>
                            <flux:select.option value="11">November</flux:select.option>
                            <flux:select.option value="12">December</flux:select.option>
                        </flux:select>
                    </div>
                    <div class="w-28">
                        <flux:select wire:model="cancelledSyncYear" label="Year" size="sm" variant="listbox">
                            @for($y = now()->year; $y >= now()->year - 2; $y--)
                                <flux:select.option value="{{ $y }}">{{ $y }}</flux:select.option>
                            @endfor
                        </flux:select>
                    </div>
                </div>
                <flux:button
                    wire:click="syncCancelledPayments"
                    variant="primary"
                    :disabled="$isSyncingCancelledPayments"
                    icon="arrow-path"
                    wire:loading.attr="disabled"
                    wire:target="syncCancelledPayments"
                >
                    <span wire:loading.remove wire:target="syncCancelledPayments">Sync Cancelled Payments</span>
                    <span wire:loading wire:target="syncCancelledPayments">Checking...</span>
                </flux:button>
            </div>
        </div>
    </flux:card>

    <!-- Pending Payment Sync Results -->
    @if(count($syncResults) > 0)
        <flux:card class="p-6 dark:bg-zinc-900 rounded-lg">
            <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100 mb-4">Pending Payment Sync Results</h3>
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
                                Payment ID: {{ $result['payment_id'] }} &bull; Bill ID: {{ $result['bill_id'] }}
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

    <!-- Cancelled Sync Results -->
    @if(count($cancelledSyncResults) > 0)
        <flux:card class="p-6 dark:bg-zinc-900 rounded-lg">
            <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100 mb-4">Cancelled Payment Sync Results</h3>
            <div class="space-y-2 max-h-96 overflow-y-auto">
                @foreach($cancelledSyncResults as $result)
                    <div class="flex items-start gap-3 p-3 rounded-lg border
                        @if($result['status'] === 'success') border-green-200 bg-green-50 dark:bg-green-900/20 dark:border-green-800
                        @elseif($result['status'] === 'error') border-red-200 bg-red-50 dark:bg-red-900/20 dark:border-red-800
                        @else border-zinc-200 bg-zinc-50 dark:bg-zinc-800/20 dark:border-zinc-700
                        @endif">
                        @if($result['status'] === 'success')
                            <flux:icon.check-circle class="size-5 text-green-600 dark:text-green-400 flex-shrink-0" />
                        @elseif($result['status'] === 'error')
                            <flux:icon.x-circle class="size-5 text-red-600 dark:text-red-400 flex-shrink-0" />
                        @else
                            <flux:icon.minus-circle class="size-5 text-zinc-500 dark:text-zinc-400 flex-shrink-0" />
                        @endif
                        <div class="flex-1 text-sm">
                            <div class="font-medium
                                @if($result['status'] === 'success') text-green-900 dark:text-green-100
                                @elseif($result['status'] === 'error') text-red-900 dark:text-red-100
                                @else text-zinc-900 dark:text-zinc-100
                                @endif">
                                {{ $result['message'] }}
                            </div>
                            <div class="text-xs mt-1
                                @if($result['status'] === 'success') text-green-700 dark:text-green-300
                                @elseif($result['status'] === 'error') text-red-700 dark:text-red-300
                                @else text-zinc-600 dark:text-zinc-400
                                @endif">
                                Payment ID: {{ $result['payment_id'] }} &bull; Bill ID: {{ $result['bill_id'] }}
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="mt-4 flex justify-end">
                <flux:button wire:click="$set('cancelledSyncResults', [])" variant="ghost" size="sm">
                    Clear Results
                </flux:button>
            </div>
        </flux:card>
    @endif
</div>
