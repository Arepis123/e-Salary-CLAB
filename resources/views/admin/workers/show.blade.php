@php
    // Contract information (passed from controller)
    $inactiveRecord = \App\Models\InactiveWorker::where('worker_id', $worker->wkr_id)->first();
    $isManuallyInactive = $inactiveRecord !== null;
    $contractActive = $contract && $contract->isActive() && !$isManuallyInactive;
    $daysRemaining = $contract ? $contract->daysRemaining() : 0;

    // Get payroll history for this worker
    $payrollHistory = \App\Models\PayrollWorker::where('worker_id', $worker->wkr_id)
        ->whereHas('payrollSubmission', function($query) {
            $query->where('status', '!=', 'draft');
        })
        ->with(['payrollSubmission' => function($query) {
            $query->orderBy('year', 'desc')->orderBy('month', 'desc');
        }])
        ->get()
        ->sortByDesc(function($payrollWorker) {
            return $payrollWorker->payrollSubmission->year * 100 + $payrollWorker->payrollSubmission->month;
        })
        ->take(6);
@endphp

<x-layouts.app :title="__('Worker Details')">
    <div class="flex h-full w-full flex-1 flex-col gap-6">
        <!-- Header with Back Button -->
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div>
                    <div class="flex items-center gap-3">
                        <h1 class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">{{ $worker->name }}</h1>
                        @if($contractActive)
                            <flux:badge color="green">Active Contract</flux:badge>
                        @else
                            <flux:badge color="zinc">Inactive</flux:badge>
                        @endif
                    </div>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">Worker ID: {{ $worker->wkr_id }} • Passport: {{ $worker->ic_number }}</p>
                </div>
            </div>
            <div>
                <flux:button variant="filled" icon="arrow-left" href="{{ route('workers') }}" wire:navigate>
                    Back to Workers
                </flux:button>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <!-- Main Information (Left - 2 columns) -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Personal Information -->
                <flux:card class="p-6 dark:bg-zinc-900 rounded-lg">
                    <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100 mb-4">Personal Information</h2>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <p class="text-sm text-zinc-600 dark:text-zinc-400">Full Name</p>
                            <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $worker->name }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-zinc-600 dark:text-zinc-400">Passport Number</p>
                            <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $worker->ic_number }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-zinc-600 dark:text-zinc-400">Passport Expiry Date</p>
                            @php
                                $passportExpired = $worker->wkr_passexp && $worker->wkr_passexp->isPast();
                                $passportExpiringSoon = $worker->wkr_passexp && $worker->wkr_passexp->isFuture() && now()->diffInDays($worker->wkr_passexp, false) <= 90;
                            @endphp
                            <p class="text-sm font-medium {{ $passportExpired ? 'text-red-600 dark:text-red-400' : ($passportExpiringSoon ? 'text-orange-600 dark:text-orange-400' : 'text-zinc-900 dark:text-zinc-100') }}">
                                @if($worker->wkr_passexp)
                                    {{ $worker->wkr_passexp->format('F d, Y') }}
                                    @if($passportExpired)
                                        <span class="text-xs">(Expired)</span>
                                    @elseif($passportExpiringSoon)
                                        <span class="text-xs">(Expiring Soon)</span>
                                    @endif
                                @else
                                    -
                                @endif
                            </p>
                        </div>
                        <div>
                            <p class="text-sm text-zinc-600 dark:text-zinc-400">Permit Expiry Date</p>
                            @php
                                $permitExpired = $worker->wkr_permitexp && $worker->wkr_permitexp->isPast();
                                $permitExpiringSoon = $worker->wkr_permitexp && $worker->wkr_permitexp->isFuture() && now()->diffInDays($worker->wkr_permitexp, false) <= 60;
                            @endphp
                            <p class="text-sm font-medium {{ $permitExpired ? 'text-red-600 dark:text-red-400' : ($permitExpiringSoon ? 'text-orange-600 dark:text-orange-400' : 'text-zinc-900 dark:text-zinc-100') }}">
                                @if($worker->wkr_permitexp)
                                    {{ $worker->wkr_permitexp->format('F d, Y') }}
                                    @if($permitExpired)
                                        <span class="text-xs">(Expired)</span>
                                    @elseif($permitExpiringSoon)
                                        <span class="text-xs">(Expiring Soon)</span>
                                    @endif
                                @else
                                    -
                                @endif
                            </p>
                        </div>
                        <div>
                            <p class="text-sm text-zinc-600 dark:text-zinc-400">Position/Trade</p>
                            <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $worker->position ?? 'General Worker' }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-zinc-600 dark:text-zinc-400">Nationality</p>
                            <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                @if($worker->country)
                                    {{ $worker->country->cty_desc }}
                                @else
                                    -
                                @endif
                            </p>
                        </div>
                        <div>
                            <p class="text-sm text-zinc-600 dark:text-zinc-400">Gender</p>
                            <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                @if($worker->wkr_gender == 1)
                                    Male
                                @elseif($worker->wkr_gender == 2)
                                    Female
                                @else
                                    {{ $worker->wkr_gender ?? '-' }}
                                @endif
                            </p>
                        </div>
                        <div>
                            <p class="text-sm text-zinc-600 dark:text-zinc-400">Phone</p>
                            <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $worker->phone ?? '-' }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-zinc-600 dark:text-zinc-400">SOCSO Number</p>
                            <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $worker->wkr_sosco_id ?? '-' }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-zinc-600 dark:text-zinc-400">KWSP Number</p>
                            <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $worker->wkr_kwsp ?? '-' }}</p>
                        </div>
                    </div>
                </flux:card>

                <!-- Contract Information -->
                @if($contract)
                    <flux:card class="p-6 dark:bg-zinc-900 rounded-lg">
                        <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100 mb-4">Contract Information</h2>
                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <p class="text-sm text-zinc-600 dark:text-zinc-400">Contract Start Date</p>
                                <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $contract->con_start->format('F d, Y') }}</p>
                            </div>
                            <div>
                                <p class="text-sm text-zinc-600 dark:text-zinc-400">Contract End Date</p>
                                <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $contract->con_end->format('F d, Y') }}</p>
                            </div>
                            <div>
                                <p class="text-sm text-zinc-600 dark:text-zinc-400">Contract Period</p>
                                <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $contract->con_period }} months</p>
                            </div>
                            @if(!$isManuallyInactive && $daysRemaining > 0)
                            <div>
                                <p class="text-sm text-zinc-600 dark:text-zinc-400">Days Remaining</p>
                                <p class="text-sm font-medium {{ $daysRemaining < 30 ? 'text-orange-600 dark:text-orange-400' : 'text-zinc-900 dark:text-zinc-100' }}">
                                    {{ $daysRemaining }} days
                                    @if($daysRemaining < 30)
                                        <span class="text-xs">(Expiring Soon)</span>
                                    @endif
                                </p>
                            </div>
                            @endif
                            <div>
                                <p class="text-sm text-zinc-600 dark:text-zinc-400">Contract Status</p>
                                <div class="mt-1">
                                    @if($contractActive)
                                        <flux:badge color="green">Active</flux:badge>
                                    @elseif($isManuallyInactive)
                                        <flux:badge color="red">Terminated</flux:badge>
                                    @else
                                        <flux:badge color="red">Expired</flux:badge>
                                    @endif
                                </div>
                            </div>

                            @if($isManuallyInactive)
                                <div>
                                    <p class="text-sm text-zinc-600 dark:text-zinc-400">Termination Date</p>
                                    <p class="text-sm font-medium text-red-600 dark:text-red-400">
                                        {{ $inactiveRecord->deactivated_at->format('F d, Y') }}
                                    </p>
                                </div>
                                @if($inactiveRecord->reason)
                                    <div class="md:col-span-2">
                                        <p class="text-sm text-zinc-600 dark:text-zinc-400">Termination Reason</p>
                                        <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $inactiveRecord->reason }}</p>
                                    </div>
                                @endif
                            @endif
                        </div>

                        @if($isManuallyInactive)
                            <div class="mt-4 p-4 bg-red-50 dark:bg-red-900/20 rounded-lg border border-red-200 dark:border-red-800">
                                <div class="flex gap-3">
                                    <flux:icon.x-circle class="size-5 flex-shrink-0 text-red-600 dark:text-red-400" />
                                    <div>
                                        <p class="text-sm font-medium text-red-900 dark:text-red-100">Worker Terminated</p>
                                        <p class="text-xs text-red-700 dark:text-red-300 mt-1">
                                            This worker was terminated on {{ $inactiveRecord->deactivated_at->format('F d, Y') }} and is no longer active in the payroll system.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        @elseif($daysRemaining > 0 && $daysRemaining < 30)
                            <div class="mt-4 p-4 bg-orange-50 dark:bg-orange-900/20 rounded-lg border border-orange-200 dark:border-orange-800">
                                <div class="flex gap-3">
                                    <flux:icon.exclamation-triangle class="size-5 flex-shrink-0 text-orange-600 dark:text-orange-400" />
                                    <div>
                                        <p class="text-sm font-medium text-orange-900 dark:text-orange-100">Contract Expiring Soon</p>
                                        <p class="text-xs text-orange-700 dark:text-orange-300 mt-1">
                                            This worker's contract will expire in {{ $daysRemaining }} days.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </flux:card>
                @endif
            </div>

            <!-- Sidebar (Right - 1 column) -->
            <div class="space-y-6">
                <!-- Next of Kin Information -->
                <flux:card class="p-6 dark:bg-zinc-900 rounded-lg">
                    <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100 mb-4">Next of Kin</h2>
                    <div class="space-y-3">
                        @if($worker->wkr_next_of_kin || $worker->wkr_relationship || $worker->wkr_homeaddr)
                            @if($worker->wkr_next_of_kin)
                                <div>
                                    <p class="text-sm text-zinc-600 dark:text-zinc-400">Name</p>
                                    <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $worker->wkr_next_of_kin }}</p>
                                </div>
                            @endif

                            @if($worker->wkr_relationship)
                                <div>
                                    <p class="text-sm text-zinc-600 dark:text-zinc-400">Relationship</p>
                                    <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $worker->wkr_relationship }}</p>
                                </div>
                            @endif

                            @if($worker->wkr_homeaddr)
                                <div>
                                    <p class="text-sm text-zinc-600 dark:text-zinc-400">Home Address</p>
                                    <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $worker->wkr_homeaddr }}</p>
                                </div>
                            @endif
                        @else
                            <div class="text-center py-4">
                                <p class="text-sm text-zinc-600 dark:text-zinc-400">No next of kin information available</p>
                            </div>
                        @endif
                    </div>
                </flux:card>

                <!-- Bank Details -->
                <flux:card class="p-6 dark:bg-zinc-900 rounded-lg">
                    <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100 mb-4">Bank Details</h2>
                    @php
                        // A worker can have several rows in wkr_bank; latestBank is the current one.
                        $bank = $worker->latestBank;
                    @endphp
                    <div class="space-y-3">
                        @if($bank)
                            <div>
                                <p class="text-sm text-zinc-600 dark:text-zinc-400">Bank Name</p>
                                <div class="mt-1">
                                    <flux:badge :color="\App\Models\WorkerBank::colorFor($bank->bank_name)" size="sm">{{ $bank->bank_name ?: '-' }}</flux:badge>
                                </div>
                            </div>

                            <div>
                                <p class="text-sm text-zinc-600 dark:text-zinc-400">Account Number</p>
                                <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100 break-all">{{ $bank->account_no ?: '-' }}</p>
                            </div>

                            @if($bank->type)
                                <div>
                                    <p class="text-sm text-zinc-600 dark:text-zinc-400">Account Type</p>
                                    <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ ucfirst(str_replace('-', ' ', $bank->type)) }}</p>
                                </div>
                            @endif

                            @if($bank->created_at)
                                <div>
                                    <p class="text-sm text-zinc-600 dark:text-zinc-400">Last Updated</p>
                                    <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $bank->created_at->format('F d, Y') }}</p>
                                </div>
                            @endif
                        @else
                            <div class="text-center py-4">
                                <p class="text-sm text-zinc-600 dark:text-zinc-400">No bank details on record</p>
                                <p class="text-xs text-zinc-500 dark:text-zinc-500 mt-1">Maintained in the worker registration system</p>
                            </div>
                        @endif
                    </div>
                </flux:card>
            </div>
        </div>

        <!-- Payroll History -->
        <flux:card class="p-6 dark:bg-zinc-900 rounded-lg">
            <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100 mb-4">Payroll History</h2>

            @if($payrollHistory->count() > 0)
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b border-zinc-200 dark:border-zinc-700">
                                <th class="pb-3 text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Period</th>
                                <th class="pb-3 text-right text-xs font-medium text-zinc-600 dark:text-zinc-400">Basic</th>
                                <th class="pb-3 text-right text-xs font-medium text-zinc-600 dark:text-zinc-400">OT Normal</th>
                                <th class="pb-3 text-right text-xs font-medium text-zinc-600 dark:text-zinc-400">OT Rest</th>
                                <th class="pb-3 text-right text-xs font-medium text-zinc-600 dark:text-zinc-400">OT Public</th>
                                <th class="pb-3 text-right text-xs font-medium text-zinc-600 dark:text-zinc-400">Earning</th>
                                <th class="pb-3 text-right text-xs font-medium text-zinc-600 dark:text-zinc-400">Deductions</th>
                                <th class="pb-3 text-center text-xs font-medium text-zinc-600 dark:text-zinc-400">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                            @foreach($payrollHistory as $payroll)
                                <tr>
                                    <td class="py-3 text-sm text-zinc-900 dark:text-zinc-100">
                                        {{ $payroll->payrollSubmission->month_year }}
                                    </td>
                                    <td class="py-3 text-right text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                        RM {{ number_format($payroll->basic_salary, 2) }}
                                    </td>
                                    <td class="py-3 text-right text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                        @if($payroll->ot_normal_hours > 0)
                                            {{ number_format($payroll->ot_normal_hours, 2) }} hrs
                                        @else
                                            <span class="text-zinc-400 dark:text-zinc-500">-</span>
                                        @endif
                                    </td>
                                    <td class="py-3 text-right text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                        @if($payroll->ot_rest_hours > 0)
                                            {{ number_format($payroll->ot_rest_hours, 2) }} hrs
                                        @else
                                            <span class="text-zinc-400 dark:text-zinc-500">-</span>
                                        @endif
                                    </td>
                                    <td class="py-3 text-right text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                        @if($payroll->ot_public_hours > 0)
                                            {{ number_format($payroll->ot_public_hours, 2) }} hrs
                                        @else
                                            <span class="text-zinc-400 dark:text-zinc-500">-</span>
                                        @endif
                                    </td>
                                    <td class="py-3 text-right text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                        @php
                                            // Earning = Allowance + Backpay + Medical Claim
                                            $totalEarning = $payroll->total_transaction_earnings;
                                        @endphp
                                        @if($totalEarning > 0)
                                            RM {{ number_format($totalEarning, 2) }}
                                        @else
                                            <span class="text-zinc-400 dark:text-zinc-500">-</span>
                                        @endif
                                    </td>
                                    <td class="py-3 text-right text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                        @php
                                            // Deduction = Advance Payment + Deductions + NPL + Accommodation
                                            $totalTransactionDeductions = $payroll->total_transaction_deductions;
                                        @endphp
                                        @if($totalTransactionDeductions > 0)
                                            -RM {{ number_format($totalTransactionDeductions, 2) }}
                                        @else
                                            <span class="text-zinc-400 dark:text-zinc-500">-</span>
                                        @endif
                                    </td>
                                    <td class="py-3 text-center">
                                        @if($payroll->payrollSubmission->status === 'paid')
                                            <flux:badge color="green" size="sm">Paid</flux:badge>
                                        @elseif($payroll->payrollSubmission->status === 'pending_payment')
                                            <flux:badge color="yellow" size="sm">Pending</flux:badge>
                                        @elseif($payroll->payrollSubmission->status === 'overdue')
                                            <flux:badge color="red" size="sm">Overdue</flux:badge>
                                        @else
                                            <flux:badge color="zinc" size="sm">{{ ucfirst($payroll->payrollSubmission->status) }}</flux:badge>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="mt-4 space-y-2">
                    <p class="text-xs text-zinc-500 dark:text-zinc-500">
                        Showing last 6 months of payroll records
                    </p>
                </div>
            @else
                <div class="text-center py-8">
                    <flux:icon.document-text class="size-12 mx-auto text-zinc-400 dark:text-zinc-600 mb-2" />
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">No payroll history available</p>
                    <p class="text-xs text-zinc-500 dark:text-zinc-500 mt-1">Payroll records will appear here once submitted</p>
                </div>
            @endif
        </flux:card>
    </div>
</x-layouts.app>
