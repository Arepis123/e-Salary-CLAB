<div class="flex h-full w-full flex-1 flex-col gap-6">
        <!-- Page Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">OT & Transaction Entry</h1>
                <p class="text-sm text-zinc-600 dark:text-zinc-400">
                    Enter overtime hours for {{ $period['entry_month_name'] ?? 'previous month' }}
                </p>
            </div>
            <div class="flex gap-2">
                <x-tutorial-button page="ot-entry" />
                @if($isWithinWindow && !$hasSubmitted)
                    <flux:button id="download-template-btn" wire:click="downloadTemplate" variant="outline" icon="arrow-down-tray" size="sm">
                        Download Template
                    </flux:button>
                    <flux:button id="import-file-btn" wire:click="openImportModal" variant="filled" icon="arrow-up-tray" size="sm">
                        Import from File
                    </flux:button>
                @endif
            </div>
        </div>

        <!-- Entry Window Status Card -->
        <flux:card id="entry-window-status" class="p-6 dark:bg-zinc-900 rounded-lg">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    @if($isWithinWindow)
                        <flux:icon.check-circle class="size-12 text-green-600 dark:text-green-400" />
                        <div>
                            <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">Entry Window OPEN</h3>
                            <p class="text-sm text-zinc-600 dark:text-zinc-400 mt-1">
                                You can enter OT hours for <strong>{{ $period['entry_month_name'] }}</strong>
                            </p>
                            <p class="text-xs text-zinc-500 dark:text-zinc-500 mt-1">
                                Window closes on {{ $period['window_end']->format('F d, Y') }} at 11:59 PM
                            </p>
                        </div>
                    @else
                        <flux:icon.x-circle class="size-12 text-red-600 dark:text-red-400" />
                        <div>
                            <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">Entry Window CLOSED</h3>
                            <p class="text-sm text-zinc-600 dark:text-zinc-400 mt-1">
                                OT entry window is only open from 1st to 15th of each month
                            </p>
                            <p class="text-xs text-zinc-500 dark:text-zinc-500 mt-1">
                                Next window opens: {{ now()->addMonth()->startOfMonth()->format('F 1, Y') }}
                            </p>
                        </div>
                    @endif
                </div>

                @if($isWithinWindow)
                    <div class="text-right">
                        <div class="text-3xl font-bold text-green-600 dark:text-green-400">
                            {{ $period['days_remaining'] }}
                        </div>
                        <div class="text-xs text-green-600 dark:text-green-400 uppercase">
                            Days Remaining
                        </div>
                    </div>
                @endif
            </div>
        </flux:card>

        <!-- Submission Status -->
        @if($hasSubmitted)
            <flux:callout icon="check-circle" color="emerald">
                <flux:callout.heading>Entries Submitted</flux:callout.heading>
                <flux:callout.text>
                    <p>
                        Your OT entries for <strong>{{ $period['entry_month_name'] }}</strong> have been submitted successfully.
                        These entries are now locked and will be automatically included in your <strong>{{ $period['submission_month_name'] }}</strong> payroll.
                    </p>
                </flux:callout.text>
            </flux:callout>
        @endif

        <!-- OT Entry Form -->
        <flux:card id="ot-entry-table" class="p-6 dark:bg-zinc-900 rounded-lg">
            <div class="mb-4">
                <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">
                    Overtime Hours for {{ $period['entry_month_name'] }}
                </h3>
            </div>

            @if(count($entries) === 0)
                <div class="text-center py-12">
                    <flux:icon.users class="size-16 mx-auto text-zinc-400 dark:text-zinc-600 mb-4" />
                    <p class="text-zinc-600 dark:text-zinc-400">No workers found</p>
                    <p class="text-sm text-zinc-500 dark:text-zinc-500 mt-2">
                        Please add workers to your account first
                    </p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <flux:table>
                        <flux:table.columns>
                            <flux:table.column align="center">
                                <span class="text-xs font-medium text-zinc-600 dark:text-zinc-400">No</span>
                            </flux:table.column>
                            <flux:table.column>
                                <span class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Worker ID</span>
                            </flux:table.column>
                            <flux:table.column>
                                <span class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Worker Name</span>
                            </flux:table.column>
                            <flux:table.column>
                                <span class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Passport</span>
                            </flux:table.column>
                            <flux:table.column align="center">
                                <span class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Weekday OT<br>(Hours)</span>
                            </flux:table.column>
                            <flux:table.column align="center">
                                <span class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Rest Day OT<br>(Hours)</span>
                            </flux:table.column>
                            <flux:table.column align="center">
                                <span class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Public Holiday OT<br>(Hours)</span>
                            </flux:table.column>
                            <flux:table.column>
                                <span class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Transactions</span>
                            </flux:table.column>
                            <flux:table.column align="center">
                                <span class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Actions</span>
                            </flux:table.column>
                            <flux:table.column align="center">
                                <span class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Status</span>
                            </flux:table.column>
                        </flux:table.columns>

                        <flux:table.rows>
                            @foreach($entries as $index => $entry)
                                <flux:table.rows :key="$entry['id']">
                                    <flux:table.cell align="center">{{ $index + 1 }}</flux:table.cell>

                                    <flux:table.cell variant="strong">
                                        {{ $entry['worker_id'] }}
                                    </flux:table.cell>

                                    <flux:table.cell variant="strong">
                                        {{ $entry['worker_name'] }}
                                    </flux:table.cell>

                                    <flux:table.cell variant="strong">
                                        {{ $entry['worker_passport'] }}
                                    </flux:table.cell>

                                    <!-- Weekday OT Hours Input -->
                                    <flux:table.cell align="center">
                                        @if($entry['is_locked'] || !$isWithinWindow)
                                            <span class="font-medium text-zinc-900 dark:text-zinc-100">
                                                {{ number_format($entry['ot_normal_hours'], 2) }}
                                            </span>
                                        @else
                                            <flux:input
                                                type="number"
                                                step="0.01"
                                                min="0"
                                                wire:model="entries.{{ $index }}.ot_normal_hours"
                                                class="w-24 text-center"
                                            />
                                        @endif
                                    </flux:table.cell>

                                    <!-- Rest Day OT Hours Input -->
                                    <flux:table.cell align="center">
                                        @if($entry['is_locked'] || !$isWithinWindow)
                                            <span class="font-medium text-zinc-900 dark:text-zinc-100">
                                                {{ number_format($entry['ot_rest_hours'], 2) }}
                                            </span>
                                        @else
                                            <flux:input
                                                type="number"
                                                step="0.01"
                                                min="0"
                                                wire:model="entries.{{ $index }}.ot_rest_hours"
                                                class="w-24 text-center"
                                            />
                                        @endif
                                    </flux:table.cell>

                                    <!-- Public Holiday OT Hours Input -->
                                    <flux:table.cell align="center">
                                        @if($entry['is_locked'] || !$isWithinWindow)
                                            <span class="font-medium text-zinc-900 dark:text-zinc-100">
                                                {{ number_format($entry['ot_public_hours'], 2) }}
                                            </span>
                                        @else
                                            <flux:input
                                                type="number"
                                                step="0.01"
                                                min="0"
                                                wire:model="entries.{{ $index }}.ot_public_hours"
                                                class="w-24 text-center"
                                            />
                                        @endif
                                    </flux:table.cell>

                                    <!-- Transactions -->
                                    <flux:table.cell>
                                        @php
                                            $transactions = $entry['transactions'] ?? [];
                                        @endphp
                                        @if(count($transactions) > 0)
                                            <div class="space-y-1">
                                                @foreach($transactions as $txn)
                                                    <div class="text-xs text-zinc-900 dark:text-zinc-100">
                                                        @if($txn['type'] === 'allowance')
                                                            +RM {{ number_format($txn['amount'], 2) }}
                                                        @elseif($txn['type'] === 'npl')
                                                            {{ $txn['amount'] }} {{ $txn['amount'] == 1 ? 'day' : 'days' }} (NPL)
                                                        @else
                                                            -RM {{ number_format($txn['amount'], 2) }}
                                                        @endif
                                                    </div>
                                                @endforeach
                                            </div>
                                        @else
                                            <span class="text-sm text-zinc-400 dark:text-zinc-600">-</span>
                                        @endif
                                    </flux:table.cell>

                                    <!-- Actions -->
                                    <flux:table.cell align="center">
                                        @if(!$entry['is_locked'] && $isWithinWindow)
                                            <flux:button
                                                wire:click="openTransactionModal({{ $index }})"
                                                variant="filled"
                                                size="sm"
                                            >
                                                Manage
                                            </flux:button>
                                        @else
                                            <span class="text-xs text-zinc-400 dark:text-zinc-600">Locked</span>
                                        @endif
                                    </flux:table.cell>

                                    <!-- Status Badge -->
                                    <flux:table.cell align="center">
                                        @if($entry['status'] === 'draft')
                                            <flux:badge color="zinc" size="sm">Draft</flux:badge>
                                        @elseif($entry['status'] === 'submitted')
                                            <flux:badge color="blue" size="sm">Submitted</flux:badge>
                                        @elseif($entry['status'] === 'locked')
                                            <flux:badge color="green" size="sm">Locked</flux:badge>
                                        @endif
                                    </flux:table.cell>
                                </flux:table.rows>
                            @endforeach
                        </flux:table.rows>
                    </flux:table>
                </div>

                <!-- Bottom Actions -->
                @if(!$hasSubmitted && $isWithinWindow && count($entries) > 0)
                    <div id="ot-entry-actions" class="mt-6 flex justify-end gap-2 border-t border-zinc-200 dark:border-zinc-700 pt-4">
                        <flux:button id="save-draft-btn" wire:click="saveDraft" variant="outline" icon="document-text">
                            Save Draft
                        </flux:button>
                        <flux:button id="submit-entries-btn" wire:click="submitEntries" variant="primary" icon="paper-airplane">
                            Submit All Entries
                        </flux:button>
                    </div>
                @endif
            @endif
        </flux:card>

        <!-- Help Information -->
        <flux:callout icon="information-circle" color="blue">
            <flux:callout.heading>Important Information</flux:callout.heading>
            <flux:callout.text>
                <ul class="list-disc list-inside space-y-1 text-sm">
                    <li>OT and transaction entries can only be made between the <strong>1st and 15th</strong> of each month</li>
                    <li>Entries are for the <strong>previous month's</strong> overtime hours and transactions</li>
                    <li>You can save as draft multiple times before submitting</li>
                    <li>Once submitted, entries will be <strong>locked</strong> and automatically included in your next payroll</li>
                    <li>After the 15th, all entries are automatically locked</li>
                </ul>
            </flux:callout.text>
        </flux:callout>

        <!-- Transaction Management Modal -->
        @if($showTransactionModal && $currentWorkerIndex !== null)
            <flux:modal wire:model="showTransactionModal" class="min-w-[600px]">
                <div class="space-y-6">
                    <div>
                        <h2 class="text-xl font-bold text-zinc-900 dark:text-zinc-100">
                            Manage Transactions
                        </h2>
                        <p class="text-sm text-zinc-600 dark:text-zinc-400 mt-1">
                            Worker: {{ $entries[$currentWorkerIndex]['worker_name'] ?? 'Unknown' }}
                        </p>
                        <p class="text-sm text-zinc-600 dark:text-zinc-400 mt-0">
                            Passport: {{ $entries[$currentWorkerIndex]['worker_passport'] ?? 'Unknown' }}
                        </p>
                    </div>

                    <!-- Add New Transaction Form -->
                    <flux:card class="p-4 bg-zinc-50 dark:bg-zinc-800">
                        <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100 mb-3">Add New Transaction</h3>
                        <div class="grid grid-cols-1 gap-4">
                            <!-- Category Selection -->
                            <div>
                                <flux:select wire:model.live="newTransactionCategory" variant="listbox" label="Category">
                                    <flux:select.option value="deduction">Deduction</flux:select.option>
                                    <flux:select.option value="earning">Earning</flux:select.option>
                                </flux:select>
                                @error('newTransactionCategory') <span class="text-xs text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                            </div>

                            <!-- Type Selection (based on category) -->
                            <div>
                                <flux:select wire:model.live="newTransactionType" variant="listbox" label="Type">
                                    @if($newTransactionCategory === 'deduction')
                                        <flux:select.option value="advance_payment">Advance Payment</flux:select.option>
                                        <flux:select.option value="deduction">Other Deduction</flux:select.option>
                                        <flux:select.option value="npl">No-Pay Leave (NPL)</flux:select.option>
                                    @else
                                        <flux:select.option value="allowance">Allowance</flux:select.option>
                                    @endif
                                </flux:select>
                                @error('newTransactionType') <span class="text-xs text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                            </div>

                            @if($newTransactionType === 'npl')
                                <!-- NPL Days Input -->
                                <div>
                                    <flux:input wire:model.live="newTransactionAmount" type="number" step="0.5" min="0" label="No-Pay Leave Days" placeholder="0.0" />
                                    @error('newTransactionAmount') <span class="text-xs text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                                </div>
                            @else
                                <!-- Amount Input -->
                                <div>
                                    <flux:input wire:model.live="newTransactionAmount" type="number" step="0.01" min="0" label="Amount (RM)" placeholder="0.00" />
                                    @error('newTransactionAmount') <span class="text-xs text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                                </div>
                            @endif

                            <div>
                                <flux:textarea wire:model.live="newTransactionRemarks" label="Remarks" placeholder="Enter reason for this transaction..." rows="2" />
                                @error('newTransactionRemarks') <span class="text-xs text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                            </div>

                            <div>
                                <flux:button wire:click="addTransaction" variant="primary" size="sm">
                                    Add Transaction
                                </flux:button>
                            </div>
                        </div>
                    </flux:card>

                    <!-- Transaction List -->
                    @php
                        $currentTransactions = $currentWorkerIndex !== null ? ($entries[$currentWorkerIndex]['transactions'] ?? []) : [];
                    @endphp
                    <div class="space-y-2">
                        <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Transactions ({{ count($currentTransactions) }})</h3>

                        @if(count($currentTransactions) > 0)
                            <div class="space-y-2 max-h-64 overflow-y-auto">
                                @foreach($currentTransactions as $index => $transaction)
                                    <div class="flex items-start justify-between p-3 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-lg">
                                        <div class="flex-1">
                                            <div class="flex items-center gap-2">
                                                @if($transaction['type'] === 'allowance')
                                                    <span class="font-semibold text-zinc-900 dark:text-zinc-100">RM {{ number_format($transaction['amount'], 2) }}</span>
                                                @elseif($transaction['type'] === 'npl')
                                                    <span class="font-semibold text-zinc-900 dark:text-zinc-100">
                                                        {{ $transaction['amount'] }} {{ $transaction['amount'] == 1 ? 'day' : 'days' }}
                                                    </span>
                                                @else
                                                    <span class="font-semibold text-zinc-900 dark:text-zinc-100">RM {{ number_format($transaction['amount'], 2) }}</span>
                                                @endif

                                                @if($transaction['type'] === 'advance_payment')
                                                    <flux:badge color="orange" size="sm">Advance Payment</flux:badge>
                                                @elseif($transaction['type'] === 'deduction')
                                                    <flux:badge color="red" size="sm">Other Deduction</flux:badge>
                                                @elseif($transaction['type'] === 'npl')
                                                    <flux:badge color="purple" size="sm">No-Pay Leave</flux:badge>
                                                @elseif($transaction['type'] === 'allowance')
                                                    <flux:badge color="green" size="sm">Allowance</flux:badge>
                                                @endif
                                            </div>
                                            <p class="text-xs text-zinc-600 dark:text-zinc-400 mt-1">{{ $transaction['remarks'] }}</p>
                                        </div>
                                        <flux:button wire:click="removeTransaction({{ $index }})" variant="ghost" size="sm" class="text-red-600 dark:text-red-400">
                                            <flux:icon.trash class="size-4" />
                                        </flux:button>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="text-center py-8 text-zinc-500 dark:text-zinc-400">
                                <flux:icon.banknotes class="size-12 mx-auto mb-2 text-zinc-300 dark:text-zinc-600" />
                                <p class="text-sm">No transactions added yet</p>
                            </div>
                        @endif
                    </div>

                    <!-- Modal Actions -->
                    <div class="flex justify-end gap-2 pt-4 border-t border-zinc-200 dark:border-zinc-700">
                        <flux:button wire:click="closeTransactionModal" variant="ghost">Cancel</flux:button>
                        <flux:button wire:click="saveTransactions" variant="primary">Save Transactions</flux:button>
                    </div>
                </div>
            </flux:modal>
        @endif

        <!-- Import Modal -->
        @if($showImportModal)
            <flux:modal id="import-modal" wire:model="showImportModal" class="min-w-[800px]">
                <div class="space-y-6">
                    <div id="import-modal-header">
                        <h2 class="text-xl font-bold text-zinc-900 dark:text-zinc-100">
                            Import OT & Transactions
                        </h2>
                        <p class="text-sm text-zinc-600 dark:text-zinc-400 mt-1">
                            Upload an Excel or CSV file to bulk import overtime hours and transactions
                        </p>
                    </div>

                    @if(!$showImportPreview)
                        <!-- Upload Form -->
                        <flux:card class="p-4 bg-zinc-50 dark:bg-zinc-800">
                            <div class="space-y-4">
                                <div id="import-file-input-container">
                                    <flux:input
                                        id="import-file-input"
                                        type="file"
                                        wire:model="importFile"
                                        accept=".xlsx,.xls,.csv"
                                        label="Select File"
                                    />
                                    <p class="text-xs text-zinc-600 dark:text-zinc-400 mt-2">
                                        Supported formats: Excel (.xlsx, .xls) or CSV (.csv). Maximum file size: 2MB
                                    </p>
                                    @error('importFile') <span class="text-xs text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                                </div>

                                <flux:separator />

                                <div id="import-instructions">
                                    <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100 mb-2">Instructions</h3>
                                    <ul class="text-xs text-zinc-600 dark:text-zinc-400 space-y-1 list-disc list-inside">
                                        <li>Download the template file to see the required format</li>
                                        <li>Fill in worker passport, name, OT hours, and transactions</li>
                                        <li>Transaction types: <strong class="text-zinc-900 dark:text-zinc-100">advance_payment</strong>, <strong class="text-zinc-900 dark:text-zinc-100">deduction</strong>, <strong class="text-zinc-900 dark:text-zinc-100">npl</strong>, <strong class="text-zinc-900 dark:text-zinc-100">allowance</strong></li>
                                        <li>You can have multiple rows for the same worker (for multiple transactions)</li>
                                        <li>Leave OT columns empty if you're only adding transactions</li>
                                        <li>Workers must already exist in your contractor worker list</li>
                                    </ul>
                                </div>

                                <div id="import-modal-actions" class="flex justify-end gap-2">
                                    <flux:button wire:click="closeImportModal" variant="ghost">
                                        Cancel
                                    </flux:button>
                                    <flux:button id="process-import-btn" wire:click="processImport" variant="primary" :disabled="!$importFile">
                                        Process File
                                    </flux:button>
                                </div>
                            </div>
                        </flux:card>
                    @else
                        <!-- Import Preview -->
                        <div class="space-y-4">
                            <!-- Error Summary -->
                            @if(count($importErrors) > 0)
                                <flux:card id="import-errors" class="p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800">
                                    <h3 class="text-sm font-semibold text-red-900 dark:text-red-100 mb-2">
                                        Errors Found ({{ count($importErrors) }})
                                    </h3>
                                    <div class="max-h-40 overflow-y-auto">
                                        <ul class="text-xs text-red-700 dark:text-red-300 space-y-1 list-disc list-inside">
                                            @foreach($importErrors as $error)
                                                <li>{{ $error }}</li>
                                            @endforeach
                                        </ul>
                                    </div>
                                </flux:card>
                            @endif

                            <!-- Success Summary -->
                            @if(count($importData) > 0)
                                <flux:card id="import-success-summary" class="p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800">
                                    <h3 class="text-sm font-semibold text-green-900 dark:text-green-100">
                                        Valid Records: {{ count($importData) }}
                                    </h3>
                                    <p class="text-xs text-green-700 dark:text-green-300 mt-1">
                                        The following data will be imported. Review carefully before confirming.
                                    </p>
                                </flux:card>

                                <!-- Data Preview Table -->
                                <div id="import-preview-table" class="border border-zinc-200 dark:border-zinc-700 rounded-lg overflow-hidden">
                                    <div class="max-h-96 overflow-y-auto">
                                        <table class="w-full text-sm">
                                            <thead class="bg-zinc-100 dark:bg-zinc-800 sticky top-0">
                                                <tr>
                                                    <th class="px-3 py-2 text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Passport</th>
                                                    <th class="px-3 py-2 text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Name</th>
                                                    <th class="px-3 py-2 text-center text-xs font-medium text-zinc-600 dark:text-zinc-400">OT Normal</th>
                                                    <th class="px-3 py-2 text-center text-xs font-medium text-zinc-600 dark:text-zinc-400">OT Rest</th>
                                                    <th class="px-3 py-2 text-center text-xs font-medium text-zinc-600 dark:text-zinc-400">OT Public</th>
                                                    <th class="px-3 py-2 text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Transaction</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                                                @foreach($importData as $item)
                                                    <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                                                        <td class="px-3 py-2 text-zinc-900 dark:text-zinc-100">{{ $item['passport'] }}</td>
                                                        <td class="px-3 py-2 text-zinc-900 dark:text-zinc-100">{{ $item['name'] }}</td>
                                                        <td class="px-3 py-2 text-center text-zinc-900 dark:text-zinc-100">
                                                            {{ $item['ot_normal'] ?? '-' }}
                                                        </td>
                                                        <td class="px-3 py-2 text-center text-zinc-900 dark:text-zinc-100">
                                                            {{ $item['ot_rest'] ?? '-' }}
                                                        </td>
                                                        <td class="px-3 py-2 text-center text-zinc-900 dark:text-zinc-100">
                                                            {{ $item['ot_public'] ?? '-' }}
                                                        </td>
                                                        <td class="px-3 py-2">
                                                            @if($item['transaction_type'])
                                                                <div class="flex items-center gap-2">
                                                                    @if($item['transaction_type'] === 'advance_payment')
                                                                        <flux:badge color="orange" size="sm">Advance</flux:badge>
                                                                    @elseif($item['transaction_type'] === 'deduction')
                                                                        <flux:badge color="red" size="sm">Deduction</flux:badge>
                                                                    @elseif($item['transaction_type'] === 'npl')
                                                                        <flux:badge color="purple" size="sm">NPL</flux:badge>
                                                                    @elseif($item['transaction_type'] === 'allowance')
                                                                        <flux:badge color="green" size="sm">Allowance</flux:badge>
                                                                    @endif
                                                                    <span class="text-xs text-zinc-600 dark:text-zinc-400">
                                                                        {{ $item['transaction_type'] === 'npl' ? $item['transaction_amount'] . ' days' : 'RM ' . number_format($item['transaction_amount'], 2) }}
                                                                    </span>
                                                                </div>
                                                                <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-1 truncate">{{ $item['transaction_remarks'] }}</p>
                                                            @else
                                                                <span class="text-xs text-zinc-400 dark:text-zinc-600">-</span>
                                                            @endif
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            @endif

                            <!-- Actions -->
                            <div class="flex justify-end gap-2 pt-4 border-t border-zinc-200 dark:border-zinc-700">
                                <flux:button wire:click="closeImportModal" variant="ghost">
                                    Cancel
                                </flux:button>
                                @if(count($importData) > 0)
                                    <flux:button wire:click="confirmImport" variant="primary">
                                        Confirm & Import {{ count($importData) }} {{ count($importData) == 1 ? 'Record' : 'Records' }}
                                    </flux:button>
                                @endif
                            </div>
                        </div>
                    @endif
                </div>
            </flux:modal>
        @endif
    </div>
