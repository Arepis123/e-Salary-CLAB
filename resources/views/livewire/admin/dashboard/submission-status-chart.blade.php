<div class="min-w-0" x-on:chart-section-clicked.window="$wire.loadSectionContractors($event.detail.index)">
    <flux:card class="p-4 sm:p-6 bg-white dark:bg-zinc-900 rounded-lg">
        <div class="mb-4 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">Contractor Submission Status</h2>
                <p class="text-sm text-zinc-600 dark:text-zinc-400 mt-1">Submission and payment status by period</p>
            </div>

            <!-- Period Selector -->
            <div class="flex gap-2 items-center">
                <flux:select wire:model.live="selectedMonth" variant="listbox" size="sm">
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

                <flux:select wire:model.live="selectedYear" variant="listbox" size="sm">
                    @for($year = now()->year; $year >= now()->year - 3; $year--)
                        <flux:select.option value="{{ $year }}">{{ $year }}</flux:select.option>
                    @endfor
                </flux:select>
            </div>
        </div>

        <div id="chartDataContainer"
            data-chart-labels='@json($contractorStatusChartData["labels"])'
            data-chart-data='@json($contractorStatusChartData["data"])'
            data-chart-colors='@json($contractorStatusChartData["colors"])'
            data-month="{{ $selectedMonth }}"
            data-year="{{ $selectedYear }}"
            style="display: none;"></div>
        <div class="relative h-64">
            <canvas id="contractorStatusChart" wire:ignore></canvas>
        </div>

        <!-- Summary info -->
        <div class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">
            Viewing: {{ \Carbon\Carbon::create($selectedYear, $selectedMonth, 1)->format('F Y') }}
            | Paid: {{ $contractorStatusChartData['data'][0] ?? 0 }}
            | Pending: {{ $contractorStatusChartData['data'][1] ?? 0 }}
            | Not Submitted: {{ $contractorStatusChartData['data'][2] ?? 0 }}
        </div>
    </flux:card>

    <!-- Section Contractors Modal (opened by clicking a chart segment) -->
    <flux:modal name="section-contractors" class="w-full max-w-2xl">
        <div class="p-6">
            <div class="mb-4 flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">{{ $sectionTitle }}</h3>
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">
                        {{ \Carbon\Carbon::create($selectedYear, $selectedMonth, 1)->format('F Y') }}
                        &mdash; {{ count($sectionContractors) }} {{ Str::plural('contractor', count($sectionContractors)) }}
                    </p>
                </div>
            </div>

            @if(count($sectionContractors) > 0)
                <div class="overflow-x-auto max-h-96 overflow-y-auto">
                    <table class="w-full text-sm">
                        <thead class="">
                            <tr class="">
                                <th class="pb-2 text-left text-xs font-medium text-zinc-500 dark:text-zinc-400">Contractor</th>
                                <th class="pb-2 text-left text-xs font-medium text-zinc-500 dark:text-zinc-400">CLAB No</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                            @foreach($sectionContractors as $contractor)
                                <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                                    <td class="py-2 font-medium text-zinc-900 dark:text-zinc-100">{{ $contractor['name'] }}</td>
                                    <td class="py-2 text-zinc-500 dark:text-zinc-400">{{ $contractor['clab_no'] ?? '-' }}</td>
                                    <td class="py-2">
                                        @php
                                            $color = match($contractor['status']) {
                                                'Paid' => 'green',
                                                'Overdue' => 'red',
                                                'Pending Payment' => 'yellow',
                                                'Not Submitted' => 'red',
                                                default => 'blue',
                                            };
                                        @endphp
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="py-8 text-center text-sm text-zinc-500 dark:text-zinc-400">
                    No contractors in this category.
                </div>
            @endif
        </div>
    </flux:modal>
</div>
