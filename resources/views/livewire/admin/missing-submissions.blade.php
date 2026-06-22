<div class="flex h-full w-full flex-1 flex-col gap-6">
    <!-- Page Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">Missing Submissions & Payments</h1>
            <p class="text-sm text-zinc-600 dark:text-zinc-400">Track contractors with missing submissions or unpaid payroll for {{ \Carbon\Carbon::create($selectedYear, $selectedMonth, 1)->format('F Y') }}</p>
        </div>
    </div>

    <!-- Historical Summary Section -->
    @if(count($historicalSummary) > 0)
    <flux:card class="p-4 sm:p-6 dark:bg-zinc-900 rounded-lg">
        <div class="flex items-start justify-between gap-4">
            <div class="flex items-start gap-3 flex-1">
                <div class="flex-1">
                    <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">Contractor List With Missing Submissions</h2>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400 mt-1">
                        {{ count($historicalSummary) }} {{ Str::plural('contractor', count($historicalSummary)) }} with multiple missing submissions or payments in the last 6 months (excluding current month)
                    </p>
                </div>
            </div>
            <div class="flex gap-2">
                <flux:button
                    wire:click="exportDetailed"
                    variant="ghost"
                    size="sm"
                    icon="arrow-down-tray"
                    icon-variant="outline"
                >
                    Export Details
                </flux:button>
                <flux:button
                    wire:click="toggleHistoricalSummary"
                    variant="ghost"
                    size="sm"
                    :icon="$showHistoricalSummary ? 'chevron-up' : 'chevron-down'"
                    icon-variant="micro"
                >
                    {{ $showHistoricalSummary ? 'Hide' : 'Show' }} Details
                </flux:button>
            </div>
        </div>

        @if($showHistoricalSummary)
        <div class="mt-4 pt-4 border-t border-zinc-200 dark:border-zinc-700">
            <flux:table>
                <flux:table.columns>
                    <flux:table.column align="center"><span class="text-center text-xs font-medium text-zinc-600 dark:text-zinc-400">No</span></flux:table.column>
                    <flux:table.column><span class="text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Contractor</span></flux:table.column>
                    <flux:table.column align="center"><span class="text-center text-xs font-medium text-zinc-600 dark:text-zinc-400">Missing Months</span></flux:table.column>
                    <flux:table.column><span class="text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Periods</span></flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach($this->historicalPaginated as $index => $contractor)
                        <flux:table.rows :key="$contractor['clab_no']">
                            <flux:table.cell align="center">{{ $this->historicalPaginated->firstItem() + $index }}</flux:table.cell>

                            <flux:table.cell variant="strong">
                                <div>
                                    <div class="font-medium">{{ $contractor['name'] }}</div>
                                    <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ $contractor['clab_no'] }}</div>
                                </div>
                            </flux:table.cell>

                            <flux:table.cell align="center">
                                <flux:badge
                                    :color="$contractor['missing_count'] >= 4 ? 'red' : ($contractor['missing_count'] >= 3 ? 'orange' : 'yellow')"
                                    size="sm"
                                    inset="top bottom"
                                >
                                    {{ $contractor['missing_count'] }} of 6
                                </flux:badge>
                            </flux:table.cell>

                            <flux:table.cell>
                                <div class="flex flex-wrap gap-1">
                                    @foreach($contractor['missing_months'] as $period)
                                        <div class="flex flex-col gap-0.5">
                                            <flux:badge color="zinc" size="xs" inset="top bottom">
                                                {{ $period['label'] }}: {{ $period['missing_count'] }}/{{ $period['total_count'] }}
                                            </flux:badge>
                                            <div class="text-[10px] text-zinc-500 dark:text-zinc-400 ml-1">
                                                @if($period['not_submitted'] > 0)
                                                    <span class="text-red-600 dark:text-red-400">{{ $period['not_submitted'] }} not sub.</span>
                                                @endif
                                                @if($period['not_paid'] > 0)
                                                    @if($period['not_submitted'] > 0), @endif
                                                    <span class="text-amber-600 dark:text-amber-400">{{ $period['not_paid'] }} not paid</span>
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </flux:table.cell>
                        </flux:table.rows>
                    @endforeach
                </flux:table.rows>
            </flux:table>

            <!-- Pagination -->
            <flux:pagination :paginator="$this->historicalPaginated" class="mt-5" />
        </div>
        @endif
    </flux:card>
    @endif

    <!-- Contractors List with Tabs -->
    @php
        $notSubmittedCount = collect($missingContractors)->filter(fn($c) => $c['not_submitted'] > 0)->count();
        $notPaidCount = collect($missingContractors)->filter(fn($c) => $c['submitted_not_paid'] > 0)->count();
        $outOfSyncCount = collect($outOfSyncContractors)->count();
    @endphp

    {{-- Auto-runs the Out of Sync drift scan in a background request after the page
         renders, so the tab badge populates without the admin opening the tab. --}}
    <div wire:init="loadOutOfSync" class="hidden"></div>

    <flux:card class="p-4 sm:p-6 dark:bg-zinc-900 rounded-lg">
        <div class="mb-4 flex items-center justify-between">
            <div>
                <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">Contractors List</h2>
                <p class="text-sm text-zinc-600 dark:text-zinc-400 mt-1">
                    Viewing period: {{ \Carbon\Carbon::create($selectedYear, $selectedMonth, 1)->format('F Y') }}
                </p>
            </div>
            <div class="flex gap-2">
                <flux:button variant="ghost" size="sm" icon="arrow-path" icon-variant="outline" wire:click="refresh">
                    Refresh
                </flux:button>
                <flux:button variant="ghost" size="sm" icon="arrow-down-tray" icon-variant="outline" wire:click="exportCurrentPeriodDetailed">
                    Export Details
                </flux:button>
                <flux:button variant="ghost" size="sm" icon="document-text" icon-variant="outline" wire:click="export">
                    Export Summary
                </flux:button>
            </div>
        </div>

        <flux:tab.group>
            <flux:tabs wire:model.live="activeTab" class="mb-4">
                <flux:tab name="not_submitted" icon="x-circle">
                    Not Submit
                    @if(!$this->notSubmitLocked && $notSubmittedCount > 0)
                        <flux:badge color="red" size="sm" inset="top bottom" class="ml-1">{{ $notSubmittedCount }}</flux:badge>
                    @endif
                </flux:tab>
                <flux:tab name="out_of_sync" icon="arrow-path-rounded-square">
                    Out of Sync
                    @if(! $outOfSyncLoaded)
                        {{-- Background drift scan still running --}}
                        <flux:icon.loading class="ml-1 size-3 inline opacity-60" />
                    @elseif($outOfSyncCount > 0)
                        <flux:badge color="purple" size="sm" inset="top bottom" class="ml-1">{{ $outOfSyncCount }}</flux:badge>
                    @endif
                </flux:tab>
                <flux:tab name="not_paid" icon="banknotes">
                    Not Paid
                    @if($notPaidCount > 0)
                        <flux:badge color="amber" size="sm" inset="top bottom" class="ml-1">{{ $notPaidCount }}</flux:badge>
                    @endif
                </flux:tab>
            </flux:tabs>

            {{-- Tab 1: Not Submit --}}
            <flux:tab.panel name="not_submitted">
                @if($this->notSubmitLocked)
                <div class="py-12 text-center">
                    <div class="flex flex-col items-center gap-3">
                        <div class="rounded-full bg-blue-100 dark:bg-blue-900/30 p-4">
                            <flux:icon.clock class="size-10 text-blue-600 dark:text-blue-400" />
                        </div>
                        <div>
                            <p class="text-sm font-medium text-zinc-700 dark:text-zinc-300">
                                Submission window still open for {{ \Carbon\Carbon::create($selectedYear, $selectedMonth, 1)->format('F Y') }}.
                            </p>
                            <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-1">
                                The Not Submit list becomes available on {{ $this->autoSubmitDate->format('d F Y') }}, after auto-submit runs.
                                Contractors can still submit on their own until then.
                            </p>
                        </div>
                    </div>
                </div>
                @elseif($activeTab === 'not_submitted' && $notSubmittedCount > 0)
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column align="center"><span class="text-xs font-medium text-zinc-600 dark:text-zinc-400">No</span></flux:table.column>
                        <flux:table.column><span class="text-xs font-medium text-zinc-600 dark:text-zinc-400">CLAB No</span></flux:table.column>
                        <flux:table.column><span class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Contractor Name</span></flux:table.column>
                        <flux:table.column><span class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Contact</span></flux:table.column>
                        <flux:table.column align="center"><span class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Not Submitted</span></flux:table.column>
                        <flux:table.column align="center"><span class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Reminders Sent</span></flux:table.column>
                        <flux:table.column align="center"><span class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Actions</span></flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach($this->missingPaginated as $index => $contractor)
                            <flux:table.rows :key="$contractor['clab_no']">
                                <flux:table.cell align="center">{{ $this->missingPaginated->firstItem() + $index }}</flux:table.cell>
                                <flux:table.cell variant="strong">{{ $contractor['clab_no'] }}</flux:table.cell>
                                <flux:table.cell variant="strong">{{ $contractor['name'] }}</flux:table.cell>
                                <flux:table.cell>
                                    <div class="text-sm">
                                        @if($contractor['email'])
                                            @php $firstEmail = trim(preg_split('/[,;]/', $contractor['email'])[0]); @endphp
                                            <div class="flex items-center gap-1 text-zinc-600 dark:text-zinc-400">
                                                <flux:icon.envelope class="size-3 me-1" />
                                                <span>{{ $firstEmail }}</span>
                                            </div>
                                        @endif
                                        @if($contractor['phone'])
                                            <div class="flex items-center gap-1 text-zinc-600 dark:text-zinc-400 mt-1">
                                                <flux:icon.phone class="size-3 me-1" />
                                                <span>{{ $contractor['phone'] }}</span>
                                            </div>
                                        @endif
                                        @if(!$contractor['email'] && !$contractor['phone'])
                                            <span class="text-zinc-400 dark:text-zinc-500">No contact info</span>
                                        @endif
                                    </div>
                                </flux:table.cell>
                                <flux:table.cell align="center">
                                    <div class="flex flex-col items-center gap-1">
                                        <flux:badge color="red" size="sm" inset="top bottom">
                                            {{ $contractor['not_submitted'] }} of {{ $contractor['total_workers'] }} workers
                                        </flux:badge>
                                    </div>
                                </flux:table.cell>
                                <flux:table.cell align="center">
                                    @if($contractor['reminders_sent'] > 0)
                                        <flux:badge color="blue" size="sm" inset="top bottom">
                                            {{ $contractor['reminders_sent'] }} {{ Str::plural('time', $contractor['reminders_sent']) }}
                                        </flux:badge>
                                    @else
                                        <flux:badge color="zinc" size="sm" inset="top bottom">No reminders</flux:badge>
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell align="center">
                                    <flux:dropdown align="end">
                                        <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" icon-variant="micro" inset="top bottom"></flux:button>
                                        <flux:menu>
                                            <flux:menu.item icon="bell" wire:click="openRemindModal('{{ $contractor['clab_no'] }}')">Send Reminder</flux:menu.item>
                                            <flux:menu.item icon="paper-airplane" wire:click="openBulkSubmitModal('{{ $contractor['clab_no'] }}')">Submit on Behalf</flux:menu.item>
                                            <flux:menu.separator />
                                            <flux:menu.item icon="eye" href="{{ route('missing-submissions.detail', $contractor['clab_no']) }}" wire:navigate>View Details</flux:menu.item>
                                        </flux:menu>
                                    </flux:dropdown>
                                </flux:table.cell>
                            </flux:table.rows>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
                @else
                <div class="py-12 text-center">
                    <div class="flex flex-col items-center gap-3">
                        <div class="rounded-full bg-green-100 dark:bg-green-900/30 p-4">
                            <flux:icon.check-circle class="size-10 text-green-600 dark:text-green-400" />
                        </div>
                        <p class="text-sm font-medium text-zinc-700 dark:text-zinc-300">All contractors have submitted for this period.</p>
                    </div>
                </div>
                @endif
            </flux:tab.panel>

            {{-- Tab 2: Not Paid --}}
            <flux:tab.panel name="not_paid">
                @if($activeTab === 'not_paid' && $notPaidCount > 0)
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column align="center"><span class="text-xs font-medium text-zinc-600 dark:text-zinc-400">No</span></flux:table.column>
                        <flux:table.column><span class="text-xs font-medium text-zinc-600 dark:text-zinc-400">CLAB No</span></flux:table.column>
                        <flux:table.column><span class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Contractor Name</span></flux:table.column>
                        <flux:table.column><span class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Contact</span></flux:table.column>
                        <flux:table.column align="center"><span class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Not Paid</span></flux:table.column>
                        <flux:table.column align="center"><span class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Reminders Sent</span></flux:table.column>
                        <flux:table.column align="center"><span class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Actions</span></flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach($this->missingPaginated as $index => $contractor)
                            <flux:table.rows :key="$contractor['clab_no']">
                                <flux:table.cell align="center">{{ $this->missingPaginated->firstItem() + $index }}</flux:table.cell>
                                <flux:table.cell variant="strong">{{ $contractor['clab_no'] }}</flux:table.cell>
                                <flux:table.cell variant="strong">{{ $contractor['name'] }}</flux:table.cell>
                                <flux:table.cell>
                                    <div class="text-sm">
                                        @if($contractor['email'])
                                            @php $firstEmail = trim(preg_split('/[,;]/', $contractor['email'])[0]); @endphp
                                            <div class="flex items-center gap-1 text-zinc-600 dark:text-zinc-400">
                                                <flux:icon.envelope class="size-3 me-1" />
                                                <span>{{ $firstEmail }}</span>
                                            </div>
                                        @endif
                                        @if($contractor['phone'])
                                            <div class="flex items-center gap-1 text-zinc-600 dark:text-zinc-400 mt-1">
                                                <flux:icon.phone class="size-3 me-1" />
                                                <span>{{ $contractor['phone'] }}</span>
                                            </div>
                                        @endif
                                        @if(!$contractor['email'] && !$contractor['phone'])
                                            <span class="text-zinc-400 dark:text-zinc-500">No contact info</span>
                                        @endif
                                    </div>
                                </flux:table.cell>
                                <flux:table.cell align="center">
                                    <div class="flex flex-col items-center gap-1">
                                        <flux:badge color="amber" size="sm" inset="top bottom">
                                            {{ $contractor['submitted_not_paid'] }} of {{ $contractor['total_workers'] }} workers
                                        </flux:badge>
                                    </div>
                                </flux:table.cell>
                                <flux:table.cell align="center">
                                    @if($contractor['reminders_sent'] > 0)
                                        <flux:badge color="blue" size="sm" inset="top bottom">
                                            {{ $contractor['reminders_sent'] }} {{ Str::plural('time', $contractor['reminders_sent']) }}
                                        </flux:badge>
                                    @else
                                        <flux:badge color="zinc" size="sm" inset="top bottom">No reminders</flux:badge>
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell align="center">
                                    <flux:dropdown align="end">
                                        <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" icon-variant="micro" inset="top bottom"></flux:button>
                                        <flux:menu>
                                            <flux:menu.item icon="bell" wire:click="openRemindModal('{{ $contractor['clab_no'] }}')">Send Reminder</flux:menu.item>
                                            <flux:menu.separator />
                                            <flux:menu.item icon="eye" href="{{ route('missing-submissions.detail', $contractor['clab_no']) }}" wire:navigate>View Details</flux:menu.item>
                                        </flux:menu>
                                    </flux:dropdown>
                                </flux:table.cell>
                            </flux:table.rows>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
                @else
                <div class="py-12 text-center">
                    <div class="flex flex-col items-center gap-3">
                        <div class="rounded-full bg-green-100 dark:bg-green-900/30 p-4">
                            <flux:icon.check-circle class="size-10 text-green-600 dark:text-green-400" />
                        </div>
                        <p class="text-sm font-medium text-zinc-700 dark:text-zinc-300">All submitted contractors have completed payment for this period.</p>
                    </div>
                </div>
                @endif
            </flux:tab.panel>

            {{-- Tab 3: Out of Sync (late OT/transaction changes) --}}
            <flux:tab.panel name="out_of_sync">
                <flux:callout icon="information-circle" variant="secondary" inline x-data="{ visible: true }" x-show="visible" class="mb-3">
                    <flux:callout.heading class="flex gap-2 @max-md:flex-col items-start">These contractors changed OT hours or transactions after their timesheet was generated. Re-syncing updates the generated timesheet with the latest data.</flux:callout.heading>
                    <x-slot name="controls">
                        <flux:button icon="x-mark" variant="ghost" x-on:click="visible = false" />
                    </x-slot>
                </flux:callout>

                {{-- Skeleton while a recompute request (refresh / period change) is in flight --}}
                <div wire:loading.flex wire:target="refresh, selectedMonth, selectedYear">
                    @include('livewire.admin.partials.out-of-sync-skeleton')
                </div>

                <div wire:loading.remove wire:target="refresh, selectedMonth, selectedYear">
                @if(! $outOfSyncLoaded)
                {{-- First load: background drift scan (wire:init) still running --}}
                @include('livewire.admin.partials.out-of-sync-skeleton')
                @elseif($activeTab === 'out_of_sync' && $outOfSyncCount > 0)
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column align="center"><span class="text-xs font-medium text-zinc-600 dark:text-zinc-400">No</span></flux:table.column>
                        <flux:table.column><span class="text-xs font-medium text-zinc-600 dark:text-zinc-400">CLAB No</span></flux:table.column>
                        <flux:table.column><span class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Contractor Name</span></flux:table.column>
                        <flux:table.column align="center"><span class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Timesheet Status</span></flux:table.column>
                        <flux:table.column align="center"><span class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Workers Changed</span></flux:table.column>
                        <flux:table.column align="center"><span class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Actions</span></flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach($this->missingPaginated as $index => $contractor)
                            <flux:table.rows :key="$contractor['clab_no']">
                                <flux:table.cell align="center">{{ $this->missingPaginated->firstItem() + $index }}</flux:table.cell>
                                <flux:table.cell variant="strong">{{ $contractor['clab_no'] }}</flux:table.cell>
                                <flux:table.cell variant="strong">{{ $contractor['name'] }}</flux:table.cell>
                                <flux:table.cell align="center">
                                    <flux:badge :color="$contractor['status'] === 'approved' ? 'green' : 'blue'" size="sm" inset="top bottom">
                                        {{ ucfirst(str_replace('_', ' ', $contractor['status'])) }}
                                    </flux:badge>
                                </flux:table.cell>
                                <flux:table.cell align="center">
                                    <flux:badge size="sm" inset="top bottom">
                                        {{ $contractor['drifted_workers'] }} {{ Str::plural('worker', $contractor['drifted_workers']) }}
                                    </flux:badge>
                                </flux:table.cell>
                                <flux:table.cell align="center">
                                    <flux:button variant="ghost" size="sm" icon="arrow-path-rounded-square" icon-variant="micro" wire:click="openResyncModal('{{ $contractor['clab_no'] }}')">
                                        Review & Re-sync
                                    </flux:button>
                                </flux:table.cell>
                            </flux:table.rows>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
                @elseif($activeTab === 'out_of_sync' && $outOfSyncLoaded)
                <div class="py-12 text-center">
                    <div class="flex flex-col items-center gap-3">
                        <div class="rounded-full bg-green-100 dark:bg-green-900/30 p-4">
                            <flux:icon.check-circle class="size-10 text-green-600 dark:text-green-400" />
                        </div>
                        <p class="text-sm font-medium text-zinc-700 dark:text-zinc-300">All generated timesheets match the latest OT data for this period.</p>
                    </div>
                </div>
                @endif
                </div>
            </flux:tab.panel>
        </flux:tab.group>

        <!-- Pagination -->
        @unless($activeTab === 'not_submitted' && $this->notSubmitLocked)
            <flux:pagination :paginator="$this->missingPaginated" class="mt-5" />
        @endunless
    </flux:card>

    @if($missingContractors->count() === 0)
    <flux:card class="p-12 text-center dark:bg-zinc-900 rounded-lg">
        <div class="flex flex-col items-center gap-4">
            <div class="rounded-full bg-green-100 dark:bg-green-900/30 p-4">
                <flux:icon.check-circle class="size-12 text-green-600 dark:text-green-400" />
            </div>
            <div>
                <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">All Complete!</h3>
                <p class="text-sm text-zinc-600 dark:text-zinc-400 mt-2">
                    Every contractor with active workers has submitted and paid their payroll for {{ \Carbon\Carbon::create($selectedYear, $selectedMonth, 1)->format('F Y') }}.
                </p>
            </div>
        </div>
    </flux:card>
    @endif

    <!-- Remind Modal -->
    @if($showRemindModal && $selectedContractor)
        <flux:modal wire:model="showRemindModal" class="w-full">
            <div class="space-y-3 sm:space-y-4 p-2 sm:p-6">
                <div>
                    <h2 class="text-lg sm:text-xl font-bold text-zinc-900 dark:text-zinc-100">
                        Send Reminder
                    </h2>
                    <p class="text-xs sm:text-sm text-zinc-600 dark:text-zinc-400 mt-1">
                        Send a reminder to contractor about pending payroll submission
                    </p>
                </div>

                <!-- Contractor Info -->
                <flux:card class="p-3 sm:p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800">
                    <div class="flex items-start gap-3">
                        <flux:icon.building-office class="size-6 sm:size-8 text-blue-600 dark:text-blue-400 flex-shrink-0" />
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold text-zinc-900 dark:text-zinc-100 truncate">{{ $selectedContractor['name'] }}</p>
                            <p class="text-xs text-zinc-600 dark:text-zinc-400">CLAB No: {{ $selectedContractor['clab_no'] }}</p>
                            <div class="flex flex-col sm:flex-row sm:gap-3 mt-1 space-y-1 sm:space-y-0">
                                @if($selectedContractor['email'])
                                    @php
                                        // Extract first email if multiple emails separated by comma or semicolon
                                        $firstEmail = trim(preg_split('/[,;]/', $selectedContractor['email'])[0]);
                                    @endphp
                                    <div class="flex items-center gap-1 text-xs text-zinc-600 dark:text-zinc-400 truncate">
                                        <flux:icon.envelope class="size-3 flex-shrink-0" />
                                        <span class="truncate">{{ $firstEmail }}</span>
                                    </div>
                                @endif
                                @if($selectedContractor['phone'])
                                    <div class="flex items-center gap-1 text-xs text-zinc-600 dark:text-zinc-400">
                                        <flux:icon.phone class="size-3 flex-shrink-0" />
                                        <span>{{ $selectedContractor['phone'] }}</span>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </flux:card>

                <!-- Pending Info -->
                <div class="rounded-lg bg-orange-50 dark:bg-orange-900/20 p-3 sm:p-4 border border-orange-200 dark:border-orange-800">
                    <div class="flex items-start gap-2">
                        <flux:icon.exclamation-triangle class="size-5 text-orange-600 dark:text-orange-400 flex-shrink-0 mt-0.5" />
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-orange-900 dark:text-orange-100">
                                {{ $selectedContractor['active_workers'] }} of {{ $selectedContractor['total_workers'] }} workers with issues
                            </p>
                            <div class="text-xs text-orange-700 dark:text-orange-300 mt-1">
                                @if($selectedContractor['not_submitted'] > 0)
                                    <div>{{ $selectedContractor['not_submitted'] }} not submitted</div>
                                @endif
                                @if($selectedContractor['submitted_not_paid'] > 0)
                                    <div>{{ $selectedContractor['submitted_not_paid'] }} submitted but not paid</div>
                                @endif
                                <div class="mt-1">Period: {{ \Carbon\Carbon::create($selectedYear, $selectedMonth, 1)->format('F Y') }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Past Reminders History -->
                @if($pastReminders->count() > 0)
                    <div>
                        <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100 mb-2">
                            Past Reminders ({{ $pastReminders->count() }})
                        </h3>
                        <div class="space-y-2 max-h-32 sm:max-h-40 overflow-y-auto rounded-lg border border-zinc-200 dark:border-zinc-700 p-2 bg-zinc-50 dark:bg-zinc-800/50">
                            @foreach($pastReminders as $reminder)
                                <div class="bg-white dark:bg-zinc-900 rounded-lg p-2 border border-zinc-200 dark:border-zinc-700">
                                    <div class="flex flex-col sm:flex-row sm:items-center gap-1 sm:gap-2 text-xs">
                                        <div class="flex items-center gap-1">
                                            <flux:icon.clock class="size-3 text-zinc-500 dark:text-zinc-400 flex-shrink-0" />
                                            <span class="font-medium text-zinc-900 dark:text-zinc-100">
                                                {{ $reminder->created_at->format('M d, Y h:i A') }}
                                            </span>
                                        </div>
                                        <div class="flex items-center gap-1 pl-4 sm:pl-0">
                                            <span class="text-zinc-400 hidden sm:inline">•</span>
                                            <flux:icon.user class="size-3 text-zinc-500 dark:text-zinc-400 flex-shrink-0" />
                                            <span class="text-zinc-600 dark:text-zinc-400">
                                                {{ $reminder->sent_by ?? 'System' }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <!-- Message -->
                <div>
                    <flux:textarea
                        wire:model="reminderMessage"
                        label="Reminder Message"
                        rows="8"
                        resize="vertical"
                        placeholder="Enter your reminder message..."
                        class="text-sm"
                    />
                </div>

                <!-- Actions -->
                <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-2 pt-4 border-t border-zinc-200 dark:border-zinc-700">
                    <flux:button wire:click="closeRemindModal" variant="ghost" class="w-full sm:w-auto">Cancel</flux:button>
                    <flux:button wire:click="sendReminder" variant="primary" icon="paper-airplane" class="w-full sm:w-auto">
                        Send Reminder
                    </flux:button>
                </div>
            </div>
        </flux:modal>
    @endif

    <!-- Re-sync Modal -->
    @if($showResyncModal && $resyncContractor)
        <flux:modal wire:model="showResyncModal" class="w-full max-w-2xl">
            <div class="space-y-4 p-4 sm:p-6">
                <div>
                    <h2 class="text-lg sm:text-xl font-bold text-zinc-900 dark:text-zinc-100">
                        Review & Re-sync Timesheet
                    </h2>
                    <p class="text-xs sm:text-sm text-zinc-600 dark:text-zinc-400 mt-1">
                        {{ $resyncContractor['name'] }} ({{ $resyncContractor['clab_no'] }}) — {{ \Carbon\Carbon::create($selectedYear, $selectedMonth, 1)->format('F Y') }}
                    </p>
                </div>

                <div class="space-y-3 max-h-96 overflow-y-auto">
                    @foreach($resyncContractor['drifts'] as $drift)
                        <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-3">
                            <div class="font-medium text-sm text-zinc-900 dark:text-zinc-100">
                                {{ $drift['worker_name'] }}
                                <span class="text-xs text-zinc-500">{{ $drift['worker_passport'] }}</span>
                            </div>
                            <div class="mt-2 space-y-1.5 text-xs">
                                @foreach($drift['changes'] as $field => $change)
                                    @if($field === 'transactions')
                                        <div>
                                            <div class="text-zinc-500 dark:text-zinc-400 mb-1">Transactions</div>
                                            <div class="grid grid-cols-2 gap-2">
                                                <div class="rounded bg-red-50 dark:bg-red-900/20 p-2">
                                                    <div class="text-[10px] uppercase text-red-600 dark:text-red-400 mb-1">In timesheet</div>
                                                    @forelse($change['before'] as $line)
                                                        <div class="text-zinc-700 dark:text-zinc-300">{{ $line }}</div>
                                                    @empty
                                                        <div class="text-zinc-400">None</div>
                                                    @endforelse
                                                </div>
                                                <div class="rounded bg-green-50 dark:bg-green-900/20 p-2">
                                                    <div class="text-[10px] uppercase text-green-600 dark:text-green-400 mb-1">Latest (client)</div>
                                                    @forelse($change['after'] as $line)
                                                        <div class="text-zinc-700 dark:text-zinc-300">{{ $line }}</div>
                                                    @empty
                                                        <div class="text-zinc-400">None</div>
                                                    @endforelse
                                                </div>
                                            </div>
                                        </div>
                                    @else
                                        <div class="flex items-center gap-2">
                                            <span class="text-zinc-500 dark:text-zinc-400 w-28">{{ ucwords(str_replace(['ot_', '_'], ['OT ', ' '], $field)) }}</span>
                                            <span class="text-red-600 dark:text-red-400 line-through">{{ rtrim(rtrim(number_format($change['before'], 2), '0'), '.') }}h</span>
                                            <flux:icon.arrow-right class="size-3 text-zinc-400" />
                                            <span class="text-green-600 dark:text-green-400 font-medium">{{ rtrim(rtrim(number_format($change['after'], 2), '0'), '.') }}h</span>
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-2 pt-4 border-t border-zinc-200 dark:border-zinc-700">
                    <flux:button wire:click="closeResyncModal" variant="ghost" class="w-full sm:w-auto">Cancel</flux:button>
                    <flux:button wire:click="performResync" variant="primary" icon="arrow-path-rounded-square" class="w-full sm:w-auto">
                        Re-sync Timesheet
                    </flux:button>
                </div>
            </div>
        </flux:modal>
    @endif

    <!-- Bulk Submit Modal -->
    @if($showBulkSubmitModal && $bulkSubmitContractor)
        <flux:modal wire:model="showBulkSubmitModal" class="w-full max-w-lg">
            <div class="space-y-4 p-4 sm:p-6">
                <div>
                    <h2 class="text-lg sm:text-xl font-bold text-zinc-900 dark:text-zinc-100">
                        Create Draft Submission
                    </h2>
                    <p class="text-xs sm:text-sm text-zinc-600 dark:text-zinc-400 mt-1">
                        Create a draft payroll submission on behalf of a contractor.
                    </p>
                </div>

                <div class="p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg">
                    <p class="text-sm text-zinc-700 dark:text-zinc-300">
                        {!! $bulkSubmitMessage !!}
                    </p>
                </div>

                <!-- Actions -->
                <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-2 pt-4 border-t border-zinc-200 dark:border-zinc-700">
                    <flux:button wire:click="closeBulkSubmitModal" variant="ghost" class="w-full sm:w-auto">Cancel</flux:button>
                    <flux:button wire:click="performBulkSubmission" variant="primary" icon="document-plus" class="w-full sm:w-auto">
                        Create Draft
                    </flux:button>
                </div>
            </div>
        </flux:modal>
    @endif

</div>
