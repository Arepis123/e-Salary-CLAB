<div>
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

        <!-- Statistics Cards (loads independently) -->
        <livewire:admin.dashboard.stats-cards />

        <!-- Recent Activity & Quick Actions -->
        <div class="grid gap-4 lg:grid-cols-3">
            <!-- Recent Payments (loads independently) -->
            <livewire:admin.dashboard.recent-payments />

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
                                    @if(is_null($outstandingBalance))
                                        <flux:skeleton animate="shimmer" class="h-4 w-32 rounded mt-1" />
                                    @else
                                        <p class="text-xs text-zinc-600 dark:text-zinc-400">RM {{ number_format($outstandingBalance) }} in unpaid invoices</p>
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

        <!-- Charts Row - Side by Side (each loads independently) -->
        <div class="grid gap-4 lg:grid-cols-2">
            <livewire:admin.dashboard.submission-status-chart />
            <livewire:admin.dashboard.top-overdue-chart />
        </div>
    </div>

    <!-- Configuration reminder popup (loads last, after the data sections) -->
    <livewire:admin.dashboard.config-reminders />

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

            // Flag clients with more than 3 overdue payrolls in red; others stay amber.
            const OVERDUE_RED_THRESHOLD = 3;
            const barColors = overdueData.counts.map(c => c > OVERDUE_RED_THRESHOLD ? '#ef4444' : '#f59e0b');
            const barHoverColors = overdueData.counts.map(c => c > OVERDUE_RED_THRESHOLD ? '#dc2626' : '#d97706');

            console.log('Initializing Top Overdue Clients Chart:', overdueData);

            window.topOverdueChartInstance = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: overdueData.labels,
                    datasets: [{
                        label: 'Overdue Payrolls',
                        data: overdueData.counts,
                        backgroundColor: barColors,
                        hoverBackgroundColor: barHoverColors,
                        borderRadius: 6,
                        borderSkipped: 'start',
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
</div>
