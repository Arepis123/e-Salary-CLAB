<div wire:init="loadInitialData" x-on:chart-section-clicked.window="$wire.loadSectionContractors($event.detail.index)">
    <div class="flex h-full w-full flex-1 flex-col gap-6">
        <!-- Page Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">Dashboard</h1>
                <p class="text-sm text-zinc-600 dark:text-zinc-400">Overview of e-salary system</p>
            </div>
            <div class="text-sm text-zinc-600 dark:text-zinc-400">
                {{ now()->format('l, F j, Y') }}
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
            <!-- Clients Without Submission -->
            <a href="{{ route('missing-submissions') }}" wire:navigate>
                <flux:card class="space-y-2 p-4 sm:p-6 bg-white dark:bg-zinc-900 rounded-lg cursor-pointer transition-[transform,box-shadow] duration-300 ease-in-out hover:scale-103 hover:shadow-lg">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-zinc-600 dark:text-zinc-400">Pending Submissions</p>
                            @if($isLoadingStats)
                                <flux:skeleton animate="shimmer" class="h-8 w-16 rounded" />
                            @else
                                <p class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">{{ $stats['clients_without_submission'] }}</p>
                            @endif
                        </div>
                        <div class="rounded-full bg-orange-100 dark:bg-orange-900/30 p-3 hidden lg:hidden xl:block">
                            <flux:icon.exclamation-triangle class="size-6 text-orange-600 dark:text-orange-400" />
                        </div>
                    </div>
                    <div class="flex items-center gap-2 text-xs">
                        @if($isLoadingStats)
                            <flux:skeleton animate="shimmer" class="h-4 w-32 rounded" />
                        @else
                            <span class="text-zinc-600 dark:text-zinc-400">{{ $stats['clients_with_submission_count'] }} of {{ $stats['total_clients'] }} submitted</span>
                        @endif
                    </div>
                </flux:card>
            </a>

            <!-- Active Workers -->
            <flux:card class="space-y-2 p-4 sm:p-6 bg-white dark:bg-zinc-900 rounded-lg cursor-pointer transition-[transform,box-shadow] duration-300 ease-in-out hover:scale-103 hover:shadow-lg">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-zinc-600 dark:text-zinc-400">Active Workers</p>
                        @if($isLoadingStats)
                            <flux:skeleton animate="shimmer" class="h-8 w-16 rounded" />
                        @else
                            <p class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">{{ $stats['active_workers'] }}</p>
                        @endif
                    </div>
                    <div class="rounded-full bg-green-100 dark:bg-green-900/30 p-3 hidden lg:hidden xl:block">
                        <flux:icon.users class="size-6 text-green-600 dark:text-green-400" />
                    </div>
                </div>
                <div class="flex items-center gap-2 text-xs">
                    @if($isLoadingStats)
                        <flux:skeleton animate="shimmer" class="h-4 w-24 rounded" />
                    @else
                        @if($stats['workers_growth'] > 0)
                            <span class="text-green-600 dark:text-green-400">+{{ $stats['workers_growth'] }} workers</span>
                        @elseif($stats['workers_growth'] < 0)
                            <span class="text-red-600 dark:text-red-400">{{ $stats['workers_growth'] }} workers</span>
                        @else
                            <span class="text-zinc-500 dark:text-zinc-400">no change</span>
                        @endif
                        <span class="text-zinc-600 dark:text-zinc-400">from last month</span>
                    @endif
                </div>
            </flux:card>

            <!-- This Month / Last Month Payments (switches on the 16th) -->
            <a href="{{ route('payroll') }}" wire:navigate>
                <flux:card class="space-y-2 p-4 sm:p-6 bg-white dark:bg-zinc-900 rounded-lg cursor-pointer transition-[transform,box-shadow] duration-300 ease-in-out hover:scale-103 hover:shadow-lg">
                    <div class="flex items-center justify-between">
                        <div>
                            @if(now()->day >= 16)
                                <p class="text-sm text-zinc-600 dark:text-zinc-400">This Month Payments</p>
                            @else
                                <p class="text-sm text-zinc-600 dark:text-zinc-400">Last Month Payments</p>
                            @endif
                            @if($isLoadingStats)
                                <flux:skeleton animate="shimmer" class="h-8 w-24 rounded" />
                            @else
                                @if(now()->day >= 16)
                                    <p class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">RM {{ number_format($stats['this_month_payments']) }}</p>
                                @else
                                    <p class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">RM {{ number_format($stats['last_month_payments']) }}</p>
                                @endif
                            @endif
                        </div>
                        <div class="rounded-full bg-purple-100 dark:bg-purple-900/30 p-3 hidden lg:hidden xl:block">
                            <flux:icon.wallet class="size-6 text-purple-600 dark:text-purple-400" />
                        </div>
                    </div>
                    <div class="flex items-center gap-2 text-xs">
                        @if($isLoadingStats)
                            <flux:skeleton animate="shimmer" class="h-4 w-28 rounded" />
                        @else
                            <span class="text-green-600 dark:text-green-400">+{{ $stats['payments_growth'] }}%</span>
                            <span class="text-zinc-600 dark:text-zinc-400">from last month</span>
                        @endif
                    </div>
                </flux:card>
            </a>

            <!-- Outstanding Balance -->
            <flux:card class="space-y-2 p-4 sm:p-6 bg-white dark:bg-zinc-900 rounded-lg cursor-pointer transition-[transform,box-shadow] duration-300 ease-in-out hover:scale-103 hover:shadow-lg">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-zinc-600 dark:text-zinc-400">Outstanding Balance</p>
                        @if($isLoadingStats)
                            <flux:skeleton animate="shimmer" class="h-8 w-24 rounded" />
                        @else
                            <p class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">RM {{ number_format($stats['outstanding_balance']) }}</p>
                        @endif
                    </div>
                    <div class="rounded-full bg-orange-100 dark:bg-orange-900/30 p-3 hidden lg:hidden xl:block">
                        <flux:icon.exclamation-circle class="size-6 text-orange-600 dark:text-orange-400" />
                    </div>
                </div>
                <div class="flex items-center gap-2 text-xs">
                    <span class="text-orange-600 dark:text-orange-400">Unpaid invoices</span>
                </div>
            </flux:card>
        </div>

        <!-- Recent Activity & Quick Actions -->
        <div class="grid gap-4 lg:grid-cols-3" wire:init="loadDeferredData">
            <!-- Recent Payments -->
            <flux:card class="order-last lg:order-first lg:col-span-2 min-w-0 overflow-hidden p-4 sm:p-6 bg-white dark:bg-zinc-900 rounded-lg">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">Recent Payments</h2>
                    <flux:button variant="ghost" size="sm" href="{{ route('payroll') }}" wire:navigate>View all</flux:button>
                </div>

                @if($isLoadingRecentPayments)
                    <flux:skeleton.group animate="shimmer" class="space-y-3">
                        @for($i = 0; $i < 5; $i++)
                            <div class="flex items-center gap-4 py-3 border-b border-zinc-200 dark:border-zinc-700">
                                <flux:skeleton class="h-4 w-32 rounded" />
                                <flux:skeleton class="h-4 w-20 rounded" />
                                <flux:skeleton class="h-5 w-16 rounded-full" />
                            </div>
                        @endfor
                    </flux:skeleton.group>
                @elseif(count($recentPayments) > 0)
                    <!-- Mobile: card list -->
                    <div class="sm:hidden space-y-3">
                        @foreach($recentPayments as $payment)
                            <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-3">
                                <div class="flex items-center justify-between mb-1">
                                    <span class="text-sm font-medium text-zinc-900 dark:text-zinc-100 truncate mr-2">{{ $payment['client'] }}</span>
                                    <flux:badge color="{{ $payment['status'] === 'completed' ? 'green' : 'yellow' }}" size="sm">
                                        {{ ucfirst($payment['status']) }}
                                    </flux:badge>
                                </div>
                                <div class="flex items-center justify-between text-xs text-zinc-600 dark:text-zinc-400">
                                    <span class="font-medium">RM {{ number_format($payment['amount']) }}</span>
                                    <span>{{ $payment['workers'] }} workers · {{ $payment['date'] }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <!-- Desktop: table -->
                    <div class="hidden sm:block overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="border-b border-zinc-200 dark:border-zinc-700">
                                    <th class="pb-3 text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Client</th>
                                    <th class="pb-3 text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Amount</th>
                                    <th class="pb-3 text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Workers</th>
                                    <th class="pb-3 text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Date</th>
                                    <th class="pb-3 text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                                @foreach($recentPayments as $payment)
                                <tr>
                                    <td class="py-3 text-sm text-zinc-900 dark:text-zinc-100 max-w-[150px] truncate">{{ $payment['client'] }}</td>
                                    <td class="py-3 text-sm font-medium text-zinc-900 dark:text-zinc-100 whitespace-nowrap">RM {{ number_format($payment['amount']) }}</td>
                                    <td class="py-3 text-sm text-zinc-600 dark:text-zinc-400 whitespace-nowrap">{{ $payment['workers'] }} workers</td>
                                    <td class="py-3 text-sm text-zinc-600 dark:text-zinc-400 whitespace-nowrap">{{ $payment['date'] }}</td>
                                    <td class="py-3">
                                        <flux:badge color="{{ $payment['status'] === 'completed' ? 'green' : 'yellow' }}" size="sm">
                                            {{ ucfirst($payment['status']) }}
                                        </flux:badge>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="py-12 text-center">
                        <flux:icon.banknotes class="mx-auto size-12 text-zinc-300 dark:text-zinc-600 mb-3" />
                        <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100 mb-1">No Recent Payments</p>
                        <p class="text-xs text-zinc-600 dark:text-zinc-400">
                            Payment records will appear here once contractors complete their payments.
                        </p>
                    </div>
                @endif
            </flux:card>

            <!-- Quick Actions & Alerts -->
            <div class="order-first lg:order-last min-w-0 space-y-4">
                <!-- Quick Actions -->
                <flux:card class="p-4 sm:p-6 bg-white dark:bg-zinc-900 rounded-lg">
                    <h2 class="mb-4 text-lg font-semibold text-zinc-900 dark:text-zinc-100">Quick Actions</h2>
                    <div class="space-y-2">
                        <flux:button variant="primary" class="w-full" href="{{ route('payroll') }}" wire:navigate>
                            <flux:icon.wallet class="size-4" />
                            View Payroll
                        </flux:button>
                        <flux:button variant="outline" class="w-full" href="{{ route('workers') }}" wire:navigate>
                            <flux:icon.users class="size-4" />
                            Manage Workers
                        </flux:button>
                        <flux:button variant="outline" class="w-full" href="{{ route('notifications') }}" wire:navigate>
                            <flux:icon.bell class="size-4" />
                            View Reminder
                        </flux:button>
                    </div>
                </flux:card>

                <!-- Alerts -->
                <flux:card class="p-4 sm:p-6 bg-white dark:bg-zinc-900 rounded-lg">
                    <h2 class="mb-4 text-lg font-semibold text-zinc-900 dark:text-zinc-100">Alerts</h2>
                    <div class="space-y-3">
                        @if(now()->day >= 16)
                            <div class="flex gap-3 rounded-lg bg-orange-50 dark:bg-orange-900/20 p-3">
                                <flux:icon.exclamation-triangle class="size-5 flex-shrink-0 text-orange-600 dark:text-orange-400" />
                                <div>
                                    <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Outstanding Balance</p>
                                    @if($isLoadingStats)
                                        <flux:skeleton animate="shimmer" class="h-4 w-32 rounded mt-1" />
                                    @else
                                        <p class="text-xs text-zinc-600 dark:text-zinc-400">RM {{ number_format($stats['outstanding_balance']) }} in unpaid invoices</p>
                                    @endif
                                </div>
                            </div>
                        @else
                            <div class="flex gap-3 rounded-lg bg-zinc-50 dark:bg-zinc-800/50 p-3">
                                <flux:icon.clock class="size-5 flex-shrink-0 text-zinc-400 dark:text-zinc-500" />
                                <div>
                                    <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Outstanding Balance</p>
                                    <p class="text-xs text-zinc-500 dark:text-zinc-400">Available from the 16th once auto-submit runs</p>
                                </div>
                            </div>
                        @endif

                        <div class="flex gap-3 rounded-lg bg-blue-50 dark:bg-blue-900/20 p-3">
                            <flux:icon.information-circle class="size-5 flex-shrink-0 text-blue-600 dark:text-blue-400" />
                            <div>
                                <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100">New Workers</p>
                                <p class="text-xs text-zinc-600 dark:text-zinc-400">5 new workers added this week</p>
                            </div>
                        </div>
                    </div>
                </flux:card>
            </div>
        </div>

        <!-- Charts Row - Side by Side -->
        <div class="grid gap-4 lg:grid-cols-2">
            <!-- Contractor Submission Status Chart -->
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

                @if($isLoadingCharts)
                    <flux:skeleton animate="shimmer" class="h-64 w-full rounded-lg" />
                @else
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
                @endif
            </flux:card>

            <!-- Top Overdue Clients Chart -->
            <flux:card class="p-4 sm:p-6 bg-white dark:bg-zinc-900 rounded-lg">
                <div class="mb-4">
                    <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">Top 5 Overdue Clients</h2>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400 mt-1">Clients who most frequently miss their payment deadline</p>
                </div>
                @if($isLoadingCharts)
                    <flux:skeleton animate="shimmer" class="h-64 w-full rounded-lg" />
                @elseif(count($topOverdueChartData['labels'] ?? []) > 0)
                    <!-- Data container for Top Overdue Clients chart -->
                    <div id="topOverdueChartDataContainer"
                        data-labels='@json($topOverdueChartData["labels"])'
                        data-counts='@json($topOverdueChartData["data"])'
                        style="display: none;"></div>
                    <div class="relative h-64">
                        <canvas id="topOverdueChart" wire:ignore></canvas>
                    </div>
                @else
                    <div class="flex h-64 flex-col items-center justify-center gap-2 text-center">
                        <flux:icon.check-circle class="size-10 text-green-500" />
                        <p class="text-sm text-zinc-500 dark:text-zinc-400">No overdue payrolls. Every client is on time.</p>
                    </div>
                @endif
            </flux:card>
        </div>
    </div>

    <script>
        // Store chart instance globally to destroy before re-creating
        window.contractorStatusChartInstance = null;

        // Wait for both DOM and Chart.js to be ready
        window.initContractorStatusChart = function() {
            if (typeof Chart === 'undefined') {
                setTimeout(window.initContractorStatusChart, 50);
                return;
            }

            const ctx = document.getElementById('contractorStatusChart');
            if (!ctx) return;

            // Get data from the data container
            const dataContainer = document.getElementById('chartDataContainer');
            if (!dataContainer) return;

            const contractorStatusData = {
                labels: JSON.parse(dataContainer.dataset.chartLabels),
                data: JSON.parse(dataContainer.dataset.chartData),
                colors: JSON.parse(dataContainer.dataset.chartColors)
            };

            // Debug logging
            console.log('Updating Contractor Status Chart:', {
                month: dataContainer.dataset.month,
                year: dataContainer.dataset.year,
                data: contractorStatusData
            });

            // Destroy existing chart instance if it exists
            if (window.contractorStatusChartInstance) {
                window.contractorStatusChartInstance.destroy();
                console.log('Destroyed existing chart instance');
            }

            // Get theme colors
            const isDark = document.documentElement.classList.contains('dark');
            const textColor = isDark ? '#e4e4e7' : '#18181b';
            const gridColor = isDark ? '#3f3f46' : '#e4e4e7';

            window.contractorStatusChartInstance = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: contractorStatusData.labels,
                    datasets: [{
                        data: contractorStatusData.data,
                        backgroundColor: contractorStatusData.colors,
                        borderWidth: 2,
                        borderColor: isDark ? '#18181b' : '#ffffff',
                        hoverOffset: 10
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    onHover: function(event, elements) {
                        event.native.target.style.cursor = elements.length > 0 ? 'pointer' : 'default';
                    },
                    onClick: function(event, elements) {
                        if (elements.length > 0) {
                            const index = elements[0].index;
                            window.dispatchEvent(new CustomEvent('chart-section-clicked', { detail: { index: index } }));
                        }
                    },
                    plugins: {
                        legend: {
                            display: true,
                            position: 'bottom',
                            labels: {
                                color: textColor,
                                padding: 20,
                                font: {
                                    family: 'Inter, ui-sans-serif, system-ui, sans-serif',
                                    size: 12,
                                    weight: '500'
                                },
                                usePointStyle: true,
                                generateLabels: function(chart) {
                                    const data = chart.data;
                                    // Get current theme color when generating labels
                                    const currentIsDark = document.documentElement.classList.contains('dark');
                                    const labelColor = currentIsDark ? '#e4e4e7' : '#18181b';

                                    if (data.labels.length && data.datasets.length) {
                                        return data.labels.map((label, i) => {
                                            const value = data.datasets[0].data[i];
                                            const total = data.datasets[0].data.reduce((a, b) => a + b, 0);
                                            const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                                            return {
                                                text: `${label}: ${value} (${percentage}%)`,
                                                fillStyle: data.datasets[0].backgroundColor[i],
                                                fontColor: labelColor,  // Chart.js v2
                                                color: labelColor,      // Chart.js v3+
                                                hidden: false,
                                                index: i
                                            };
                                        });
                                    }
                                    return [];
                                }
                            }
                        },
                        tooltip: {
                            backgroundColor: isDark ? '#27272a' : '#ffffff',
                            titleColor: textColor,
                            bodyColor: textColor,
                            borderColor: gridColor,
                            borderWidth: 1,
                            padding: 12,
                            titleFont: {
                                family: 'Inter, ui-sans-serif, system-ui, sans-serif',
                                size: 13,
                                weight: 'bold'
                            },
                            bodyFont: {
                                family: 'Inter, ui-sans-serif, system-ui, sans-serif',
                                size: 12
                            },
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.parsed;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                                    return `${label}: ${value} contractors (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            });
        }

        // Store chart instance globally to destroy before re-creating
        window.topOverdueChartInstance = null;

        function initTopOverdueChart() {
            if (typeof Chart === 'undefined') {
                setTimeout(initTopOverdueChart, 50);
                return;
            }

            const ctx = document.getElementById('topOverdueChart');
            if (!ctx) return;

            // Get data from the data container
            const dataContainer = document.getElementById('topOverdueChartDataContainer');
            if (!dataContainer) return;

            // Destroy existing chart instance if it exists
            if (window.topOverdueChartInstance) {
                window.topOverdueChartInstance.destroy();
            }

            // Get theme colors
            const isDark = document.documentElement.classList.contains('dark');
            const textColor = isDark ? '#d4d4d8' : '#3f3f46';
            const gridColor = isDark ? '#3f3f46' : '#e4e4e7';

            const overdueData = {
                labels: JSON.parse(dataContainer.dataset.labels),
                counts: JSON.parse(dataContainer.dataset.counts)
            };

            // Give the axis ~40% headroom above the largest value so the longest
            // bar never spans the full width (keeps integer ticks via stepSize: 1).
            const maxCount = Math.max(0, ...overdueData.counts);
            const suggestedMax = maxCount > 0 ? Math.ceil(maxCount * 1.4) : 1;

            console.log('Initializing Top Overdue Clients Chart:', overdueData);

            window.topOverdueChartInstance = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: overdueData.labels,
                    datasets: [{
                        label: 'Overdue Payrolls',
                        data: overdueData.counts,
                        backgroundColor: '#f59e0b',
                        hoverBackgroundColor: '#d97706',
                        borderRadius: 6,
                        borderSkipped: false,
                        maxBarThickness: 30
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            backgroundColor: isDark ? '#18181b' : '#ffffff',
                            titleColor: textColor,
                            bodyColor: textColor,
                            borderColor: gridColor,
                            borderWidth: 1,
                            padding: 12,
                            displayColors: false,
                            titleFont: {
                                family: 'Inter, ui-sans-serif, system-ui, sans-serif',
                                size: 13
                            },
                            bodyFont: {
                                family: 'Inter, ui-sans-serif, system-ui, sans-serif',
                                size: 12
                            },
                            callbacks: {
                                label: function(context) {
                                    const value = context.parsed.x;
                                    return ' ' + value + ' overdue ' + (value === 1 ? 'payroll' : 'payrolls');
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            beginAtZero: true,
                            suggestedMax: suggestedMax,
                            grid: {
                                color: gridColor
                            },
                            ticks: {
                                color: textColor,
                                precision: 0,
                                stepSize: 1,
                                font: {
                                    family: 'Inter, ui-sans-serif, system-ui, sans-serif',
                                    size: 11
                                }
                            }
                        },
                        y: {
                            grid: {
                                display: false
                            },
                            ticks: {
                                color: textColor,
                                font: {
                                    family: 'Inter, ui-sans-serif, system-ui, sans-serif',
                                    size: 11
                                }
                            }
                        }
                    }
                }
            });
        }

        // Track if charts have been initialized
        window.contractorChartInitialized = false;
        window.topOverdueChartInitialized = false;

        // Function to initialize charts if ready. Each chart is initialized
        // independently so the top-overdue chart being absent (empty state) does
        // not prevent the contractor status chart from rendering, and vice versa.
        function tryInitCharts() {
            if (!window.contractorChartInitialized &&
                document.getElementById('chartDataContainer') &&
                document.getElementById('contractorStatusChart')) {
                console.log('Contractor status chart container found, initializing...');
                window.contractorChartInitialized = true;
                window.initContractorStatusChart();
                setupChartObserver();
            }

            if (!window.topOverdueChartInitialized &&
                document.getElementById('topOverdueChartDataContainer') &&
                document.getElementById('topOverdueChart')) {
                console.log('Top overdue chart container found, initializing...');
                window.topOverdueChartInitialized = true;
                initTopOverdueChart();
            }
        }

        // Use Livewire hook to detect DOM updates after component refresh
        document.addEventListener('livewire:init', function() {
            // Listen for component updates
            Livewire.hook('morph.updated', ({ el, component }) => {
                // Check if charts are ready after any DOM update
                setTimeout(tryInitCharts, 50);
            });

            // Also listen for the custom event as backup
            Livewire.on('chartsDataLoaded', function() {
                setTimeout(tryInitCharts, 100);
            });
        });

        // Poll for chart containers (handles initial load and navigate).
        // Keep polling until the always-present contractor chart is up; the
        // top-overdue chart is initialized opportunistically when present.
        function pollForCharts() {
            tryInitCharts();
            if (!window.contractorChartInitialized) {
                setTimeout(pollForCharts, 200);
            }
        }

        // Start polling when DOM is ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', pollForCharts);
        } else {
            pollForCharts();
        }

        // Reset initialization flags when navigating away (for wire:navigate)
        document.addEventListener('livewire:navigating', function() {
            window.contractorChartInitialized = false;
            window.topOverdueChartInitialized = false;
        });

        // Setup MutationObserver to watch for data changes
        function setupChartObserver() {
            const dataContainer = document.getElementById('chartDataContainer');
            if (!dataContainer) return;

            const observer = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    if (mutation.type === 'attributes' &&
                        (mutation.attributeName === 'data-chart-data' ||
                         mutation.attributeName === 'data-month' ||
                         mutation.attributeName === 'data-year')) {
                        console.log('Chart data changed, re-rendering...');
                        setTimeout(window.initContractorStatusChart, 100);
                    }
                });
            });

            observer.observe(dataContainer, {
                attributes: true,
                attributeFilter: ['data-chart-data', 'data-chart-labels', 'data-chart-colors', 'data-month', 'data-year']
            });

            console.log('MutationObserver setup complete');
        }

        // Watch for theme changes and reinitialize charts
        const themeObserver = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.type === 'attributes' && mutation.attributeName === 'class') {
                    // Reinitialize both charts with new theme colors
                    setTimeout(function() {
                        window.initContractorStatusChart();
                        initTopOverdueChart();
                    }, 100);
                }
            });
        });

        // Observe theme changes on <html> element
        themeObserver.observe(document.documentElement, {
            attributes: true,
            attributeFilter: ['class']
        });
    </script>

    <!-- Section Contractors Modal -->
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

    <!-- Configuration reminder: pops on dashboard load when any OT entry window is open
         or any contractor-specific override (service charge / penalty exemption or enabled
         deductions) is active, so admins are reminded these settings are in effect. -->
    <flux:modal name="config-reminder" class="w-full max-w-lg">
        <div class="p-6">
            <div class="mb-4 flex items-start gap-3">
                <div>
                    <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">Active Configuration Reminder</h3>
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">
                        The following settings are currently active. Review them on the Configuration page.
                    </p>
                </div>
            </div>

            <div class="max-h-96 space-y-5 overflow-y-auto">
                @if(count($configReminderWindows) > 0)
                    <div>
                        <div class="mb-2 flex items-center gap-2">
                            <h4 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">
                                Open OT Entry Windows
                            </h4>
                        </div>
                        <ul class="divide-y divide-zinc-100 dark:divide-zinc-800 rounded-lg border border-zinc-100 dark:border-zinc-800">
                            @foreach($configReminderWindows as $contractor)
                                <li class="flex items-center justify-between px-3 py-2">
                                    <span class="text-sm font-medium text-zinc-900 dark:text-zinc-100">- {{ $contractor['name'] }}</span>
                                    <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ $contractor['clab_no'] }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if(count($configReminderSettings) > 0)
                    <div>
                        <div class="mb-2 flex items-center gap-2">
                            <h4 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">
                                Contractor-Specific Settings
                            </h4>
                        </div>
                        <ul class="divide-y divide-zinc-100 dark:divide-zinc-800 rounded-lg border border-zinc-100 dark:border-zinc-800">
                            @foreach($configReminderSettings as $contractor)
                                <li class="px-3 py-2">
                                    <div class="flex items-center justify-between">
                                        <span class="text-sm font-medium text-zinc-900 dark:text-zinc-100">- {{ $contractor['name'] }}</span>
                                        <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ $contractor['clab_no'] }}</span>
                                    </div>
                                    <div class="mt-1 flex flex-wrap gap-1">
                                        @if($contractor['service_charge_exempt'])
                                            <flux:badge size="sm" color="purple">Service charge exempt</flux:badge>
                                        @endif
                                        @if($contractor['penalty_exempt'])
                                            <flux:badge size="sm" color="orange">Penalty exempt</flux:badge>
                                        @endif
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>

            <div class="mt-6 flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">Dismiss</flux:button>
                </flux:modal.close>
                <flux:button :href="route('configuration')" wire:navigate variant="primary" icon="cog-6-tooth">
                    Go to Configuration
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
