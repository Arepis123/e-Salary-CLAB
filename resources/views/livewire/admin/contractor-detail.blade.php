<div class="flex h-full w-full flex-1 flex-col gap-6">
    <!-- Page Header -->
    <div class="flex items-center justify-between">
        <div>
            <div class="flex items-center gap-3">
                <div>
                    <h1 class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">{{ $contractor->name }}</h1>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">{{ $contractor->contractor_clab_no }}</p>
                </div>
            </div>
        </div>
        <div class="flex gap-2">
            <flux:button variant="outline" size="sm" icon="document-text" href="{{ route('payroll') }}?contractor={{ $contractor->contractor_clab_no }}">
                View Submissions
            </flux:button>
            <flux:button variant="outline" size="sm" icon="document" href="{{ route('invoices') }}?contractor={{ $contractor->contractor_clab_no }}">
                View Invoices
            </flux:button>
            <flux:button variant="outline" size="sm" icon="arrow-left" href="{{ route('contractors') }}">
                Back to Contractor
            </flux:button>
        </div>
    </div>

    <!-- Contractor Details Card -->
    <flux:card class="p-6 dark:bg-zinc-900 rounded-lg">
        <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100 mb-4">Contractor Information</h2>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="space-y-3">
                <div>
                    <label class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Company Name</label>
                    <p class="text-sm text-zinc-900 dark:text-zinc-100 mt-1">{{ $contractor->name }}</p>
                </div>
                <div>
                    <label class="text-xs font-medium text-zinc-600 dark:text-zinc-400">CLAB Number</label>
                    <p class="text-sm text-zinc-900 dark:text-zinc-100 mt-1">{{ $contractor->contractor_clab_no }}</p>
                </div>
                <div>
                    <label class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Email</label>
                    @php
                        // Extract first email if multiple emails separated by comma or semicolon
                        $firstEmail = trim(preg_split('/[,;]/', $contractor->email)[0]);
                    @endphp
                    <p class="text-sm text-zinc-900 dark:text-zinc-100 mt-1">{{ $firstEmail }}</p>
                </div>
                <div>
                    <label class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Phone</label>
                    <p class="text-sm text-zinc-900 dark:text-zinc-100 mt-1">{{ $contractor->phone ?? '-' }}</p>
                </div>
            </div>
            <div class="space-y-3">
                <div>
                    <label class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Person in Charge</label>
                    <p class="text-sm text-zinc-900 dark:text-zinc-100 mt-1">{{ $contractor->person_in_charge ?? '-' }}</p>
                </div>
                <div>
                    <label class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Registration Date</label>
                    <p class="text-sm text-zinc-900 dark:text-zinc-100 mt-1">{{ $contractor->created_at->format('M d, Y') }}</p>
                </div>
                <div>
                    <label class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Last Login</label>
                    <p class="text-sm text-zinc-900 dark:text-zinc-100 mt-1">
                        {{ $contractor->last_login_at ? $contractor->last_login_at->diffForHumans() : 'Never' }}
                    </p>
                </div>
                <div>
                    <label class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Status</label>
                    <div class="mt-1">
                        @if($contractor->email_verified_at)
                            <flux:badge color="green" size="sm">Verified</flux:badge>
                        @else
                            <flux:badge color="orange" size="sm">Unverified</flux:badge>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </flux:card>

    <!-- Statistics Cards -->
    <div class="grid gap-4 md:grid-cols-5">
        <flux:card class="space-y-2 p-4 sm:p-6 dark:bg-zinc-900 rounded-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">Total Submissions</p>
                    <p class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">{{ $stats['total_submissions'] }}</p>
                </div>
                <flux:icon.document-text class="size-8 text-blue-600 dark:text-blue-400" />
            </div>
        </flux:card>

        <flux:card class="space-y-2 p-4 sm:p-6 dark:bg-zinc-900 rounded-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">Total Workers</p>
                    <p class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">{{ $stats['total_workers'] }}</p>
                </div>
                <flux:icon.users class="size-8 text-purple-600 dark:text-purple-400" />
            </div>
        </flux:card>

        <flux:card class="space-y-2 p-4 sm:p-6 dark:bg-zinc-900 rounded-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">Pending</p>
                    <p class="text-2xl font-bold text-orange-600 dark:text-orange-400">{{ $stats['pending_submissions'] }}</p>
                </div>
                <flux:icon.clock class="size-8 text-orange-600 dark:text-orange-400" />
            </div>
        </flux:card>

        <flux:card class="space-y-2 p-4 sm:p-6 dark:bg-zinc-900 rounded-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">Total Paid</p>
                    <p class="text-2xl font-bold text-green-600 dark:text-green-400">RM {{ number_format($stats['total_paid'], 2) }}</p>
                </div>
                <flux:icon.check-circle class="size-8 text-green-600 dark:text-green-400" />
            </div>
        </flux:card>

        <flux:card class="space-y-2 p-4 sm:p-6 dark:bg-zinc-900 rounded-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">Outstanding</p>
                    <p class="text-2xl font-bold text-red-600 dark:text-red-400">RM {{ number_format($stats['total_outstanding'], 2) }}</p>
                </div>
                <flux:icon.exclamation-circle class="size-8 text-red-600 dark:text-red-400" />
            </div>
        </flux:card>
    </div>

    <!-- Tabs -->
    <div class="border-b border-zinc-200 dark:border-zinc-700">
        <nav class="-mb-px flex space-x-8">
            <button
                wire:click="setTab('workers')"
                class="border-b-2 py-4 px-1 text-sm font-medium transition-colors {{ $activeTab === 'workers' ? 'border-blue-500 text-blue-600 dark:text-blue-400' : 'border-transparent text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100 hover:border-zinc-300 dark:hover:border-zinc-600' }}"
            >
                <flux:icon.users class="size-4 inline mr-1" />
                Workers History
            </button>
            <button
                wire:click="setTab('payroll')"
                class="border-b-2 py-4 px-1 text-sm font-medium transition-colors {{ $activeTab === 'payroll' ? 'border-blue-500 text-blue-600 dark:text-blue-400' : 'border-transparent text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100 hover:border-zinc-300 dark:hover:border-zinc-600' }}"
            >
                <flux:icon.document-text class="size-4 inline mr-1" />
                Payroll History
            </button>
        </nav>
    </div>

    <!-- Tab Content -->
    @if($activeTab === 'workers')
        <!-- Workers History -->
        <flux:card class="p-4 sm:p-6 dark:bg-zinc-900 rounded-lg">
            <div class="mb-4">
                <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">Workers History</h2>
                <p class="text-sm text-zinc-600 dark:text-zinc-400">All workers submitted by this contractor</p>
            </div>

            <div class="overflow-x-auto">
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column><span class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Worker Name</span></flux:table.column>
                        <flux:table.column><span class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Passport</span></flux:table.column>
                        <flux:table.column><span class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Country</span></flux:table.column>
                        <flux:table.column><span class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Position</span></flux:table.column>
                        <flux:table.column><span class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Contract Period</span></flux:table.column>
                        <flux:table.column><span class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Last Submission</span></flux:table.column>
                        <flux:table.column><span class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Total Submissions</span></flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @forelse($workers as $worker)
                            @php
                                // Get the latest submission for this worker
                                $latestSubmission = App\Models\PayrollSubmission::where('id', $worker->latest_submission_id)->first();
                            @endphp
                            <flux:table.rows :key="$worker->worker_passport">
                                <flux:table.cell variant="strong">
                                    {{ $worker->worker_name }}
                                </flux:table.cell>
                                <flux:table.cell>
                                    {{ $worker->worker_passport }}
                                </flux:table.cell>
                                <flux:table.cell>
                                    {{ $worker->country }}
                                </flux:table.cell>
                                <flux:table.cell>
                                    {{ $worker->position }}
                                </flux:table.cell>
                                <flux:table.cell>
                                    @if($worker->contract_start && $worker->contract_end)
                                        {{ \Carbon\Carbon::parse($worker->contract_start)->format('M Y') }} - {{ \Carbon\Carbon::parse($worker->contract_end)->format('M Y') }}
                                    @else
                                        -
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell>
                                    {{ $latestSubmission->month_year ?? '-' }}
                                </flux:table.cell>
                                <flux:table.cell variant="strong">
                                    {{ $worker->total_submissions }}
                                </flux:table.cell>
                            </flux:table.rows>
                        @empty
                            <flux:table.rows>
                                <flux:table.cell variant="strong" colspan="7" class="text-center">
                                    No workers found for this contractor.
                                </flux:table.cell>
                            </flux:table.rows>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
            </div>

            <!-- Workers Pagination -->
            @if($workers->hasPages())
                <div class="mt-4 border-t border-zinc-200 dark:border-zinc-700 pt-4">
                    {{ $workers->links() }}
                </div>
            @endif
        </flux:card>
    @elseif($activeTab === 'payroll')
        <!-- Payroll History -->
        <flux:card class="p-4 sm:p-6 dark:bg-zinc-900 rounded-lg">
            <div class="mb-4">
                <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">Payroll History</h2>
                <p class="text-sm text-zinc-600 dark:text-zinc-400">All payroll submissions from this contractor</p>
            </div>

            <div class="overflow-x-auto">
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column><span class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Invoice #</span></flux:table.column>
                        <flux:table.column><span class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Period</span></flux:table.column>
                        <flux:table.column><span class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Workers</span></flux:table.column>
                        <flux:table.column><span class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Amount</span></flux:table.column>
                        <flux:table.column><span class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Status</span></flux:table.column>
                        <flux:table.column><span class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Submitted</span></flux:table.column>
                        <flux:table.column><span class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Actions</span></flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @forelse($payrollHistory as $submission)
                            <flux:table.rows :key="$submission->id">
                                <flux:table.cell variant="strong">
                                    INV-{{ str_pad($submission->id, 4, '0', STR_PAD_LEFT) }}
                                </flux:table.cell>
                                <flux:table.cell>
                                    {{ $submission->month_year }}
                                </flux:table.cell>
                                <flux:table.cell>
                                    {{ $submission->total_workers }}
                                </flux:table.cell>
                                <flux:table.cell variant="strong">
                                    @if($submission->hasAdminReview())
                                        RM {{ number_format($submission->client_total, 2) }}
                                        @if($submission->has_penalty)
                                            <span class="text-xs text-red-600 dark:text-red-400">
                                                (+{{ number_format($submission->penalty_amount, 2) }})
                                            </span>
                                        @endif
                                    @else
                                        <span class="text-sm text-orange-600 dark:text-orange-400">Pending Review</span>
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell>
                                    @if($submission->status === 'draft')
                                        <flux:badge color="zinc" size="sm">Draft</flux:badge>
                                    @elseif($submission->status === 'pending_payment')
                                        <flux:badge color="orange" size="sm">Pending</flux:badge>
                                    @elseif($submission->status === 'paid')
                                        <flux:badge color="green" size="sm">Paid</flux:badge>
                                    @elseif($submission->status === 'overdue')
                                        <flux:badge color="red" size="sm">Overdue</flux:badge>
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell>
                                    {{ $submission->submitted_at ? $submission->submitted_at->format('M d, Y') : '-' }}
                                </flux:table.cell>
                                <flux:table.cell>
                                    <flux:button
                                        variant="ghost"
                                        size="xs"
                                        icon="eye"
                                        href="{{ route('payroll.detail', $submission->id) }}"
                                    />
                                </flux:table.cell>
                            </flux:table.rows>
                        @empty
                            <flux:table.rows>
                                <flux:table.cell variant="strong" colspan="7" class="text-center">
                                    No payroll submissions found for this contractor.
                                </flux:table.cell>
                            </flux:table.rows>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
            </div>

            <!-- Payroll Pagination -->
            @if($payrollHistory->hasPages())
                <div class="mt-4 border-t border-zinc-200 dark:border-zinc-700 pt-4">
                    {{ $payrollHistory->links() }}
                </div>
            @endif
        </flux:card>
    @endif
</div>
