<x-layouts.app :title="__('Invoices')">
    <div class="flex h-full w-full flex-1 flex-col gap-6">
        <!-- Page Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">All Invoices</h1>
                <p class="text-sm text-zinc-600 dark:text-zinc-400">View and manage all contractor payroll invoices</p>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="grid gap-4 md:grid-cols-3">
            <flux:card class="space-y-2 p-4 sm:p-6 dark:bg-zinc-900 rounded-lg">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-zinc-600 dark:text-zinc-400">Pending Invoices</p>
                        <p class="text-2xl font-bold text-orange-600 dark:text-orange-400">{{ $stats['pending_invoices'] }}</p>
                    </div>
                    <div class="rounded-full bg-orange-100 dark:bg-orange-900/30 p-3">
                        <flux:icon.clock class="size-6 text-orange-600 dark:text-orange-400" />
                    </div>
                </div>
            </flux:card>

            <flux:card class="space-y-2 p-4 sm:p-6 dark:bg-zinc-900 rounded-lg">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-zinc-600 dark:text-zinc-400">Paid Invoices</p>
                        <p class="text-2xl font-bold text-green-600 dark:text-green-400">{{ $stats['paid_invoices'] }}</p>
                    </div>
                    <div class="rounded-full bg-green-100 dark:bg-green-900/30 p-3">
                        <flux:icon.check-circle class="size-6 text-green-600 dark:text-green-400" />
                    </div>
                </div>
            </flux:card>

            <flux:card class="space-y-2 p-4 sm:p-6 dark:bg-zinc-900 rounded-lg">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-zinc-600 dark:text-zinc-400">Total Invoiced</p>
                        <p class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">RM {{ number_format($stats['total_invoiced'], 2) }}</p>
                    </div>
                    <div class="rounded-full bg-blue-100 dark:bg-blue-900/30 p-3">
                        <flux:icon.document-text class="size-6 text-blue-600 dark:text-blue-400" />
                    </div>
                </div>
            </flux:card>
        </div>

        <!-- Invoices Table -->
        <flux:card class="p-4 sm:p-6 dark:bg-zinc-900 rounded-lg">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">All Invoices</h2>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-zinc-200 dark:border-zinc-700">
                            <th class="pb-3 text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Invoice #</th>
                            <th class="pb-3 text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Contractor</th>
                            <th class="pb-3 text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Period</th>
                            <th class="pb-3 text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Workers</th>
                            <th class="pb-3 text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Amount</th>
                            <th class="pb-3 text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Issue Date</th>
                            <th class="pb-3 text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Due Date</th>
                            <th class="pb-3 text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Status</th>
                            <th class="pb-3 text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                        @forelse($invoices as $invoice)
                        <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                            <td class="py-3 text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                INV-{{ str_pad($invoice->id, 4, '0', STR_PAD_LEFT) }}
                            </td>
                            <td class="py-3 text-sm text-zinc-900 dark:text-zinc-100">
                                <div>
                                    <div class="font-medium">{{ $invoice->user->name ?? 'N/A' }}</div>
                                    <div class="text-xs text-zinc-600 dark:text-zinc-400">{{ $invoice->contractor_clab_no }}</div>
                                </div>
                            </td>
                            <td class="py-3 text-sm text-zinc-900 dark:text-zinc-100">{{ $invoice->month_year }}</td>
                            <td class="py-3 text-sm text-zinc-600 dark:text-zinc-400">{{ $invoice->total_workers }}</td>
                            <td class="py-3 text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                RM {{ number_format($invoice->total_amount, 2) }}
                                @if($invoice->has_penalty)
                                    <span class="text-xs text-red-600 dark:text-red-400">
                                        (+RM {{ number_format($invoice->penalty_amount, 2) }})
                                    </span>
                                @endif
                            </td>
                            <td class="py-3 text-sm text-zinc-600 dark:text-zinc-400">
                                {{ $invoice->submitted_at ? $invoice->submitted_at->format('M d, Y') : '-' }}
                            </td>
                            <td class="py-3 text-sm text-zinc-600 dark:text-zinc-400 {{ now()->gt($invoice->payment_deadline) && $invoice->status !== 'paid' ? 'text-red-600 dark:text-red-400 font-semibold' : '' }}">
                                {{ $invoice->payment_deadline->format('M d, Y') }}
                            </td>
                            <td class="py-3">
                                @if($invoice->status === 'draft')
                                    <flux:badge color="zinc" size="sm">Draft</flux:badge>
                                @elseif($invoice->status === 'pending_payment')
                                    <flux:badge color="orange" size="sm">Pending</flux:badge>
                                @elseif($invoice->status === 'paid')
                                    <flux:badge color="green" size="sm">Paid</flux:badge>
                                @elseif($invoice->status === 'overdue')
                                    <flux:badge color="red" size="sm">Overdue</flux:badge>
                                @endif
                            </td>
                            <td class="py-3">
                                <div class="flex gap-2">
                                    <flux:tooltip content="View Invoice">
                                        <flux:button
                                            variant="ghost"
                                            size="xs"
                                            icon="eye"
                                            title="View Invoice"
                                            href="{{ route('invoices.show', $invoice->id) }}"
                                        />
                                    </flux:tooltip>
                                    <flux:tooltip content="Download Invoice">
                                        <flux:button
                                            variant="ghost"
                                            size="xs"
                                            icon="arrow-down-tray"
                                            title="Download Invoice"
                                            href="{{ route('invoices.download', $invoice->id) }}"
                                        />
                                    </flux:tooltip>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="9" class="py-8 text-center text-zinc-600 dark:text-zinc-400">
                                No invoices found.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            @if($invoices->hasPages())
            <div class="mt-4 border-t border-zinc-200 dark:border-zinc-700 pt-4">
                {{ $invoices->links() }}
            </div>
            @endif
        </flux:card>
    </div>
</x-layouts.app>
