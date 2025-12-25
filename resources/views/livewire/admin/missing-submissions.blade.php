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
                            <flux:table.cell align="center">{{ $this->historicalPagination['from'] + $index }}</flux:table.cell>

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
            @if($this->historicalPagination['total'] > $historicalPerPage)
            <div class="mt-4 flex items-center justify-between">
                <div class="text-sm text-zinc-600 dark:text-zinc-400">
                    Showing {{ $this->historicalPagination['from'] }} to {{ $this->historicalPagination['to'] }} of {{ $this->historicalPagination['total'] }} contractors
                </div>
                <div class="flex gap-2">
                    <flux:button
                        wire:click="$set('historicalPage', {{ $this->historicalPagination['current_page'] - 1 }})"
                        variant="ghost"
                        size="sm"
                        icon="chevron-left"
                        icon-variant="micro"
                        :disabled="$this->historicalPagination['current_page'] === 1"
                    >
                        Previous
                    </flux:button>
                    <flux:button
                        wire:click="$set('historicalPage', {{ $this->historicalPagination['current_page'] + 1 }})"
                        variant="ghost"
                        size="sm"
                        icon="chevron-right"
                        icon-trailing
                        icon-variant="micro"
                        :disabled="$this->historicalPagination['current_page'] >= $this->historicalPagination['last_page']"
                    >
                        Next
                    </flux:button>
                </div>
            </div>
            @endif
        </div>
        @endif
    </flux:card>
    @endif

    <!-- Contractors Without Submission Table -->
    @if($missingContractors->count() > 0)
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
                @if($missingContractors->count() > 0)
                    <flux:button variant="ghost" size="sm" icon="arrow-down-tray" icon-variant="outline" wire:click="exportCurrentPeriodDetailed">
                        Export Details
                    </flux:button>
                    <flux:button variant="ghost" size="sm" icon="document-text" icon-variant="outline" wire:click="export">
                        Export Summary
                    </flux:button>
                @endif
            </div>
        </div>

        <flux:table>
            <flux:table.columns>
                <flux:table.column align="center"><span class="text-center text-xs font-medium text-zinc-600 dark:text-zinc-400">No</span></flux:table.column>
                <flux:table.column><span class="text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">CLAB No</span></flux:table.column>
                <flux:table.column><span class="text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Contractor Name</span></flux:table.column>
                <flux:table.column><span class="text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Contact</span></flux:table.column>
                <flux:table.column align="center"><span class="text-center text-xs font-medium text-zinc-600 dark:text-zinc-400">Active Workers</span></flux:table.column>
                <flux:table.column align="center"><span class="text-center text-xs font-medium text-zinc-600 dark:text-zinc-400">Reminders Sent</span></flux:table.column>
                <flux:table.column align="center"><span class="text-center text-xs font-medium text-zinc-600 dark:text-zinc-400">Actions</span></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach($this->missingPaginated as $index => $contractor)
                    <flux:table.rows :key="$contractor['clab_no']">
                        <flux:table.cell align="center">{{ $this->missingPagination['from'] + $index }}</flux:table.cell>

                        <flux:table.cell variant="strong">
                            {{ $contractor['clab_no'] }}
                        </flux:table.cell>

                        <flux:table.cell variant="strong">
                            {{ $contractor['name'] }}
                        </flux:table.cell>

                        <flux:table.cell>
                            <div class="text-sm">
                                @if($contractor['email'])
                                    @php
                                        // Extract first email if multiple emails separated by comma or semicolon
                                        $firstEmail = trim(preg_split('/[,;]/', $contractor['email'])[0]);
                                    @endphp
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
                                <flux:badge color="orange" size="sm" inset="top bottom">
                                    {{ $contractor['active_workers'] }} of {{ $contractor['total_workers'] }} with issues
                                </flux:badge>
                                <div class="flex flex-col gap-0.5 text-xs text-zinc-500 dark:text-zinc-400 mt-1">
                                    @if($contractor['not_submitted'] > 0)
                                        <span class="text-red-600 dark:text-red-400">
                                            {{ $contractor['not_submitted'] }} not submitted
                                        </span>
                                    @endif
                                    @if($contractor['submitted_not_paid'] > 0)
                                        <span class="text-amber-600 dark:text-amber-400">
                                            {{ $contractor['submitted_not_paid'] }} not paid
                                        </span>
                                    @endif
                                    @if($contractor['total_workers'] - $contractor['active_workers'] > 0)
                                        <span class="text-green-600 dark:text-green-400">
                                            {{ $contractor['total_workers'] - $contractor['active_workers'] }} completed
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </flux:table.cell>

                        <flux:table.cell align="center">
                            <div class="flex flex-col items-center gap-1">
                                @if($contractor['reminders_sent'] > 0)
                                    <flux:badge color="blue" size="sm" inset="top bottom">
                                        {{ $contractor['reminders_sent'] }} {{ Str::plural('time', $contractor['reminders_sent']) }}
                                    </flux:badge>
                                    <span class="text-xs text-zinc-500 dark:text-zinc-400 mt-1">
                                        This period
                                    </span>
                                @else
                                    <flux:badge color="zinc" size="sm" inset="top bottom">
                                        No reminders
                                    </flux:badge>
                                @endif
                            </div>
                        </flux:table.cell>

                        <flux:table.cell align="center">
                            <flux:dropdown align="end">
                                <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" icon-variant="micro" inset="top bottom"></flux:button>

                                <flux:menu>
                                    <flux:menu.item icon="bell" wire:click="openRemindModal('{{ $contractor['clab_no'] }}')">
                                        Send Reminder
                                    </flux:menu.item>
                                    <flux:menu.item icon="paper-airplane" wire:click="openBulkSubmitModal('{{ $contractor['clab_no'] }}')">
                                        Submit on Behalf
                                    </flux:menu.item>
                                    <flux:menu.separator />
                                    <flux:menu.item icon="eye" href="{{ route('missing-submissions.detail', $contractor['clab_no']) }}" wire:navigate>
                                        View Details
                                    </flux:menu.item>
                                </flux:menu>
                            </flux:dropdown>
                        </flux:table.cell>
                    </flux:table.rows>
                @endforeach
            </flux:table.rows>
        </flux:table>

        <!-- Pagination -->
        @if($this->missingPagination['total'] > $currentPerPage)
        <div class="mt-4 flex items-center justify-between">
            <div class="text-sm text-zinc-600 dark:text-zinc-400">
                Showing {{ $this->missingPagination['from'] }} to {{ $this->missingPagination['to'] }} of {{ $this->missingPagination['total'] }} contractors
            </div>
            <div class="flex gap-2">
                <flux:button
                    wire:click="$set('currentPage', {{ $this->missingPagination['current_page'] - 1 }})"
                    variant="ghost"
                    size="sm"
                    icon="chevron-left"
                    icon-variant="micro"
                    :disabled="$this->missingPagination['current_page'] === 1"
                >
                    Previous
                </flux:button>
                <flux:button
                    wire:click="$set('currentPage', {{ $this->missingPagination['current_page'] + 1 }})"
                    variant="ghost"
                    size="sm"
                    icon="chevron-right"
                    icon-trailing
                    icon-variant="micro"
                    :disabled="$this->missingPagination['current_page'] >= $this->missingPagination['last_page']"
                >
                    Next
                </flux:button>
            </div>
        </div>
        @endif
    </flux:card>
    @else
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

    <!-- Bulk Submit Modal -->
    @if($showBulkSubmitModal && $bulkSubmitContractor)
        <flux:modal wire:model="showBulkSubmitModal" class="w-full max-w-lg">
            <div class="space-y-4 p-4 sm:p-6">
                <div>
                    <h2 class="text-lg sm:text-xl font-bold text-zinc-900 dark:text-zinc-100">
                        Submit on Behalf of Contractor
                    </h2>
                    <p class="text-xs sm:text-sm text-zinc-600 dark:text-zinc-400 mt-1">
                        Create and submit a payroll submission on behalf of a contractor.
                    </p>
                </div>

                <div class="p-4 bg-orange-50 dark:bg-orange-900/20 border border-orange-200 dark:border-orange-800 rounded-lg">
                    <p class="text-sm text-zinc-700 dark:text-zinc-300">
                        {!! $bulkSubmitMessage !!}
                    </p>
                </div>

                <!-- Actions -->
                <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-2 pt-4 border-t border-zinc-200 dark:border-zinc-700">
                    <flux:button wire:click="closeBulkSubmitModal" variant="ghost" class="w-full sm:w-auto">Cancel</flux:button>
                    <flux:button wire:click="performBulkSubmission" variant="primary" icon="paper-airplane" class="w-full sm:w-auto">
                        Confirm & Submit
                    </flux:button>
                </div>
            </div>
        </flux:modal>
    @endif
</div>
