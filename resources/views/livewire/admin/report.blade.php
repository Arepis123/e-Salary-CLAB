<div class="flex h-full w-full flex-1 flex-col gap-6">
        <!-- Page Header -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">Reports & Analytics</h1>
                <p class="text-sm text-zinc-600 dark:text-zinc-400">Generate and view payroll reports</p>
            </div>
            <div class="flex gap-2">
                <flux:button variant="outline" href="#" wire:navigate class="flex-1 sm:flex-none">
                    <flux:icon.arrow-down-tray class="size-4" />
                    <span class="hidden sm:inline">Export All</span>
                    <span class="sm:hidden">Export</span>
                </flux:button>
                <flux:button variant="primary" href="#" wire:navigate class="flex-1 sm:flex-none">
                    <flux:icon.document-plus class="size-4" />
                    <span class="hidden sm:inline">Generate Report</span>
                    <span class="sm:hidden">Generate</span>
                </flux:button>
            </div>
        </div>

        <!-- Report Filters -->
        <flux:card class="p-4 sm:p-6 dark:bg-zinc-900 rounded-lg">
            <h2 class="mb-4 text-lg font-semibold text-zinc-900 dark:text-zinc-100">Filter Reports</h2>
            <div class="grid gap-4 md:grid-cols-4">
                <div>
                    <flux:label>Report Type</flux:label>
                    <flux:select wire:model.live="reportType" placeholder="Select type">
                        <flux:select.option value="">All Reports</flux:select.option>
                        <flux:select.option value="payment">Payment Summary</flux:select.option>
                        <flux:select.option value="worker">Worker Payroll</flux:select.option>
                        <flux:select.option value="client">Client Summary</flux:select.option>
                        <flux:select.option value="monthly">Monthly Analysis</flux:select.option>
                        <flux:select.option value="tax">Tax Report</flux:select.option>
                    </flux:select>
                </div>

                <div>
                    <flux:label>Period</flux:label>
                    <flux:select wire:model.live="period" wire:change="filterByMonthYear($event.target.value)" placeholder="Select period">
                        @foreach($availableMonths as $month)
                            <flux:select.option value="{{ $month['value'] }}">{{ $month['label'] }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>

                <div>
                    <flux:label>Client</flux:label>
                    <flux:select wire:model.live="clientFilter" placeholder="All Clients">
                        <flux:select.option value="">All Clients</flux:select.option>
                        @foreach($clientPayments as $client)
                            <flux:select.option value="{{ $client['client'] }}">{{ $client['client'] }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>

                <div class="flex items-end">
                    <flux:button variant="primary" class="w-full" wire:click="applyFilters">
                        <flux:icon.funnel class="size-4" />
                        Apply Filters
                    </flux:button>
                </div>
            </div>
        </flux:card>

        <!-- Report Statistics -->
        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
            <flux:card class="space-y-2 p-4 sm:p-6 dark:bg-zinc-900 rounded-lg">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-zinc-600 dark:text-zinc-400">Total Paid ({{ now()->format('M Y') }})</p>
                        <p class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">RM {{ number_format($stats['total_paid'], 2) }}</p>
                    </div>
                    <div class="rounded-full bg-green-100 dark:bg-green-900/30 p-3">
                        <flux:icon.check-circle class="size-6 text-green-600 dark:text-green-400" />
                    </div>
                </div>
                <p class="text-xs text-zinc-600 dark:text-zinc-400">{{ $stats['completed_payments'] }} completed {{ Str::plural('payment', $stats['completed_payments']) }}</p>
            </flux:card>

            <flux:card class="space-y-2 p-4 sm:p-6 dark:bg-zinc-900 rounded-lg">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-zinc-600 dark:text-zinc-400">Pending Amount</p>
                        <p class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">RM {{ number_format($stats['pending_amount'], 2) }}</p>
                    </div>
                    <div class="rounded-full bg-orange-100 dark:bg-orange-900/30 p-3">
                        <flux:icon.clock class="size-6 text-orange-600 dark:text-orange-400" />
                    </div>
                </div>
                <p class="text-xs text-zinc-600 dark:text-zinc-400">{{ $stats['pending_payments'] }} pending {{ Str::plural('payment', $stats['pending_payments']) }}</p>
            </flux:card>

            <flux:card class="space-y-2 p-4 sm:p-6 dark:bg-zinc-900 rounded-lg">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-zinc-600 dark:text-zinc-400">Average Salary</p>
                        <p class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">RM {{ number_format($stats['average_salary'], 2) }}</p>
                    </div>
                    <div class="rounded-full bg-blue-100 dark:bg-blue-900/30 p-3">
                        <flux:icon.calculator class="size-6 text-blue-600 dark:text-blue-400" />
                    </div>
                </div>
                <p class="text-xs text-zinc-600 dark:text-zinc-400">Per worker this month</p>
            </flux:card>

            <flux:card class="space-y-2 p-4 sm:p-6 dark:bg-zinc-900 rounded-lg">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-zinc-600 dark:text-zinc-400">Total Hours</p>
                        <p class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">{{ number_format($stats['total_hours']) }}</p>
                    </div>
                    <div class="rounded-full bg-purple-100 dark:bg-purple-900/30 p-3">
                        <flux:icon.clock class="size-6 text-purple-600 dark:text-purple-400" />
                    </div>
                </div>
                <p class="text-xs text-zinc-600 dark:text-zinc-400">Including {{ number_format($stats['overtime_hours']) }} OT hours</p>
            </flux:card>
        </div>

        <!-- Charts Section -->
        <div class="grid gap-4 lg:grid-cols-2">
            <!-- Monthly Payment Trend -->
            <flux:card class="p-4 sm:p-6 dark:bg-zinc-900 rounded-lg">
                <h2 class="mb-4 text-lg font-semibold text-zinc-900 dark:text-zinc-100">Monthly Payment Trend</h2>
                <div class="relative h-64">
                    <canvas id="monthlyTrendChart"></canvas>
                </div>
            </flux:card>

            <!-- Payment Distribution by Client -->
            <flux:card class="p-4 sm:p-6 dark:bg-zinc-900 rounded-lg">
                <h2 class="mb-4 text-lg font-semibold text-zinc-900 dark:text-zinc-100">Payment Distribution by Client</h2>
                <div class="relative h-64">
                    <canvas id="clientDistributionChart"></canvas>
                </div>
            </flux:card>
        </div>

        <!-- Payment Summary by Client -->
        <flux:card class="p-4 sm:p-6 dark:bg-zinc-900 rounded-lg">
            <div class="mb-4 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">Payment Summary by Client</h2>
                    <div class="min-w-[180px]">
                        <flux:select wire:model.live="selectedMonth" wire:change="filterByMonthYear($event.target.value)" size="sm">
                            @foreach($availableMonths as $month)
                                <flux:select.option value="{{ $month['value'] }}">
                                    {{ $month['label'] }}
                                </flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>
                </div>
                <flux:button variant="ghost" size="sm">
                    <flux:icon.arrow-down-tray class="size-4" />
                    Export CSV
                </flux:button>
            </div>

            @if(count($clientPayments) > 0)
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b border-zinc-200 dark:border-zinc-700">
                                <th class="pb-3 text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Client Name</th>
                                <th class="pb-3 text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Total Workers</th>
                                <th class="pb-3 text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Total Hours</th>
                                <th class="pb-3 text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Basic Salary</th>
                                <th class="pb-3 text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Overtime</th>
                                <th class="pb-3 text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Allowances</th>
                                <th class="pb-3 text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Deductions</th>
                                <th class="pb-3 text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Total Amount</th>
                                <th class="pb-3 text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                            @foreach($clientPayments as $client)
                                <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                                    <td class="py-3 text-sm text-zinc-900 dark:text-zinc-100">{{ $client['client'] }}</td>
                                    <td class="py-3 text-sm text-zinc-600 dark:text-zinc-400">{{ $client['workers'] }}</td>
                                    <td class="py-3 text-sm text-zinc-600 dark:text-zinc-400">{{ number_format($client['hours']) }}</td>
                                    <td class="py-3 text-sm text-zinc-900 dark:text-zinc-100">RM {{ number_format($client['basic_salary'], 2) }}</td>
                                    <td class="py-3 text-sm text-zinc-900 dark:text-zinc-100">RM {{ number_format($client['overtime'], 2) }}</td>
                                    <td class="py-3 text-sm text-zinc-900 dark:text-zinc-100">RM {{ number_format($client['allowances'], 2) }}</td>
                                    <td class="py-3 text-sm text-red-600 dark:text-red-400">RM {{ number_format($client['deductions'], 2) }}</td>
                                    <td class="py-3 text-sm font-medium text-zinc-900 dark:text-zinc-100">RM {{ number_format($client['total'], 2) }}</td>
                                    <td class="py-3">
                                        <flux:badge color="{{ $client['status'] === 'Paid' ? 'green' : 'orange' }}" size="sm">{{ $client['status'] }}</flux:badge>
                                    </td>
                                </tr>
                            @endforeach

                            <tr class="bg-zinc-100 dark:bg-zinc-800 font-semibold">
                                <td class="py-3 text-sm text-zinc-900 dark:text-zinc-100">TOTAL</td>
                                <td class="py-3 text-sm text-zinc-900 dark:text-zinc-100">{{ collect($clientPayments)->sum('workers') }}</td>
                                <td class="py-3 text-sm text-zinc-900 dark:text-zinc-100">{{ number_format(collect($clientPayments)->sum('hours')) }}</td>
                                <td class="py-3 text-sm text-zinc-900 dark:text-zinc-100">RM {{ number_format(collect($clientPayments)->sum('basic_salary'), 2) }}</td>
                                <td class="py-3 text-sm text-zinc-900 dark:text-zinc-100">RM {{ number_format(collect($clientPayments)->sum('overtime'), 2) }}</td>
                                <td class="py-3 text-sm text-zinc-900 dark:text-zinc-100">RM {{ number_format(collect($clientPayments)->sum('allowances'), 2) }}</td>
                                <td class="py-3 text-sm text-red-600 dark:text-red-400">RM {{ number_format(collect($clientPayments)->sum('deductions'), 2) }}</td>
                                <td class="py-3 text-sm font-bold text-zinc-900 dark:text-zinc-100">RM {{ number_format(collect($clientPayments)->sum('total'), 2) }}</td>
                                <td class="py-3"></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            @else
                <div class="text-center py-12">
                    <p class="text-zinc-600 dark:text-zinc-400">No client payment data available for {{ \Carbon\Carbon::create($selectedYear, $selectedMonth)->format('F Y') }}</p>
                </div>
            @endif
        </flux:card>

        <!-- Worker Payroll Summary -->
        <flux:card class="p-4 sm:p-6 dark:bg-zinc-900 rounded-lg">
            <div class="mb-4 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">Top Paid Workers</h2>
                    <div class="min-w-[180px]">
                        <flux:select wire:model.live="selectedMonth" wire:change="filterByMonthYear($event.target.value)" size="sm">
                            @foreach($availableMonths as $month)
                                <flux:select.option value="{{ $month['value'] }}">
                                    {{ $month['label'] }}
                                </flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>
                </div>
                <flux:button variant="ghost" size="sm" href="{{ route('admin.worker') }}" wire:navigate>View all workers</flux:button>
            </div>

            @if(count($topWorkers) > 0)
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b border-zinc-200 dark:border-zinc-700">
                                <th class="pb-3 text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Rank</th>
                                <th class="pb-3 text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Worker ID</th>
                                <th class="pb-3 text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Name</th>
                                <th class="pb-3 text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Position</th>
                                <th class="pb-3 text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Client</th>
                                <th class="pb-3 text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Hours Worked</th>
                                <th class="pb-3 text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Total Earned</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                            @foreach($topWorkers as $worker)
                                <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                                    <td class="py-3 text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $worker['rank'] }}</td>
                                    <td class="py-3 text-sm text-zinc-600 dark:text-zinc-400">{{ $worker['worker_id'] }}</td>
                                    <td class="py-3">
                                        <div class="flex items-center gap-3">
                                            <flux:avatar size="sm" name="{{ $worker['name'] }}" />
                                            <span class="text-sm text-zinc-900 dark:text-zinc-100">{{ $worker['name'] }}</span>
                                        </div>
                                    </td>
                                    <td class="py-3 text-sm text-zinc-900 dark:text-zinc-100">{{ $worker['position'] }}</td>
                                    <td class="py-3 text-sm text-zinc-600 dark:text-zinc-400">{{ $worker['client'] }}</td>
                                    <td class="py-3 text-sm text-zinc-600 dark:text-zinc-400">{{ $worker['hours'] }} hrs</td>
                                    <td class="py-3 text-sm font-medium text-green-600 dark:text-green-400">RM {{ number_format($worker['earned'], 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="text-center py-12">
                    <p class="text-zinc-600 dark:text-zinc-400">No worker data available for {{ \Carbon\Carbon::create($selectedYear, $selectedMonth)->format('F Y') }}</p>
                </div>
            @endif
        </flux:card>

        <!-- Quick Report Templates -->
        <flux:card class="p-4 sm:p-6 dark:bg-zinc-900 rounded-lg">
            <h2 class="mb-4 text-lg font-semibold text-zinc-900 dark:text-zinc-100">Quick Report Templates</h2>
            <div class="grid gap-4 md:grid-cols-3">
                <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-4 hover:border-blue-500 dark:hover:border-blue-500 cursor-pointer transition">
                    <flux:icon.document-text class="size-8 text-blue-600 dark:text-blue-400 mb-3" />
                    <h3 class="font-semibold text-zinc-900 dark:text-zinc-100 mb-1">Monthly Payroll Summary</h3>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400 mb-3">Complete payroll summary for the selected month</p>
                    <flux:button variant="outline" size="sm" class="w-full">Generate PDF</flux:button>
                </div>

                <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-4 hover:border-blue-500 dark:hover:border-blue-500 cursor-pointer transition">
                    <flux:icon.users class="size-8 text-green-600 dark:text-green-400 mb-3" />
                    <h3 class="font-semibold text-zinc-900 dark:text-zinc-100 mb-1">Worker Payslips</h3>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400 mb-3">Individual payslips for all workers</p>
                    <flux:button variant="outline" size="sm" class="w-full">Generate ZIP</flux:button>
                </div>

                <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-4 hover:border-blue-500 dark:hover:border-blue-500 cursor-pointer transition">
                    <flux:icon.building-office class="size-8 text-purple-600 dark:text-purple-400 mb-3" />
                    <h3 class="font-semibold text-zinc-900 dark:text-zinc-100 mb-1">Client Billing Report</h3>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400 mb-3">Detailed billing report per client</p>
                    <flux:button variant="outline" size="sm" class="w-full">Generate Excel</flux:button>
                </div>
            </div>
        </flux:card>
    </div>

    <script>
        // Wait for both DOM and Chart.js to be ready
        function initReportCharts() {
            if (typeof Chart === 'undefined') {
                setTimeout(initReportCharts, 50);
                return;
            }

            const trendCtx = document.getElementById('monthlyTrendChart');
            const pieCtx = document.getElementById('clientDistributionChart');

            if (!trendCtx || !pieCtx) return;

            // Get theme colors
            const isDark = document.documentElement.classList.contains('dark');
            const textColor = isDark ? '#d4d4d8' : '#3f3f46';
            const gridColor = isDark ? '#3f3f46' : '#e4e4e7';

            // Monthly Trend Chart
            new Chart(trendCtx, {
                type: 'bar',
                data: {
                    labels: @json($chartData['trend']['labels']),
                    datasets: [{
                        label: 'Total Payments (RM)',
                        data: @json($chartData['trend']['data']),
                        backgroundColor: 'rgba(139, 92, 246, 0.8)',
                        borderColor: '#8b5cf6',
                        borderWidth: 2,
                        borderRadius: 6
                    }]
                },
                options: {
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
                            callbacks: {
                                label: function(context) {
                                    return 'RM ' + context.parsed.y.toLocaleString();
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: {
                                display: false
                            },
                            ticks: {
                                color: textColor
                            }
                        },
                        y: {
                            grid: {
                                color: gridColor
                            },
                            ticks: {
                                color: textColor,
                                callback: function(value) {
                                    return 'RM ' + (value / 1000) + 'k';
                                }
                            }
                        }
                    }
                }
            });

            // Client Distribution Pie Chart
            new Chart(pieCtx, {
                type: 'doughnut',
                data: {
                    labels: @json($chartData['distribution']['labels']),
                    datasets: [{
                        label: 'Payment Amount',
                        data: @json($chartData['distribution']['data']),
                        backgroundColor: [
                            'rgba(59, 130, 246, 0.8)',   // Blue
                            'rgba(16, 185, 129, 0.8)',   // Green
                            'rgba(245, 158, 11, 0.8)',   // Orange
                            'rgba(139, 92, 246, 0.8)',   // Purple
                            'rgba(236, 72, 153, 0.8)'    // Pink
                        ],
                        borderColor: [
                            '#3b82f6',
                            '#10b981',
                            '#f59e0b',
                            '#8b5cf6',
                            '#ec4899'
                        ],
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'bottom',
                            labels: {
                                color: textColor,
                                padding: 15,
                                usePointStyle: true
                            }
                        },
                        tooltip: {
                            backgroundColor: isDark ? '#18181b' : '#ffffff',
                            titleColor: textColor,
                            bodyColor: textColor,
                            borderColor: gridColor,
                            borderWidth: 1,
                            padding: 12,
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.parsed;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = ((value / total) * 100).toFixed(1);
                                    return label + ': RM ' + value.toLocaleString() + ' (' + percentage + '%)';
                                }
                            }
                        }
                    }
                }
            });
        }

        // Initialize when DOM is ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initReportCharts);
        } else {
            initReportCharts();
        }
    </script>
</div>
