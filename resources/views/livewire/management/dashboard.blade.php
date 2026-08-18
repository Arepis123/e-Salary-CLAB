<div>
    <div class="flex h-full w-full flex-1 flex-col gap-6">
        <!-- Page Header -->
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">Management Overview</h1>
                <p class="text-sm text-zinc-600 dark:text-zinc-400">
                    Reporting period <span class="font-medium text-zinc-700 dark:text-zinc-300">{{ $period['label'] }}</span>
                    &middot; company revenue shown separately from pass-through payroll
                </p>
            </div>
            <div class="text-sm text-zinc-600 dark:text-zinc-400">
                {{ now()->format('l, F j, Y') }}
            </div>
        </div>

        <!-- Row 1 — headline KPIs -->
        <livewire:management.dashboard.revenue-kpis />

        <!-- Row 2 — 12-month trends -->
        <livewire:management.dashboard.trend-charts />

        <!-- Row 3 — cash & collection health -->
        <livewire:management.dashboard.collection-health />
    </div>

    <script>
        // Charts on this page are rendered by lazily-loaded Livewire children, so
        // each canvas appears at an unpredictable moment. Rather than wiring up a
        // bespoke observer per chart, every chart publishes its data as a JSON
        // payload on a hidden container and this loop renders whatever is present,
        // re-rendering only when the payload or the theme actually changes.
        (function () {
            const instances = {};
            const rendered = {};

            const FONT = 'Inter, ui-sans-serif, system-ui, sans-serif';

            function palette() {
                const dark = document.documentElement.classList.contains('dark');
                return {
                    dark: dark,
                    text: dark ? '#d4d4d8' : '#3f3f46',
                    grid: dark ? '#3f3f46' : '#e4e4e7',
                    surface: dark ? '#18181b' : '#ffffff',
                };
            }

            function money(value) {
                return 'RM ' + Number(value).toLocaleString('en-MY', { maximumFractionDigits: 0 });
            }

            function baseOptions(colors, extra) {
                return Object.assign({
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                color: colors.text,
                                padding: 16,
                                usePointStyle: true,
                                font: { family: FONT, size: 11 },
                            },
                        },
                        tooltip: {
                            backgroundColor: colors.surface,
                            titleColor: colors.text,
                            bodyColor: colors.text,
                            borderColor: colors.grid,
                            borderWidth: 1,
                            padding: 12,
                            titleFont: { family: FONT, size: 12 },
                            bodyFont: { family: FONT, size: 12 },
                        },
                    },
                }, extra || {});
            }

            function axis(colors, opts) {
                return Object.assign({
                    grid: { color: colors.grid, drawBorder: false },
                    ticks: { color: colors.text, font: { family: FONT, size: 11 } },
                }, opts || {});
            }

            // --- Revenue composition -------------------------------------------------
            function revenueChart(canvas, data, colors) {
                return new Chart(canvas, {
                    data: {
                        labels: data.labels,
                        datasets: [
                            { type: 'bar', label: 'Service charge', data: data.service_charge, backgroundColor: '#6366f1', stack: 'revenue', borderRadius: 4, maxBarThickness: 34 },
                            { type: 'bar', label: 'SST', data: data.sst, backgroundColor: '#38bdf8', stack: 'revenue', borderRadius: 4, maxBarThickness: 34 },
                            { type: 'bar', label: 'Penalty', data: data.penalty, backgroundColor: '#f59e0b', stack: 'revenue', borderRadius: 4, maxBarThickness: 34 },
                            {
                                type: 'line',
                                label: 'Payroll volume (pass-through)',
                                data: data.payroll_volume,
                                borderColor: '#a1a1aa',
                                backgroundColor: '#a1a1aa',
                                borderWidth: 2,
                                borderDash: [5, 4],
                                pointRadius: 2,
                                tension: 0.3,
                                yAxisID: 'y1',
                            },
                        ],
                    },
                    options: baseOptions(colors, {
                        plugins: {
                            legend: { position: 'bottom', labels: { color: colors.text, padding: 16, usePointStyle: true, font: { family: FONT, size: 11 } } },
                            tooltip: {
                                backgroundColor: colors.surface, titleColor: colors.text, bodyColor: colors.text,
                                borderColor: colors.grid, borderWidth: 1, padding: 12,
                                titleFont: { family: FONT, size: 12 }, bodyFont: { family: FONT, size: 12 },
                                callbacks: { label: (c) => ' ' + c.dataset.label + ': ' + money(c.parsed.y) },
                            },
                        },
                        scales: {
                            x: axis(colors, { stacked: true, grid: { display: false } }),
                            y: axis(colors, {
                                stacked: true,
                                beginAtZero: true,
                                title: { display: true, text: 'Revenue (RM)', color: colors.text, font: { family: FONT, size: 11 } },
                                ticks: { color: colors.text, font: { family: FONT, size: 11 }, callback: (v) => Number(v).toLocaleString('en-MY') },
                            }),
                            y1: axis(colors, {
                                position: 'right',
                                beginAtZero: true,
                                grid: { display: false },
                                title: { display: true, text: 'Payroll (RM)', color: colors.text, font: { family: FONT, size: 11 } },
                                ticks: { color: colors.text, font: { family: FONT, size: 11 }, callback: (v) => Number(v).toLocaleString('en-MY') },
                            }),
                        },
                    }),
                });
            }

            // --- Workers & clients ---------------------------------------------------
            function headcountChart(canvas, data, colors) {
                return new Chart(canvas, {
                    data: {
                        labels: data.labels,
                        datasets: [
                            { type: 'line', label: 'Workers on payroll', data: data.workers, borderColor: '#10b981', backgroundColor: 'rgba(16,185,129,0.12)', borderWidth: 2, fill: true, tension: 0.3, pointRadius: 2 },
                            { type: 'bar', label: 'Clients billed', data: data.clients, backgroundColor: '#c4b5fd', borderRadius: 4, maxBarThickness: 26, yAxisID: 'y1' },
                        ],
                    },
                    options: baseOptions(colors, {
                        scales: {
                            x: axis(colors, { grid: { display: false } }),
                            y: axis(colors, { beginAtZero: true, title: { display: true, text: 'Workers', color: colors.text, font: { family: FONT, size: 11 } } }),
                            y1: axis(colors, { position: 'right', beginAtZero: true, grid: { display: false }, ticks: { color: colors.text, precision: 0, font: { family: FONT, size: 11 } }, title: { display: true, text: 'Clients', color: colors.text, font: { family: FONT, size: 11 } } }),
                        },
                    }),
                });
            }

            // --- Billed vs collected -------------------------------------------------
            function collectionChart(canvas, data, colors) {
                return new Chart(canvas, {
                    data: {
                        labels: data.labels,
                        datasets: [
                            { type: 'bar', label: 'Billed', data: data.billed, backgroundColor: colors.dark ? '#3f3f46' : '#e4e4e7', borderRadius: 4, maxBarThickness: 30 },
                            { type: 'bar', label: 'Collected', data: data.collected, backgroundColor: '#10b981', borderRadius: 4, maxBarThickness: 30 },
                            { type: 'line', label: 'Collection rate', data: data.rate, borderColor: '#6366f1', backgroundColor: '#6366f1', borderWidth: 2, pointRadius: 3, tension: 0.3, yAxisID: 'y1' },
                        ],
                    },
                    options: baseOptions(colors, {
                        plugins: {
                            legend: { position: 'bottom', labels: { color: colors.text, padding: 16, usePointStyle: true, font: { family: FONT, size: 11 } } },
                            tooltip: {
                                backgroundColor: colors.surface, titleColor: colors.text, bodyColor: colors.text,
                                borderColor: colors.grid, borderWidth: 1, padding: 12,
                                titleFont: { family: FONT, size: 12 }, bodyFont: { family: FONT, size: 12 },
                                callbacks: {
                                    label: (c) => c.dataset.label === 'Collection rate'
                                        ? ' Collection rate: ' + c.parsed.y + '%'
                                        : ' ' + c.dataset.label + ': ' + money(c.parsed.y),
                                },
                            },
                        },
                        scales: {
                            x: axis(colors, { grid: { display: false } }),
                            y: axis(colors, { beginAtZero: true, ticks: { color: colors.text, font: { family: FONT, size: 11 }, callback: (v) => Number(v).toLocaleString('en-MY') } }),
                            y1: axis(colors, { position: 'right', beginAtZero: true, suggestedMax: 100, grid: { display: false }, ticks: { color: colors.text, font: { family: FONT, size: 11 }, callback: (v) => v + '%' } }),
                        },
                    }),
                });
            }

            const REGISTRY = [
                { key: 'revenue', container: 'mgmtRevenueData', canvas: 'mgmtRevenueChart', build: revenueChart },
                { key: 'headcount', container: 'mgmtHeadcountData', canvas: 'mgmtHeadcountChart', build: headcountChart },
                { key: 'collection', container: 'mgmtCollectionData', canvas: 'mgmtCollectionChart', build: collectionChart },
            ];

            function renderAll(force) {
                if (typeof Chart === 'undefined') return;

                const colors = palette();

                REGISTRY.forEach(function (chart) {
                    const container = document.getElementById(chart.container);
                    const canvas = document.getElementById(chart.canvas);

                    if (!container || !canvas) return;

                    // Signature covers both the data and the theme, so a chart is
                    // only rebuilt when something it depends on actually changed.
                    const signature = container.dataset.payload + '|' + colors.dark;
                    if (!force && rendered[chart.key] === signature) return;

                    if (instances[chart.key]) {
                        instances[chart.key].destroy();
                    }

                    instances[chart.key] = chart.build(canvas, JSON.parse(container.dataset.payload), colors);
                    rendered[chart.key] = signature;
                });
            }

            // Poll while the lazy components are still arriving, then stop.
            let attempts = 0;
            const poll = setInterval(function () {
                renderAll(false);
                if (++attempts > 80 || Object.keys(rendered).length === REGISTRY.length) {
                    clearInterval(poll);
                }
            }, 250);

            document.addEventListener('livewire:init', function () {
                Livewire.hook('morph.updated', () => setTimeout(() => renderAll(false), 50));
            });

            document.addEventListener('livewire:navigating', function () {
                Object.keys(instances).forEach((key) => {
                    instances[key].destroy();
                    delete instances[key];
                    delete rendered[key];
                });
                clearInterval(poll);
            });

            new MutationObserver(() => setTimeout(() => renderAll(false), 60))
                .observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
        })();
    </script>
</div>
