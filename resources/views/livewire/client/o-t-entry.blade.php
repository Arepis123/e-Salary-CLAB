<div id="ot-entry-scroll-container" class="flex h-full w-full flex-1 flex-col gap-6 overflow-y-auto" wire:init="initializeData">
        <!-- Back-to-top button: appears after scrolling down inside this container -->
        <button
            type="button"
            x-data="{ show: false }"
            x-init="
                const c = document.getElementById('ot-entry-scroll-container');
                if (c) {
                    const onScroll = () => { show = c.scrollTop > 300; };
                    c.addEventListener('scroll', onScroll, { passive: true });
                    onScroll();
                }
            "
            x-show="show"
            style="display: none;"
            x-transition.opacity
            @click="document.getElementById('ot-entry-scroll-container')?.scrollTo({ top: 0, behavior: 'smooth' })"
            aria-label="Back to top"
            class="fixed bottom-6 right-6 z-40 flex size-11 items-center justify-center rounded-full bg-zinc-900 text-white shadow-lg transition hover:bg-zinc-700 dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-200"
        >
            <flux:icon.arrow-up class="size-5" />
        </button>

        <!-- Page Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">OT & Transaction Entry</h1>
                <p class="text-sm text-zinc-600 dark:text-zinc-400">
                    Enter overtime hours and transactions for {{ $period['entry_month_name'] ?? 'previous month' }}
                </p>
            </div>
            <div class="flex gap-2">
                <x-tutorial-button page="ot-entry" />
                @if(!$isLoading && $isWithinWindow)
                    <flux:button id="download-template-btn" wire:click="downloadTemplate" variant="outline" icon="arrow-down-tray" size="sm">
                        Download Template
                    </flux:button>
                    <flux:button id="import-file-btn" wire:click="openImportModal" variant="filled" icon="arrow-up-tray" size="sm">
                        Import from File
                    </flux:button>
                @endif
            </div>
        </div>

        <!-- Sequential Payroll Queue Notice -->
        @if(!$isLoading && $isBlocked && count($blockReasons) > 0)
            <flux:card class="p-4 sm:p-6 dark:bg-zinc-900 rounded-lg border-2 border-orange-200 dark:border-orange-800">
                <div class="flex items-start gap-3">
                    <flux:icon.queue-list class="size-6 text-orange-600 dark:text-orange-400 flex-shrink-0 mt-0.5" />
                    <div class="flex-1">
                        <div class="flex items-center justify-between">
                            <h3 class="text-base font-semibold text-zinc-900 dark:text-zinc-100">Settle Outstanding Payroll First</h3>
                            <flux:badge color="zinc" size="sm">{{ $totalOutstandingCount }} {{ \Str::plural('month', $totalOutstandingCount) }} remaining</flux:badge>
                        </div>
                        <p class="text-sm text-zinc-600 dark:text-zinc-400 mt-1">
                            OT &amp; transaction entry is locked until your outstanding payroll is settled. {{ $blockReasons[0]['message'] }}
                        </p>

                        <div class="mt-4">
                            <flux:button
                                variant="primary"
                                size="sm"
                                href="{{ $blockReasons[0]['redirect_url'] }}"
                                wire:navigate
                                class="w-full sm:w-auto"
                            >
                                {{ $blockReasons[0]['action_text'] }}
                            </flux:button>
                        </div>
                    </div>
                </div>
            </flux:card>
        @endif

        @unless(!$isLoading && $isBlocked)
        <!-- Entry Window Status Card -->
        <flux:card id="entry-window-status" class="p-6 dark:bg-zinc-900 rounded-lg">
            <div class="flex items-center justify-between">
                @if($isLoading)
                    <div class="flex items-center gap-4">
                        <flux:skeleton animate="shimmer" class="size-12 rounded-full flex-shrink-0" />
                        <div class="space-y-2">
                            <flux:skeleton animate="shimmer" class="h-5 w-40 rounded" />
                            <flux:skeleton animate="shimmer" class="h-4 w-56 rounded" />
                            <flux:skeleton animate="shimmer" class="h-3 w-48 rounded" />
                        </div>
                    </div>
                    <div class="text-right space-y-1">
                        <flux:skeleton animate="shimmer" class="h-9 w-10 rounded ml-auto" />
                        <flux:skeleton animate="shimmer" class="h-3 w-20 rounded" />
                    </div>
                @else
                <div class="flex items-center gap-4">
                    @if($isWithinWindow)
                        <flux:icon.check-circle class="size-12 text-green-600 dark:text-green-400" />
                        <div>
                            <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">Entry Window OPEN</h3>
                            <p class="text-sm text-zinc-600 dark:text-zinc-400 mt-1">
                                You can enter OT hours for <strong>{{ $period['entry_month_name'] }}</strong>
                            </p>
                            <p class="text-sm text-zinc-600 dark:text-zinc-400 mt-1">
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
                            <p class="text-sm text-zinc-600 dark:text-zinc-400 mt-1">
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
                @endif
            </div>
        </flux:card>

        <!-- Submission Status -->
        @if(!$isLoading && $hasSubmitted && !$isManuallyOpened)
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
                    @if($isLoading)
                        <flux:skeleton animate="shimmer" class="h-6 w-56 rounded inline-block" />
                    @else
                        OT &amp; Transactions for {{ $period['entry_month_name'] }}
                    @endif
                </h3>
            </div>

            @if($isLoading)
                <!-- Skeleton table -->
                <div class="overflow-x-auto">
                    <flux:table>
                        <flux:table.columns>
                            <flux:table.column align="center"><span class="text-xs font-medium text-zinc-600 dark:text-zinc-400">No</span></flux:table.column>
                            <flux:table.column><span class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Worker ID</span></flux:table.column>
                            <flux:table.column><span class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Worker Name</span></flux:table.column>
                            <flux:table.column><span class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Passport</span></flux:table.column>
                            <flux:table.column align="center"><span class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Weekday OT<br>(Hours)</span></flux:table.column>
                            <flux:table.column align="center"><span class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Rest Day OT<br>(Hours)</span></flux:table.column>
                            <flux:table.column align="center"><span class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Public Holiday OT<br>(Hours)</span></flux:table.column>
                            <flux:table.column><span class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Transactions</span></flux:table.column>
                            <flux:table.column align="center"><span class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Actions</span></flux:table.column>
                        </flux:table.columns>
                        <flux:table.rows>
                            <flux:skeleton.group>
                                @for($i = 0; $i < 6; $i++)
                                <flux:table.rows>
                                    <flux:table.cell align="center"><flux:skeleton animate="shimmer" class="h-4 w-4 rounded mx-auto" /></flux:table.cell>
                                    <flux:table.cell><flux:skeleton animate="shimmer" class="h-4 w-20 rounded" /></flux:table.cell>
                                    <flux:table.cell><flux:skeleton animate="shimmer" class="h-4 w-28 rounded" /></flux:table.cell>
                                    <flux:table.cell><flux:skeleton animate="shimmer" class="h-4 w-20 rounded" /></flux:table.cell>
                                    <flux:table.cell align="center"><flux:skeleton animate="shimmer" class="h-8 w-20 rounded mx-auto" /></flux:table.cell>
                                    <flux:table.cell align="center"><flux:skeleton animate="shimmer" class="h-8 w-20 rounded mx-auto" /></flux:table.cell>
                                    <flux:table.cell align="center"><flux:skeleton animate="shimmer" class="h-8 w-20 rounded mx-auto" /></flux:table.cell>
                                    <flux:table.cell><flux:skeleton animate="shimmer" class="h-4 w-16 rounded" /></flux:table.cell>
                                    <flux:table.cell align="center"><flux:skeleton animate="shimmer" class="h-7 w-16 rounded mx-auto" /></flux:table.cell>
                                </flux:table.rows>
                                @endfor
                            </flux:skeleton.group>
                        </flux:table.rows>
                    </flux:table>
                </div>
            @elseif(count($entries) === 0)
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
                                                wire:model.blur="entries.{{ $index }}.ot_normal_hours"
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
                                                wire:model.blur="entries.{{ $index }}.ot_rest_hours"
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
                                                wire:model.blur="entries.{{ $index }}.ot_public_hours"
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
                                                        @elseif($txn['type'] === 'backpay')
                                                            +RM {{ number_format($txn['amount'], 2) }}
                                                        @elseif($txn['type'] === 'medical_claim')
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
                                </flux:table.rows>
                            @endforeach
                        </flux:table.rows>
                    </flux:table>
                </div>

                <!-- Bottom Actions -->
                @if(!$hasSubmitted && $isWithinWindow && count($entries) > 0)
                    <div id="ot-entry-actions" class="mt-6 flex items-center justify-between border-t border-zinc-200 dark:border-zinc-700 pt-4">
                        <!-- Auto-save status indicator -->
                        <div class="flex items-center gap-1.5 text-xs">
                            <div wire:loading wire:target="autoSaveDraft" class="flex items-center gap-1.5 text-zinc-500 dark:text-zinc-400">
                                <flux:icon.arrow-path class="size-3.5 animate-spin" />
                                <span>Saving...</span>
                            </div>
                            <div wire:loading.remove wire:target="autoSaveDraft">
                                @if($autoSaveStatus === 'saved')
                                    <span class="flex items-center gap-1.5 text-green-600 dark:text-green-400">
                                        <flux:icon.check-circle class="size-3.5" />
                                        Draft auto-saved
                                    </span>
                                @elseif($autoSaveStatus === 'error')
                                    <span class="flex items-center gap-1.5 text-red-600 dark:text-red-400">
                                        <flux:icon.exclamation-circle class="size-3.5" />
                                        Auto-save failed — please save manually
                                    </span>
                                @else
                                    <span class="text-zinc-400 dark:text-zinc-500">Changes are saved automatically</span>
                                @endif
                            </div>
                        </div>

                        @if($autoSaveStatus === 'error')
                            <flux:button id="save-draft-btn" wire:click="saveDraft" variant="outline" icon="document-text">
                                Save Draft
                            </flux:button>
                        @endif
                    </div>
                @endif
            @endif
        </flux:card>

        <!-- Salary Deduction Form -->
        @if(!$isLoading)
            <flux:card
                id="salary-deduction-form"
                x-data="{ spotlight: false }"
                x-on:salary-deduction-form-needed.window="
                    spotlight = true;
                    $nextTick(() => $el.scrollIntoView({ behavior: 'smooth', block: 'center' }));
                    setTimeout(() => spotlight = false, 6600);
                "
                x-bind:class="spotlight ? 'animate-deduction-breathe' : ''"
                class="p-6 dark:bg-zinc-900 rounded-lg scroll-mt-6"
            >
                <div class="flex flex-col gap-5">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="flex items-start gap-3">
                            <div>
                                <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">Salary Deduction Form</h3>
                                <p class="text-sm text-zinc-600 dark:text-zinc-400 mt-1">
                                    A declaration form signed by your officer and the worker, confirming the deductions recorded for
                                    <strong>{{ $period['entry_month_name'] ?? 'this period' }}</strong>.
                                    Download it, collect both signatures, then upload the signed copy back here.
                                </p>
                            </div>
                        </div>
                        @if($this->deductionWorkersCount > 0)
                            <flux:badge color="amber" size="sm">
                                {{ $this->deductionWorkersCount }} {{ \Str::plural('worker', $this->deductionWorkersCount) }} with deductions
                            </flux:badge>
                        @endif
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <!-- Step 1: download the pre-filled form -->
                        <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                            <div class="flex items-center gap-2">
                                <span class="flex size-5 items-center justify-center rounded-full bg-zinc-900 text-[10px] font-bold text-white dark:bg-white dark:text-zinc-900">1</span>
                                <span class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Download &amp; sign</span>
                            </div>
                            <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
                                One pre-filled signature page per worker, bundled into a single PDF.
                            </p>
                            <flux:button
                                id="download-deduction-form-btn"
                                wire:click="downloadDeductionForm"
                                variant="primary"
                                icon="arrow-down-tray"
                                size="sm"
                                class="mt-3"
                                :disabled="$this->deductionWorkersCount === 0"
                            >
                                Download Deduction Form
                            </flux:button>
                            @if($this->deductionWorkersCount === 0)
                                <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-500">
                                    No deductions recorded for this period.
                                </p>
                            @endif
                        </div>

                        <!-- Step 2: upload the signed copy back -->
                        <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                            <div class="flex items-center gap-2">
                                <span class="flex size-5 items-center justify-center rounded-full bg-zinc-900 text-[10px] font-bold text-white dark:bg-white dark:text-zinc-900">2</span>
                                <span class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Upload signed copy</span>
                            </div>

                            @if($signedDeductionForm)
                                <div class="mt-3 flex flex-wrap items-center gap-3 rounded-md bg-green-50 p-3 dark:bg-green-900/20">
                                    <flux:icon.check-circle class="size-5 text-green-600 dark:text-green-400 flex-shrink-0" />
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                            {{ $signedDeductionForm['file_name'] }}
                                        </p>
                                        <p class="text-xs text-zinc-600 dark:text-zinc-400">
                                            {{ $signedDeductionForm['file_size'] }}
                                            @if($signedDeductionForm['uploaded_at'])
                                                &middot; uploaded {{ $signedDeductionForm['uploaded_at'] }}
                                            @endif
                                        </p>
                                    </div>
                                </div>
                                <div class="mt-3 flex flex-wrap gap-2">
                                    <flux:button wire:click="downloadSignedDeductionForm" variant="outline" icon="arrow-down-tray" size="sm">
                                        View Uploaded
                                    </flux:button>
                                    @if($isWithinWindow)
                                        <flux:button wire:click="removeSignedDeductionForm" variant="ghost" icon="trash" size="sm" class="text-red-600 dark:text-red-400">
                                            Replace
                                        </flux:button>
                                    @endif
                                </div>
                            @elseif($isWithinWindow)
                                <div class="mt-3 space-y-2">
                                    <flux:file-upload wire:model="deductionFormFile" accept=".pdf,.jpg,.jpeg,.png">
                                        <flux:file-upload.dropzone
                                            heading="Drop the signed form or click to browse"
                                            text="PDF, JPG or PNG up to 10 MB"
                                            with-progress
                                            inline
                                        />
                                    </flux:file-upload>

                                    {{-- The dropzone shows progress but not the result, so name what is staged. --}}
                                    @if($deductionFormFile)
                                        <flux:file-item
                                            heading="{{ $deductionFormFile->getClientOriginalName() }}"
                                            :size="$deductionFormFile->getSize()"
                                            class="my-2"
                                        >
                                            <x-slot name="actions">
                                                <flux:file-item.remove wire:click="$set('deductionFormFile', null)" />
                                            </x-slot>
                                        </flux:file-item>
                                    @endif

                                    <flux:error name="deductionFormFile" />

                                    {{-- Nothing to upload until a file is staged, so the button stays out of the way. --}}
                                    @if($deductionFormFile)
                                        <flux:button
                                            id="upload-deduction-form-btn"
                                            wire:click="uploadDeductionForm"
                                            variant="primary"
                                            icon="arrow-up-tray"
                                            size="sm"
                                        >
                                            Upload Signed Form
                                        </flux:button>
                                    @endif
                                </div>
                            @else
                                <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
                                    The entry window is closed, so the signed form can no longer be uploaded for this period.
                                </p>
                            @endif
                        </div>
                    </div>
                </div>
            </flux:card>
        @endif

        <!-- Help Information -->
        <flux:callout icon="information-circle" color="blue">
            <flux:callout.heading>Important Information</flux:callout.heading>
            <flux:callout.text>
                <ul class="list-disc list-inside space-y-1 text-sm">
                    <li>OT and transaction entries can only be made between the <strong>1st and 15th</strong> of each month</li>
                    <li>Entries are for the <strong>previous month's</strong> overtime hours and transactions</li>
                    <li>All changes are <strong>saved automatically</strong> as you type</li>
                    <li>On the <strong>16th</strong>, all entries are automatically submitted and locked</li>
                    <li>Locked entries will be automatically included in your next payroll</li>
                </ul>
            </flux:callout.text>
        </flux:callout>

        <!-- Transaction Management Modal -->
        @if($showTransactionModal && $currentWorkerIndex !== null)
            <flux:modal wire:model="showTransactionModal" class="min-w-[600px]" :dismissible="false">
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
                                        <flux:select.option value="accommodation">Accommodation</flux:select.option>
                                        <flux:select.option value="advance_payment">Advance Payment</flux:select.option>
                                        <flux:select.option value="npl">No-Pay Leave (NPL)</flux:select.option>
                                    @else
                                        <flux:select.option value="allowance">Allowance</flux:select.option>
                                        <flux:select.option value="backpay">Backpay</flux:select.option>
                                        <flux:select.option value="medical_claim">Medical Claim</flux:select.option>
                                    @endif
                                </flux:select>
                                @error('newTransactionType') <span class="text-xs text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                            </div>

                            @if($newTransactionType === 'npl')
                                <!--
                                    NPL is charged against the month the leave was taken,
                                    at that month's own daily rate, so several months can
                                    be selected in one transaction.
                                -->
                                @php
                                    $nplMonths = $this->nplSelectableMonths;
                                    $nplPreview = $this->nplPreview;
                                @endphp

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <!-- Month multi-select -->
                                    <div>
                                        <flux:label>NPL Month <span class="text-red-600">*</span></flux:label>

                                        @if(count($nplSelectedMonths) > 0)
                                            <div class="flex flex-wrap gap-1 mt-1 mb-2">
                                                @foreach($nplMonths as $month)
                                                    @if(in_array($month['key'], $nplSelectedMonths))
                                                        <flux:badge color="blue" size="sm">{{ $month['label'] }}</flux:badge>
                                                    @endif
                                                @endforeach
                                            </div>
                                        @endif

                                        <div class="mt-1 border border-zinc-200 dark:border-zinc-700 rounded-lg divide-y divide-zinc-100 dark:divide-zinc-800 max-h-48 overflow-y-auto">
                                            @foreach($nplMonths as $month)
                                                <label class="flex items-center gap-2 px-3 py-2 text-sm {{ $month['already_used'] ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer hover:bg-zinc-50 dark:hover:bg-zinc-800' }}">
                                                    <input
                                                        type="checkbox"
                                                        wire:model.live="nplSelectedMonths"
                                                        value="{{ $month['key'] }}"
                                                        @disabled($month['already_used'])
                                                        class="rounded border-zinc-300 dark:border-zinc-600"
                                                    />
                                                    <span class="text-zinc-900 dark:text-zinc-100">
                                                        {{ $month['label'] }}
                                                        <span class="text-zinc-500 dark:text-zinc-400">({{ $month['days_in_month'] }} days)</span>
                                                    </span>
                                                    @if($month['already_used'])
                                                        <flux:badge color="zinc" size="sm" class="ml-auto">Already recorded</flux:badge>
                                                    @endif
                                                </label>
                                            @endforeach
                                        </div>

                                        <div class="flex gap-3 mt-2">
                                            <button type="button" wire:click="selectAllNplMonths" class="text-xs text-blue-600 dark:text-blue-400 hover:underline">Select All</button>
                                            <button type="button" wire:click="clearNplMonths" class="text-xs text-blue-600 dark:text-blue-400 hover:underline">Clear All</button>
                                        </div>

                                        @error('nplSelectedMonths') <span class="text-xs text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                                    </div>

                                    <!-- Days per selected month -->
                                    <div>
                                        <flux:label>No-Pay Leave Days <span class="text-red-600">*</span></flux:label>

                                        @if(count($nplSelectedMonths) === 0)
                                            <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-2">Select one or more NPL months first.</p>
                                        @else
                                            <div class="space-y-2 mt-1">
                                                @foreach($nplMonths as $month)
                                                    @if(in_array($month['key'], $nplSelectedMonths))
                                                        <div class="flex items-center gap-2">
                                                            <span class="text-sm text-zinc-700 dark:text-zinc-300 flex-1">
                                                                {{ $month['label'] }}
                                                            </span>
                                                            <input
                                                                type="number"
                                                                step="0.5"
                                                                min="0"
                                                                max="{{ $month['days_in_month'] }}"
                                                                wire:model.live="nplDaysByMonth.{{ $month['key'] }}"
                                                                class="w-20 rounded-md border border-zinc-300 dark:border-zinc-600 bg-white dark:bg-zinc-900 px-2 py-1 text-sm text-zinc-900 dark:text-zinc-100"
                                                            />
                                                            <span class="text-sm text-zinc-500">days</span>
                                                        </div>
                                                        @error('nplDaysByMonth.'.$month['key'])
                                                            <span class="text-xs text-red-600 dark:text-red-400">{{ $message }}</span>
                                                        @enderror
                                                    @endif
                                                @endforeach
                                            </div>

                                            <flux:callout variant="success" class="mt-3" icon="check-circle">
                                                <flux:callout.text class="text-xs">
                                                    Deduction is calculated automatically from the real number of days in each selected month.
                                                </flux:callout.text>
                                            </flux:callout>
                                        @endif
                                    </div>
                                </div>

                                <!-- Monthly salary (read-only, from payroll) -->
                                <div>
                                    <flux:label>Monthly Salary (RM)</flux:label>
                                    <div class="mt-1 px-3 py-2 rounded-md border border-zinc-200 dark:border-zinc-700 bg-zinc-100 dark:bg-zinc-800 text-sm text-zinc-900 dark:text-zinc-100">
                                        {{ number_format($nplMonthlySalary, 2) }}
                                    </div>                                   
                                </div>

                                <!-- Live calculation summary -->
                                @if(count($nplPreview['rows']) > 0)
                                    <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 overflow-hidden">
                                        <div class="px-3 py-2 bg-zinc-100 dark:bg-zinc-800">
                                            <h4 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">System Calculation</h4>
                                        </div>
                                        <div class="overflow-x-auto">
                                            <table class="w-full text-xs">
                                                <thead class="bg-zinc-50 dark:bg-zinc-900">
                                                    <tr class="text-left text-zinc-600 dark:text-zinc-400">
                                                        <th class="px-3 py-2 font-medium">NPL Month</th>
                                                        <th class="px-3 py-2 font-medium text-center">Days in Month</th>
                                                        <th class="px-3 py-2 font-medium text-center">NPL Days</th>
                                                        <th class="px-3 py-2 font-medium">Daily Rate (RM)</th>
                                                        <th class="px-3 py-2 font-medium text-right">NPL Deduction (RM)</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                                                    @foreach($nplPreview['rows'] as $row)
                                                        <tr class="text-zinc-900 dark:text-zinc-100">
                                                            <td class="px-3 py-2">{{ $row['month_label'] }}</td>
                                                            <td class="px-3 py-2 text-center">{{ $row['days_in_month'] }}</td>
                                                            <td class="px-3 py-2 text-center">{{ rtrim(rtrim(number_format($row['npl_days'], 1), '0'), '.') }}</td>
                                                            <td class="px-3 py-2 text-zinc-600 dark:text-zinc-400">
                                                                RM{{ number_format($row['monthly_salary'], 2) }} ÷ {{ $row['days_in_month'] }} = RM{{ number_format($row['daily_rate'], 2) }}
                                                            </td>
                                                            <td class="px-3 py-2 text-right font-semibold">RM{{ number_format($row['amount'], 2) }}</td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                                <tfoot class="bg-zinc-50 dark:bg-zinc-900">
                                                    <tr>
                                                        <td colspan="4" class="px-3 py-2 text-right font-semibold text-zinc-900 dark:text-zinc-100">Total NPL Deduction (RM)</td>
                                                        <td class="px-3 py-2 text-right font-bold text-zinc-900 dark:text-zinc-100">RM{{ number_format($nplPreview['total_amount'], 2) }}</td>
                                                    </tr>
                                                </tfoot>
                                            </table>
                                        </div>
                                    </div>
                                @endif
                            @else
                                <!-- Amount Input -->
                                <div>
                                    <flux:input wire:model.live="newTransactionAmount" type="number" step="0.01" min="0" label="Amount (RM)" placeholder="0.00" />
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
                                                    @php $nplTotal = collect($transaction['npl_details'] ?? [])->sum('amount'); @endphp
                                                    <span class="font-semibold text-zinc-900 dark:text-zinc-100">
                                                        {{ $transaction['amount'] }} {{ $transaction['amount'] == 1 ? 'day' : 'days' }}
                                                        @if(count($transaction['npl_details'] ?? []) > 0)
                                                            <span class="text-zinc-500 dark:text-zinc-400">— RM {{ number_format($nplTotal, 2) }}</span>
                                                        @endif
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
                                                @elseif($transaction['type'] === 'backpay')
                                                    <flux:badge color="cyan" size="sm">Backpay</flux:badge>
                                                @elseif($transaction['type'] === 'medical_claim')
                                                    <flux:badge color="lime" size="sm">Medical Claim</flux:badge>
                                                @endif
                                            </div>
                                            @if($transaction['type'] === 'npl' && count($transaction['npl_details'] ?? []) > 0)
                                                <div class="mt-1 space-y-0.5">
                                                    @foreach($transaction['npl_details'] as $detail)
                                                        <p class="text-xs text-zinc-500 dark:text-zinc-400">
                                                            {{ $detail['month_label'] }} ({{ $detail['days_in_month'] }} days) &mdash;
                                                            {{ rtrim(rtrim(number_format($detail['npl_days'], 1), '0'), '.') }} days &times;
                                                            RM{{ number_format($detail['daily_rate'], 2) }} =
                                                            <span class="font-medium text-zinc-700 dark:text-zinc-300">RM{{ number_format($detail['amount'], 2) }}</span>
                                                        </p>
                                                    @endforeach
                                                </div>
                                            @endif
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
                        <flux:button wire:click="closeTransactionModal" variant="primary">Close</flux:button>
                    </div>
                </div>
            </flux:modal>
        @endif

        <!-- Floating Scroll-to-Bottom Button -->
        <button
            id="scroll-to-bottom-btn"
            onclick="scrollPageToBottom()"
            title="Scroll to bottom"
            class="fixed bottom-6 right-6 z-50 flex items-center justify-center size-11 rounded-full bg-zinc-800 dark:bg-zinc-100 text-white dark:text-zinc-900 shadow-lg hover:bg-zinc-600 dark:hover:bg-zinc-300 transition-all duration-200"
        >
            <flux:icon.arrow-down class="size-5" />
        </button>

        <script>
            window.scrollPageToBottom = function () {
                [
                    document.getElementById('ot-entry-scroll-container'),
                    document.querySelector('main'),
                    document.documentElement,
                    document.body,
                ].forEach(function (el) {
                    if (el) el.scrollTo({ top: el.scrollHeight, behavior: 'smooth' });
                });
            };
        </script>

        <!-- Import Modal -->
        @if($showImportModal)
            <flux:modal id="import-modal" wire:model="showImportModal" class="min-w-[800px]" :dismissible="false">
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
                                        <li>Deduction types: <strong class="text-zinc-900 dark:text-zinc-100">accommodation</strong>, <strong class="text-zinc-900 dark:text-zinc-100">advance_payment</strong>, <strong class="text-zinc-900 dark:text-zinc-100">npl</strong></li>
                                        <li>Earning types: <strong class="text-zinc-900 dark:text-zinc-100">allowance</strong>, <strong class="text-zinc-900 dark:text-zinc-100">backpay</strong>, <strong class="text-zinc-900 dark:text-zinc-100">medical_claim</strong></li>
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

                                <!-- Import Mode Selection -->
                                <flux:card class="p-4 bg-zinc-100 dark:bg-zinc-800">
                                    <h3 class="text-sm font-semibold mb-3">
                                        How should transactions be handled?
                                    </h3>
                                    <div class="space-y-2">
                                        <label class="flex items-start gap-3 cursor-pointer">
                                            <input type="radio" wire:model="importMode" value="add" class="mt-1">
                                            <div>
                                                <span class="text-sm font-medium ">Add to existing</span>
                                                <p class="text-xs ">OT hours will be <strong>added</strong> to existing values. Transactions will be <strong>appended</strong> to existing list.</p>
                                            </div>
                                        </label>
                                        <label class="flex items-start gap-3 cursor-pointer">
                                            <input type="radio" wire:model="importMode" value="override" class="mt-1">
                                            <div>
                                                <span class="text-sm font-medium ">Override existing</span>
                                                <p class="text-xs ">OT hours will be <strong>replaced</strong> with imported values. Transactions will be <strong>replaced</strong> entirely.</p>
                                            </div>
                                        </label>
                                    </div>
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
                                                                    @elseif($item['transaction_type'] === 'accommodation')
                                                                        <flux:badge color="amber" size="sm">Accommodation</flux:badge>
                                                                    @elseif($item['transaction_type'] === 'backpay')
                                                                        <flux:badge color="cyan" size="sm">Backpay</flux:badge>
                                                                    @elseif($item['transaction_type'] === 'medical_claim')
                                                                        <flux:badge color="lime" size="sm">Medical Claim</flux:badge>
                                                                    @endif
                                                                    <span class="text-xs text-zinc-600 dark:text-zinc-400">
                                                                        @if($item['transaction_type'] === 'npl')
                                                                            {{ $item['transaction_amount'] }} days
                                                                            @if(! empty($item['npl_year']))
                                                                                <span class="text-zinc-500">in {{ \Carbon\Carbon::create($item['npl_year'], $item['npl_month'], 1)->format('M Y') }}</span>
                                                                            @endif
                                                                        @else
                                                                            RM {{ number_format($item['transaction_amount'], 2) }}
                                                                        @endif
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
        @endunless
    </div>
