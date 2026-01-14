<div class="flex h-full w-full flex-1 flex-col gap-6">
        <!-- Page Header -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">Reports & Analytics</h1>
                <p class="text-sm text-zinc-600 dark:text-zinc-400">Generate and view payroll reports</p>
            </div>
        </div>

        <!-- Report Filters -->
        <flux:card class="p-4 sm:p-6 dark:bg-zinc-900 rounded-lg">
            <h2 class="mb-4 text-lg font-semibold text-zinc-900 dark:text-zinc-100">Filter Reports</h2>
            <div class="grid gap-4 md:grid-cols-4">
                <flux:field>
                    <flux:label>Report Type</flux:label>
                    <flux:select wire:model.live="reportType" variant="listbox" placeholder="Select type">
                        <flux:select.option value="">All Reports</flux:select.option>
                        <flux:select.option value="payment">Payment Summary</flux:select.option>
                        <flux:select.option value="worker">Worker Payroll</flux:select.option>
                        <flux:select.option value="client">Client Summary</flux:select.option>
                        <!-- <flux:select.option value="monthly">Monthly Analysis</flux:select.option> -->
                        <flux:select.option value="tax">Official Receipt Report</flux:select.option>
                    </flux:select>
                </flux:field>

                <flux:field>
                    <flux:label>Period</flux:label>
                    <flux:select wire:model.live="period" variant="listbox" wire:change="filterByMonthYear($event.target.value)" placeholder="Select period">
                        @foreach($availableMonths as $month)
                            <flux:select.option value="{{ $month['value'] }}">{{ $month['label'] }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </flux:field>

                <flux:field>
                    <flux:label>Client</flux:label>
                    <flux:select wire:model.live="clientFilter" variant="listbox" placeholder="All Clients">
                        <flux:select.option value="">All Clients</flux:select.option>
                        @if($reportGenerated)
                            @foreach($clientPayments as $client)
                                <flux:select.option value="{{ $client['client'] }}">{{ $client['client'] }}</flux:select.option>
                            @endforeach
                        @endif
                    </flux:select>
                </flux:field>

                <div class="flex items-end">
                    <flux:button variant="primary" class="w-full" wire:click="generateReport" icon="document-chart-bar">
                        Generate
                    </flux:button>
                </div>
            </div>
        </flux:card>

        @if(!$reportGenerated)
            <!-- Empty State -->
            <flux:card class="p-12 text-center dark:bg-zinc-900 rounded-lg">
                <div class="mx-auto max-w-md">
                    <flux:icon.document-chart-bar class="mx-auto size-16 text-zinc-400 dark:text-zinc-600 mb-4" />
                    <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100 mb-2">No Report Generated</h3>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400 mb-4">
                        Select your report criteria above and click "Generate" to view the report data, statistics, and charts.
                    </p>
                </div>
            </flux:card>
        @else
            @if($reportType === '')
            <!-- Export Button for All Reports -->
            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" size="sm" icon="arrow-down-tray" icon-variant="outline" wire:click="exportPaymentSummary">
                    Export Payment Summary
                </flux:button>
                <flux:button variant="primary" size="sm" wire:click="exportAllReports" wire:loading.attr="disabled" wire:loading.class="opacity-50">
                    <flux:icon.arrow-down-tray class="size-4 inline" wire:loading.remove wire:target="exportAllReports" />
                    <svg wire:loading wire:target="exportAllReports" class="animate-spin size-4 inline" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span wire:loading.remove wire:target="exportAllReports">Export Detailed Report</span>
                    <span wire:loading wire:target="exportAllReports">Generating Excel...</span>
                </flux:button>
            </div>
            @endif

            @if($reportType === 'payment')
            <!-- Export Button for Payment Summary -->
            <div class="flex justify-end">
                <flux:button variant="ghost" size="sm" icon="arrow-down-tray" icon-variant="outline" wire:click="exportPaymentSummary">
                    Export Payment Summary
                </flux:button>
            </div>
            @endif

            @if($reportType === 'payment' || $reportType === '')

            <!-- Report Statistics -->
            <div class="grid gap-4 md:grid-cols-3">
            <flux:card class="space-y-2 p-4 sm:p-6 dark:bg-zinc-900 rounded-lg">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-zinc-600 dark:text-zinc-400">Total Paid ({{ \Carbon\Carbon::create($selectedYear, $selectedMonth)->format('M Y') }})</p>
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
                <p class="text-xs text-zinc-600 dark:text-zinc-400">Per worker for selected period</p>
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
            @endif

            @if($reportType === 'client')
            <!-- Export Button for Client Summary -->
            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" size="sm" icon="arrow-down-tray" icon-variant="outline" wire:click="exportClientPayments">
                    Export Client Summary
                </flux:button>
                <flux:button variant="primary" size="sm" wire:click="exportAllReports" wire:loading.attr="disabled" wire:loading.class="opacity-50">
                    <flux:icon.arrow-down-tray class="size-4 inline" wire:loading.remove wire:target="exportAllReports" />
                    <svg wire:loading wire:target="exportAllReports" class="animate-spin size-4 inline" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span wire:loading.remove wire:target="exportAllReports">Export Detailed Report</span>
                    <span wire:loading wire:target="exportAllReports">Generating Excel...</span>
                </flux:button>
            </div>
            @endif

            @if($reportType === 'client' || $reportType === '')
        <!-- Payment Summary by Client -->
        <flux:card class="p-4 sm:p-6 dark:bg-zinc-900 rounded-lg">
            <div class="mb-4 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
                <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">Payment Summary by Client</h2>
                @if($reportType === '')
                <flux:button variant="ghost" size="sm" wire:click="exportClientPayments">
                    <flux:icon.arrow-down-tray class="size-4 inline" />
                    Export CSV
                </flux:button>
                @endif
            </div>

            @if(count($clientPayments) > 0)
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b border-zinc-200 dark:border-zinc-700">
                                <th class="pb-3 text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Client Name</th>
                                <th class="pb-3 text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Total Workers</th>
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
                                    <td class="py-3 text-sm text-zinc-900 dark:text-zinc-100">RM {{ number_format($client['basic_salary'], 2) }}</td>
                                    <td class="py-3 text-sm text-zinc-900 dark:text-zinc-100">RM {{ number_format($client['overtime'], 2) }}</td>
                                    <td class="py-3 text-sm text-zinc-900 dark:text-zinc-100">RM {{ number_format($client['allowances'], 2) }}</td>
                                    <td class="py-3 text-sm text-red-600 dark:text-red-400">RM {{ number_format($client['deductions'], 2) }}</td>
                                    <td class="py-3 text-sm font-medium text-zinc-900 dark:text-zinc-100">RM {{ number_format($client['total'], 2) }}</td>
                                    <td class="py-3">
                                        <flux:badge color="{{ $client['status'] === 'Paid' ? 'green' : ($client['status'] === 'Partially Paid' ? 'blue' : 'orange') }}" size="sm">{{ $client['status'] }}</flux:badge>
                                    </td>
                                </tr>
                            @endforeach

                            <tr class="bg-zinc-100 dark:bg-zinc-800 font-semibold">
                                <td class="py-3 text-sm text-zinc-900 dark:text-zinc-100">TOTAL</td>
                                <td class="py-3 text-sm text-zinc-900 dark:text-zinc-100">{{ collect($clientPayments)->sum('workers') }}</td>
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
            @endif

            @if($reportType === 'worker')
            <!-- Export Button for Worker Payroll -->
            <div class="flex justify-end">
                <flux:button variant="primary" size="sm" wire:click="exportAllReports" wire:loading.attr="disabled" wire:loading.class="opacity-50">
                    <flux:icon.arrow-down-tray class="size-4 inline" wire:loading.remove wire:target="exportAllReports" />
                    <svg wire:loading wire:target="exportAllReports" class="animate-spin size-4 inline" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span wire:loading.remove wire:target="exportAllReports">Export Detailed Report</span>
                    <span wire:loading wire:target="exportAllReports">Generating Excel...</span>
                </flux:button>
            </div>
            @endif

            @if($reportType === 'worker' || $reportType === '')
        <!-- Worker Payroll Summary -->
        <flux:card class="p-4 sm:p-6 dark:bg-zinc-900 rounded-lg">
            <div class="mb-4 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
                <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">Top Paid Workers</h2>
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
            @endif

            @if($reportType === 'tax')
            <!-- Export Button for Tax Invoice Report -->
            <div class="flex justify-end">
                <flux:button variant="primary" size="sm" wire:click="exportAllReports" wire:loading.attr="disabled" wire:loading.class="opacity-50">
                    <flux:icon.arrow-down-tray class="size-4 inline" wire:loading.remove wire:target="exportAllReports" />
                    <svg wire:loading wire:target="exportAllReports" class="animate-spin size-4 inline" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span wire:loading.remove wire:target="exportAllReports">Export Detailed Report</span>
                    <span wire:loading wire:target="exportAllReports">Generating Excel...</span>
                </flux:button>
            </div>
            @endif

            @if($reportType === 'tax' || $reportType === '')
        <!-- Official Receipt Report -->
        <flux:card class="p-4 sm:p-6 dark:bg-zinc-900 rounded-lg">
            <div class="mb-4 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
                <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">Official Receipt List</h2>
                <div class="flex gap-2">
                    @if(count($selectedInvoices) > 0)
                        <flux:button variant="ghost" size="sm" wire:click="downloadSelectedReceipts" wire:loading.attr="disabled" wire:loading.class="opacity-50">
                            <flux:icon.arrow-down-tray class="size-4 inline" wire:loading.remove wire:target="downloadSelectedReceipts" />
                            <svg wire:loading wire:target="downloadSelectedReceipts" class="animate-spin size-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span wire:loading.remove wire:target="downloadSelectedReceipts">Download Selected ({{ count($selectedInvoices) }})</span>
                            <span wire:loading wire:target="downloadSelectedReceipts">Preparing PDFs...</span>
                        </flux:button>
                    @else
                        <flux:button variant="ghost" size="sm" disabled>
                            <flux:icon.arrow-down-tray class="size-4 inline" />
                            Download Selected
                        </flux:button>
                    @endif
                    <flux:button variant="primary" size="sm" wire:click="downloadAllReceipts" wire:loading.attr="disabled" wire:loading.class="opacity-50">
                        <flux:icon.arrow-down-tray class="size-4 inline" wire:loading.remove wire:target="downloadAllReceipts" />
                        <svg wire:loading wire:target="downloadAllReceipts" class="animate-spin size-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span wire:loading.remove wire:target="downloadAllReceipts">Download All</span>
                        <span wire:loading wire:target="downloadAllReceipts">Preparing PDFs...</span>
                    </flux:button>
                </div>
            </div>

            @if(count($taxInvoices) > 0)
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b border-zinc-200 dark:border-zinc-700">
                                <th class="pb-3 text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">
                                    <input type="checkbox"
                                           wire:model.live="selectAll"
                                           class="rounded border-zinc-300 dark:border-zinc-600">
                                </th>
                                <th class="pb-3 text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Receipt No.</th>
                                <th class="pb-3 text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Contractor Name</th>
                                <th class="pb-3 text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">CLAB No.</th>
                                <th class="pb-3 text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Total Workers</th>
                                <th class="pb-3 text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Total Amount</th>
                                <th class="pb-3 text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Paid Date</th>
                                <th class="pb-3 text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Period</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                            @foreach($taxInvoices as $invoice)
                                <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                                    <td class="py-3">
                                        <input type="checkbox"
                                               wire:model.live="selectedInvoices"
                                               value="{{ $invoice['id'] }}"
                                               class="rounded border-zinc-300 dark:border-zinc-600">
                                    </td>
                                    <td class="py-3 text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $invoice['tax_invoice_number'] }}</td>
                                    <td class="py-3 text-sm text-zinc-900 dark:text-zinc-100">{{ $invoice['contractor'] }}</td>
                                    <td class="py-3 text-sm text-zinc-600 dark:text-zinc-400">{{ $invoice['contractor_clab_no'] }}</td>
                                    <td class="py-3 text-sm text-zinc-600 dark:text-zinc-400">{{ $invoice['total_workers'] }}</td>
                                    <td class="py-3 text-sm font-medium text-zinc-900 dark:text-zinc-100">RM {{ number_format($invoice['total_amount'], 2) }}</td>
                                    <td class="py-3 text-sm text-zinc-600 dark:text-zinc-400">
                                        {{ $invoice['paid_date'] ? \Carbon\Carbon::parse($invoice['paid_date'])->format('d M Y') : '-' }}
                                    </td>
                                    <td class="py-3 text-sm text-zinc-600 dark:text-zinc-400">{{ $invoice['month_year'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-4 flex items-center justify-between text-sm text-zinc-600 dark:text-zinc-400">
                    <div>
                        Total: {{ count($taxInvoices) }} {{ Str::plural('receipt', count($taxInvoices)) }}
                        @if(count($selectedInvoices) > 0)
                            | Selected: {{ count($selectedInvoices) }}
                        @endif
                    </div>
                    <div>
                        Total Amount: RM {{ number_format(collect($taxInvoices)->sum('total_amount'), 2) }}
                    </div>
                </div>
            @else
                <div class="text-center py-12">
                    <p class="text-zinc-600 dark:text-zinc-400">No official receipts available for {{ \Carbon\Carbon::create($selectedYear, $selectedMonth)->format('F Y') }}</p>
                </div>
            @endif
        </flux:card>
            @endif
        @endif
    </div>

    @if($reportGenerated && ($reportType === 'payment' || $reportType === ''))
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
    @endif

    <script>
        // Handle receipt download event from Livewire
        document.addEventListener('livewire:init', () => {
            Livewire.on('download-receipts', (data) => {
                const params = data[0]; // Get first element which contains our parameters

                // Create a temporary form to submit the download request
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '{{ route('report.download-receipts') }}';
                form.style.display = 'none';

                // Add CSRF token
                const csrfInput = document.createElement('input');
                csrfInput.type = 'hidden';
                csrfInput.name = '_token';
                csrfInput.value = '{{ csrf_token() }}';
                form.appendChild(csrfInput);

                // Add invoice IDs
                params.invoices.forEach((id) => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'invoices[]';
                    input.value = id;
                    form.appendChild(input);
                });

                // Add month and year
                const monthInput = document.createElement('input');
                monthInput.type = 'hidden';
                monthInput.name = 'month';
                monthInput.value = params.month;
                form.appendChild(monthInput);

                const yearInput = document.createElement('input');
                yearInput.type = 'hidden';
                yearInput.name = 'year';
                yearInput.value = params.year;
                form.appendChild(yearInput);

                // Append form to body and submit
                document.body.appendChild(form);
                form.submit();

                // Clean up
                setTimeout(() => {
                    document.body.removeChild(form);
                }, 100);
            });
        });
    </script>
</div>
