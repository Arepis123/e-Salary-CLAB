<div class="flex h-full w-full flex-1 flex-col gap-6">
    <!-- Page Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">Submission Details</h1>
            <p class="text-sm text-zinc-600 dark:text-zinc-400">Worker submission status for {{ now()->format('F Y') }}</p>
        </div>
        <flux:button variant="outline" href="{{ route('missing-submissions') }}" wire:navigate>
            <flux:icon.arrow-left class="size-4" />
            Back to List
        </flux:button>
    </div>

    <!-- Contractor Info Card -->
    <flux:card class="p-6 dark:bg-zinc-900 rounded-lg">
        <div class="flex items-start justify-between">
            <div class="flex items-center gap-4">
                <div class="rounded-full bg-blue-100 dark:bg-blue-900/30 p-3">
                    <flux:icon.building-office class="size-8 text-blue-600 dark:text-blue-400" />
                </div>
                <div>
                    <h2 class="text-xl font-semibold text-zinc-900 dark:text-zinc-100">{{ $contractor['name'] }}</h2>                    
                    <div class="flex gap-4 mt-2">
                        <div class="flex items-center gap-1">
                            <p class="text-sm text-zinc-600 dark:text-zinc-400 mt-1">CLAB No: {{ $contractor['clab_no'] }}</p>
                        </div>
                        @if($contractor['email'])
                            <div class="flex items-center gap-1 text-sm text-zinc-600 dark:text-zinc-400">
                                <flux:icon.envelope class="size-4" />
                                @php
                                    // Extract first email if multiple emails separated by comma or semicolon
                                    $firstEmail = trim(preg_split('/[,;]/', $contractor['email'])[0]);
                                @endphp
                                <span>{{ $firstEmail }}</span>
                            </div>
                        @endif
                        @if($contractor['phone'])
                            <div class="flex items-center gap-1 text-sm text-zinc-600 dark:text-zinc-400">
                                <flux:icon.phone class="size-4" />
                                <span>{{ $contractor['phone'] }}</span>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
            <div class="text-right">
                <p class="text-sm text-zinc-600 dark:text-zinc-400">Submission Progress</p>
                <p class="text-3xl font-bold text-blue-600 dark:text-blue-400">{{ $stats['submission_rate'] }}%</p>
            </div>
        </div>
    </flux:card>

    <!-- Statistics Cards -->
    <div class="grid gap-4 md:grid-cols-3">
        <flux:card class="space-y-2 p-4 sm:p-6 dark:bg-zinc-900 rounded-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">Total Workers</p>
                    <p class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">{{ $stats['total_workers'] }}</p>
                </div>
                <flux:icon.users class="size-8 text-blue-600 dark:text-blue-400" />
            </div>
        </flux:card>

        <flux:card class="space-y-2 p-4 sm:p-6 dark:bg-zinc-900 rounded-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">Submitted</p>
                    <p class="text-2xl font-bold text-green-600 dark:text-green-400 mt-1">{{ $stats['submitted_count'] }}</p>
                </div>
                <flux:icon.check-circle class="size-8 text-green-600 dark:text-green-400" />
            </div>
        </flux:card>

        <flux:card class="space-y-2 p-4 sm:p-6 dark:bg-zinc-900 rounded-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">Not Submitted</p>
                    <p class="text-2xl font-bold text-orange-600 dark:text-orange-400 mt-1">{{ $stats['unsubmitted_count'] }}</p>
                </div>
                <flux:icon.exclamation-triangle class="size-8 text-orange-600 dark:text-orange-400" />
            </div>
        </flux:card>
    </div>

    <!-- Unsubmitted Workers -->
    @if(count($unsubmittedWorkers) > 0)
    <flux:card class="p-4 sm:p-6 dark:bg-zinc-900 rounded-lg">
        <div class="mb-4 flex items-center justify-between">
            <div>
                <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">Workers Not Submitted</h2>
                <p class="text-sm text-zinc-600 dark:text-zinc-400 mt-1">
                    {{ count($unsubmittedWorkers) }} {{ Str::plural('worker', count($unsubmittedWorkers)) }} pending submission for <span class="font-semibold text-orange-600 dark:text-orange-400">{{ now()->format('F Y') }}</span>
                </p>
            </div>
            {{-- <flux:badge color="orange" size="lg">Requires Attention</flux:badge> --}}
        </div>

        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b border-zinc-200 dark:border-zinc-700">
                        <th class="pb-3 text-center text-xs font-medium text-zinc-600 dark:text-zinc-400">No</th>
                        <th class="pb-3 text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Worker Info</th>
                        <th class="pb-3 text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Passport</th>
                        <th class="pb-3 text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Position</th>
                        <th class="pb-3 text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Nationality</th>
                        <th class="pb-3 text-center text-xs font-medium text-zinc-600 dark:text-zinc-400">Passport Expiry</th>
                        <th class="pb-3 text-center text-xs font-medium text-zinc-600 dark:text-zinc-400">Permit Expiry</th>
                        <th class="pb-3 text-center text-xs font-medium text-zinc-600 dark:text-zinc-400">Missing Period</th>
                        <th class="pb-3 text-center text-xs font-medium text-zinc-600 dark:text-zinc-400">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @foreach($unsubmittedWorkers as $index => $worker)
                    <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                        <td class="py-3 text-center text-sm text-zinc-900 dark:text-zinc-100">{{ $index + 1 }}</td>
                        <td class="py-3">
                            <div class="flex items-center gap-3">
                                <flux:avatar size="sm" name="{{ $worker['name'] }}" color="auto" />
                                <div>
                                    <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $worker['name'] }}</div>
                                    <div class="text-xs text-zinc-500 dark:text-zinc-400">ID: {{ $worker['worker_id'] }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="py-3 text-sm text-zinc-900 dark:text-zinc-100">{{ $worker['passport'] }}</td>
                        <td class="py-3 text-sm text-zinc-600 dark:text-zinc-400">{{ $worker['position'] }}</td>
                        <td class="py-3 text-sm text-zinc-600 dark:text-zinc-400">{{ ucfirst(strtolower($worker['nationality'])) ?? 'N/A' }}</td>
                        <td class="py-3 text-center text-sm text-zinc-600 dark:text-zinc-400">{{ $worker['passport_expiry'] }}</td>
                        <td class="py-3 text-center text-sm text-zinc-600 dark:text-zinc-400">{{ $worker['permit_expiry'] }}</td>
                        <td class="py-3 text-center">
                            <div class="flex flex-col items-center gap-1">
                                <flux:badge color="zinc" size="sm" inset="top bottom">{{ now()->format('F Y') }}</flux:badge>
                                <span class="text-xs text-zinc-500 dark:text-zinc-400 mt-1">Not submitted</span>
                            </div>
                        </td>
                        <td class="py-3 text-center">
                            <flux:badge color="orange" size="sm" inset="top bottom" icon="clock">Pending</flux:badge>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </flux:card>
    @endif

    <!-- Submitted Workers -->
    @if(count($submittedWorkers) > 0)
    <flux:card class="p-4 sm:p-6 dark:bg-zinc-900 rounded-lg">
        <div class="mb-4 flex items-center justify-between">
            <div>
                <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">Workers Already Submitted</h2>
                <p class="text-sm text-zinc-600 dark:text-zinc-400 mt-1">
                    {{ count($submittedWorkers) }} {{ Str::plural('worker', count($submittedWorkers)) }} submitted for {{ now()->format('F Y') }}
                </p>
            </div>
            {{-- <flux:badge color="green" size="lg">Completed</flux:badge> --}}
        </div>

        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b border-zinc-200 dark:border-zinc-700">
                        <th class="pb-3 text-center text-xs font-medium text-zinc-600 dark:text-zinc-400">No</th>
                        <th class="pb-3 text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Worker Info</th>
                        <th class="pb-3 text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Passport</th>
                        <th class="pb-3 text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Position</th>
                        <th class="pb-3 text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Nationality</th>
                        <th class="pb-3 text-center text-xs font-medium text-zinc-600 dark:text-zinc-400">Passport Expiry</th>
                        <th class="pb-3 text-center text-xs font-medium text-zinc-600 dark:text-zinc-400">Permit Expiry</th>
                        <th class="pb-3 text-center text-xs font-medium text-zinc-600 dark:text-zinc-400">Submitted For</th>
                        <th class="pb-3 text-center text-xs font-medium text-zinc-600 dark:text-zinc-400">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @foreach($submittedWorkers as $index => $worker)
                    <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                        <td class="py-3 text-center text-sm text-zinc-900 dark:text-zinc-100">{{ $index + 1 }}</td>
                        <td class="py-3">
                            <div class="flex items-center gap-3">
                                <flux:avatar size="sm" name="{{ $worker['name'] }}" color="auto" />
                                <div>
                                    <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $worker['name'] }}</div>
                                    <div class="text-xs text-zinc-500 dark:text-zinc-400">ID: {{ $worker['worker_id'] }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="py-3 text-sm text-zinc-900 dark:text-zinc-100">{{ $worker['passport'] }}</td>
                        <td class="py-3 text-sm text-zinc-600 dark:text-zinc-400">{{ $worker['position'] }}</td>
                        <td class="py-3 text-sm text-zinc-600 dark:text-zinc-400">{{ ucfirst(strtolower($worker['nationality'])) ?? 'N/A' }}</td>
                        <td class="py-3 text-center text-sm text-zinc-600 dark:text-zinc-400">{{ $worker['passport_expiry'] }}</td>
                        <td class="py-3 text-center text-sm text-zinc-600 dark:text-zinc-400">{{ $worker['permit_expiry'] }}</td>
                        <td class="py-3 text-center">
                            <div class="flex flex-col items-center gap-1">
                                <flux:badge color="zinc" size="sm" inset="top bottom">{{ now()->format('F Y') }}</flux:badge>
                                <span class="text-xs text-zinc-500 dark:text-zinc-400 mt-1">Submitted</span>
                            </div>
                        </td>
                        <td class="py-3 text-center">
                            <flux:badge color="green" size="sm" icon="check" inset="top bottom">Completed</flux:badge>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </flux:card>
    @endif
</div>
