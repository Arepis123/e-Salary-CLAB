<div>
    <!-- Carousel News Notification Modal -->
    <div id="newsModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/30 backdrop-blur-[2px] opacity-0 invisible transition-all duration-300">
        <div class="relative w-full max-w-2xl mx-4 bg-white dark:bg-zinc-900 rounded-2xl shadow-2xl overflow-hidden transform scale-95 transition-transform duration-300">
            <button onclick="closeNewsModal()" class="absolute top-4 right-4 z-10 p-2 rounded-full bg-white/90 dark:bg-zinc-800/90 hover:bg-white dark:hover:bg-zinc-800 transition-colors">
                <flux:icon.x-mark class="size-5 text-zinc-600 dark:text-zinc-400" />
            </button>

            @if($newsItems->count() > 0)
            <div class="absolute top-4 left-1/2 transform -translate-x-1/2 flex items-center gap-4 bg-white/90 dark:bg-zinc-800/90 backdrop-blur-sm px-4 py-2 rounded-full z-10">
                <button onclick="prevSlide()" class="p-1 hover:bg-zinc-100 dark:hover:bg-zinc-700 rounded-full transition-colors">
                    <flux:icon.chevron-left class="size-4 text-zinc-600 dark:text-zinc-400" />
                </button>
                <div class="flex gap-2">
                    @foreach($newsItems as $index => $item)
                    <button onclick="goToSlide({{ $index }})" class="carousel-dot w-2 h-2 rounded-full bg-zinc-400 dark:bg-zinc-600 transition-all"></button>
                    @endforeach
                </div>
                <button onclick="nextSlide()" class="p-1 hover:bg-zinc-100 dark:hover:bg-zinc-700 rounded-full transition-colors">
                    <flux:icon.chevron-right class="size-4 text-zinc-600 dark:text-zinc-400" />
                </button>
            </div>

            <div id="carouselContainer" class="relative pt-14">
                @foreach($newsItems as $index => $news)
                    <div class="carousel-slide {{ $index === 0 ? 'active' : '' }}">
                        <div class="flex flex-col">
                            @if($news->image_path)
                            <div class="w-full relative group">
                                <img src="{{ asset('images/uploads/' . $news->image_path) }}" alt="{{ $news->title }}"
                                    class="w-full h-auto object-cover max-h-96 cursor-pointer transition-opacity hover:opacity-90"
                                    onclick="const win = window.open('{{ asset('images/uploads/' . $news->image_path) }}', '_blank'); if(win) win.opener = null;"
                                    title="Click to view full image in new tab" />
                                <div class="absolute inset-0 flex items-center justify-center bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none">
                                    <div class="bg-white/90 dark:bg-zinc-800/90 text-zinc-900 dark:text-zinc-100 px-4 py-2 rounded-lg flex items-center gap-2">
                                        <flux:icon.magnifying-glass-plus class="size-5" />
                                        <span class="font-medium">Click to view full image</span>
                                    </div>
                                </div>
                            </div>
                            @endif
                            <div class="p-6 bg-white dark:bg-zinc-900">
                                <h2 class="text-xl font-bold text-zinc-900 dark:text-zinc-100 mb-2">{{ $news->title }}</h2>
                                <p class="text-sm text-zinc-600 dark:text-zinc-400 mb-4">{!! nl2br(e($news->description)) !!}</p>
                                @if($news->button_text && $news->button_url)
                                    @php $isExternal = preg_match('/^(https?:\/\/|\/\/)/', $news->button_url); @endphp
                                    @if($isExternal)
                                        <flux:button variant="primary" href="{{ $news->button_url }}" target="_blank" rel="noopener noreferrer" onclick="closeNewsModal()">
                                            {{ $news->button_text }}
                                            <flux:icon.arrow-top-right-on-square class="size-4 ml-1" />
                                        </flux:button>
                                    @else
                                        <flux:button variant="primary" href="{{ $news->button_url }}" wire:navigate onclick="closeNewsModal()">
                                            {{ $news->button_text }}
                                        </flux:button>
                                    @endif
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
            @endif

            <div class="absolute bottom-0 left-0 right-0 h-1 bg-zinc-200 dark:bg-zinc-700">
                <div id="progressBar" class="h-full bg-blue-600 transition-all duration-300" style="width: 33.33%"></div>
            </div>
        </div>
    </div>

    <div
        class="flex h-full w-full flex-1 flex-col gap-6"
        wire:init="loadInitialData"
        x-data
        x-on:stats-loaded.window="$wire.loadDeferredData()"
    >
        <!-- Page Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">Dashboard</h1>
                <p class="text-sm text-zinc-600 dark:text-zinc-400">Welcome back, {{ auth()->user()->name }}</p>
            </div>
            <div class="flex items-center gap-3">
                <x-tutorial-button page="dashboard" />
                <div class="text-sm text-zinc-600 dark:text-zinc-400">{{ now()->format('l, F j, Y') }}</div>
            </div>
        </div>

        <!-- Critical Alerts Banner (deferred) -->
        @if($isLoadingContent)
            {{-- Hidden until content loads --}}
        @else
            @if($overduePayments->count() > 0)
            <flux:card class="p-4 sm:p-6 dark:bg-zinc-900 rounded-lg border-l-4 border-red-500 bg-red-50 dark:bg-red-900/20">
                <div class="flex items-start gap-3">
                    <flux:icon.exclamation-triangle class="size-6 text-red-600 dark:text-red-400 flex-shrink-0 mt-0.5" />
                    <div class="flex-1">
                        <h3 class="text-base font-semibold text-red-900 dark:text-red-100">Urgent: Overdue Payments Detected</h3>
                        <p class="text-sm text-red-700 dark:text-red-300 mt-1">
                            You have {{ $overduePayments->count() }} submitted {{ Str::plural('payroll', $overduePayments->count()) }} with unpaid invoices past the deadline. Late payments incur an 8% penalty.
                        </p>
                        <div class="mt-3 space-y-2">
                            @foreach($overduePayments as $overdue)
                            <div class="flex items-center justify-between p-2 bg-white dark:bg-zinc-800 rounded">
                                <div>
                                    <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ $overdue->month_year }}</span>
                                    <span class="text-xs text-zinc-600 dark:text-zinc-400 ml-2">
                                        Deadline: {{ $overdue->payment_deadline->format('M d, Y') }}
                                        ({{ $overdue->payment_deadline->diffForHumans() }})
                                    </span>
                                </div>
                                <div class="flex items-center gap-3">
                                    <span class="font-bold text-red-600 dark:text-red-400">RM {{ number_format($overdue->total_due, 2) }}</span>
                                    <flux:button variant="primary" size="sm" href="{{ route('invoices') }}" wire:navigate>Pay Now</flux:button>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </flux:card>
            @endif

            @if($draftSubmissions->count() > 0)
            <flux:card class="p-4 sm:p-6 dark:bg-zinc-900 rounded-lg">
                <div class="flex items-start gap-3">
                    <flux:icon.document-text class="size-6 text-yellow-600 dark:text-yellow-400 flex-shrink-0 mt-0.5" />
                    <div class="flex-1">
                        <h3 class="text-base font-semibold text-yellow-900 dark:text-yellow-100">Draft Payroll Not Submitted</h3>
                        <p class="text-sm text-yellow-700 dark:text-yellow-300 mt-1">
                            You have {{ $draftSubmissions->count() }} draft payroll {{ Str::plural('submission', $draftSubmissions->count()) }} that haven't been finalized.
                        </p>
                        <div class="mt-3 space-y-2">
                            @foreach($draftSubmissions as $index => $draft)
                            <div class="bg-white dark:bg-zinc-800 rounded-lg overflow-hidden">
                                <div class="w-full p-3 flex items-center justify-between {{ $draft['missing_workers'] > 0 ? 'cursor-pointer hover:bg-zinc-50 dark:hover:bg-zinc-700/50 transition-colors' : '' }}"
                                    @if($draft['missing_workers'] > 0) onclick="toggleWorkerDetails('draft-{{ $index }}')" @endif>
                                    <div class="flex items-center gap-3 flex-1">
                                        @if($draft['missing_workers'] > 0)
                                            <flux:icon.chevron-right class="size-4 text-zinc-400 transition-transform chevron-icon" id="chevron-draft-{{ $index }}" />
                                        @else
                                            <div class="size-4"></div>
                                        @endif
                                        <div class="text-left">
                                            <div class="flex items-center gap-2">
                                                <p class="font-medium text-zinc-900 dark:text-zinc-100">{{ $draft['month_label'] }}</p>
                                                <flux:badge color="yellow" size="xs">Draft</flux:badge>
                                            </div>
                                            <p class="text-xs text-zinc-600 dark:text-zinc-400">
                                                @if($draft['paid_workers'] > 0)
                                                    {{ $draft['paid_workers'] }} paid • {{ $draft['draft_workers'] }} in draft
                                                @else
                                                    {{ $draft['draft_workers'] }} of {{ $draft['total_workers'] }} workers in draft
                                                @endif
                                                @if($draft['missing_workers'] > 0)
                                                    • {{ $draft['missing_workers'] }} still missing
                                                @endif
                                            </p>
                                            <p class="text-xs text-zinc-500 dark:text-zinc-500 mt-0.5">Created {{ $draft['created_at']->diffForHumans() }}</p>
                                        </div>
                                    </div>
                                    <flux:button variant="primary" size="sm" href="{{ route('timesheet.edit', $draft['id']) }}" wire:navigate onclick="event.stopPropagation()">
                                        Complete & Submit
                                    </flux:button>
                                </div>
                                @if($draft['missing_workers'] > 0)
                                <div id="draft-{{ $index }}" class="hidden border-t border-zinc-200 dark:border-zinc-700">
                                    <div class="p-3 bg-zinc-50 dark:bg-zinc-800/50">
                                        <p class="text-xs font-medium text-zinc-700 dark:text-zinc-300 mb-2">Workers not yet added to draft:</p>
                                        <div class="space-y-1.5 max-h-60 overflow-y-auto">
                                            @foreach($draft['missing_worker_details'] as $worker)
                                            <div class="flex items-center justify-between p-2 bg-white dark:bg-zinc-900 rounded text-sm">
                                                <div class="flex items-center gap-2">
                                                    <flux:avatar size="xs" name="{{ $worker['name'] }}" />
                                                    <div>
                                                        <p class="font-medium text-zinc-900 dark:text-zinc-100">{{ $worker['name'] }}</p>
                                                        <p class="text-xs text-zinc-600 dark:text-zinc-400">ID: {{ $worker['worker_id'] }} • Passport: {{ $worker['passport'] }}</p>
                                                    </div>
                                                </div>
                                                <flux:badge color="zinc" size="xs">Not in Draft</flux:badge>
                                            </div>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                                @endif
                            </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </flux:card>
            @endif

            @if($missingSubmissions->count() > 0)
            <flux:card class="p-4 sm:p-6 dark:bg-zinc-900 rounded-lg">
                <div class="flex items-start gap-3">
                    <flux:icon.exclamation-circle class="size-6 text-red-500 dark:text-red-500 flex-shrink-0 mt-0.5" />
                    <div class="flex-1">
                        <h3 class="text-base font-semibold text-red-500 dark:text-red-400">Missing Payroll Submissions</h3>
                        <p class="text-sm text-zinc-600 dark:text-zinc-300 mt-1">
                            You have {{ $missingSubmissions->count() }} {{ Str::plural('month', $missingSubmissions->count()) }} from the past 6 months with NO payroll submission.
                        </p>
                        <div class="mt-3 space-y-2">
                            @foreach($missingSubmissions as $index => $missing)
                            <div class="bg-white dark:bg-zinc-800 rounded-lg overflow-hidden">
                                <button onclick="toggleWorkerDetails('missing-{{ $index }}')"
                                    class="w-full p-3 flex items-center justify-between hover:bg-zinc-50 dark:hover:bg-zinc-700/50 transition-colors">
                                    <div class="flex items-center gap-3 flex-1">
                                        <flux:icon.chevron-right class="size-4 text-zinc-400 transition-transform chevron-icon" id="chevron-missing-{{ $index }}" />
                                        <div class="text-left">
                                            <p class="font-medium text-zinc-900 dark:text-zinc-100">{{ $missing['month_label'] }}</p>
                                            <p class="text-xs text-zinc-600 dark:text-zinc-400">All {{ $missing['total_workers'] }} workers not submitted</p>
                                        </div>
                                    </div>
                                    <flux:button variant="outline" size="sm" href="{{ route('timesheet', ['month' => $missing['month'], 'year' => $missing['year']]) }}" wire:navigate onclick="event.stopPropagation()">
                                        Submit Now
                                    </flux:button>
                                </button>
                                <div id="missing-{{ $index }}" class="hidden border-t border-zinc-200 dark:border-zinc-700">
                                    <div class="p-3 bg-zinc-50 dark:bg-zinc-800/50">
                                        <p class="text-xs font-medium text-zinc-700 dark:text-zinc-300 mb-2">Workers that need submission:</p>
                                        <div class="space-y-1.5 max-h-60 overflow-y-auto">
                                            @foreach($missing['missing_worker_details'] as $worker)
                                            <div class="flex items-center justify-between p-2 rounded text-sm">
                                                <div class="flex items-center gap-2">
                                                    <flux:avatar size="xs" name="{{ $worker['name'] }}" />
                                                    <div>
                                                        <p class="font-medium text-zinc-900 dark:text-zinc-100">{{ $worker['name'] }}</p>
                                                        <p class="text-xs text-zinc-600 dark:text-zinc-400">ID: {{ $worker['worker_id'] }} • Passport: {{ $worker['passport'] }}</p>
                                                    </div>
                                                </div>
                                            </div>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </flux:card>
            @endif
        @endif

        <!-- Statistics Cards -->
        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
            <!-- My Workers -->
            <flux:card class="space-y-2 p-4 sm:p-6 dark:bg-zinc-900 rounded-lg">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-zinc-600 dark:text-zinc-400">My Workers</p>
                        @if($isLoadingStats)
                            <flux:skeleton animate="shimmer" class="h-8 w-16 rounded mt-1" />
                        @else
                            <p class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">{{ $stats['active_workers'] }}</p>
                            <p class="text-xs text-zinc-500 dark:text-zinc-500 mt-0.5">Active</p>
                        @endif
                    </div>
                    <div class="rounded-full bg-green-100 dark:bg-green-900/30 p-3">
                        <flux:icon.users class="size-6 text-green-600 dark:text-green-400" />
                    </div>
                </div>
                <div class="flex items-center gap-2 text-xs">
                    @if($isLoadingStats)
                        <flux:skeleton animate="shimmer" class="h-4 w-32 rounded" />
                    @elseif($stats['expiring_soon'] > 0)
                        <span class="text-orange-600 dark:text-orange-400">{{ $stats['expiring_soon'] }} Expiring Soon</span>
                    @endif
                </div>
            </flux:card>

            <!-- This Month -->
            <flux:card class="space-y-2 p-4 sm:p-6 dark:bg-zinc-900 rounded-lg">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-zinc-600 dark:text-zinc-400">This Month</p>
                        @if($isLoadingStats)
                            <flux:skeleton animate="shimmer" class="h-8 w-24 rounded mt-1" />
                        @else
                            <p class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">RM {{ number_format($paymentStats['this_month_amount'], 2) }}</p>
                        @endif
                    </div>
                    <div class="rounded-full bg-purple-100 dark:bg-purple-900/30 p-3">
                        <flux:icon.wallet class="size-6 text-purple-600 dark:text-purple-400" />
                    </div>
                </div>
                <div class="flex items-center gap-2 text-xs">
                    @if($isLoadingStats)
                        <flux:skeleton animate="shimmer" class="h-4 w-36 rounded" />
                    @elseif($paymentStats['this_month_deadline'])
                        <span class="text-zinc-600 dark:text-zinc-400">Payment deadline: {{ $paymentStats['this_month_deadline']->format('M d') }}</span>
                    @else
                        <span class="text-zinc-500 dark:text-zinc-500">No submission for this month</span>
                    @endif
                </div>
            </flux:card>

            <!-- Outstanding Balance -->
            <flux:card class="space-y-2 p-4 sm:p-6 dark:bg-zinc-900 rounded-lg">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-zinc-600 dark:text-zinc-400">Outstanding Balance</p>
                        @if($isLoadingStats)
                            <flux:skeleton animate="shimmer" class="h-8 w-24 rounded mt-1" />
                        @else
                            <p class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">RM {{ number_format($paymentStats['outstanding_balance'], 2) }}</p>
                        @endif
                    </div>
                    <div class="rounded-full bg-orange-100 dark:bg-orange-900/30 p-3">
                        <flux:icon.exclamation-circle class="size-6 text-orange-600 dark:text-orange-400" />
                    </div>
                </div>
                <div class="flex items-center gap-2 text-xs">
                    @if($isLoadingStats)
                        <flux:skeleton animate="shimmer" class="h-4 w-20 rounded" />
                    @elseif($paymentStats['outstanding_balance'] > 0)
                        <span class="text-orange-600 dark:text-orange-400">Pending & unpaid</span>
                    @else
                        <span class="text-green-600 dark:text-green-400">All paid up</span>
                    @endif
                </div>
            </flux:card>

            <!-- Paid This Year -->
            <flux:card class="space-y-2 p-4 sm:p-6 dark:bg-zinc-900 rounded-lg">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-zinc-600 dark:text-zinc-400">Paid This Year</p>
                        @if($isLoadingStats)
                            <flux:skeleton animate="shimmer" class="h-8 w-24 rounded mt-1" />
                        @else
                            <p class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">RM {{ number_format($paymentStats['year_to_date_paid'], 2) }}</p>
                        @endif
                    </div>
                    <div class="rounded-full bg-green-100 dark:bg-green-900/30 p-3">
                        <flux:icon.check-circle class="size-6 text-green-600 dark:text-green-400" />
                    </div>
                </div>
                <div class="flex items-center gap-2 text-xs">
                    <span class="text-zinc-600 dark:text-zinc-400">January - {{ now()->format('F') }} {{ now()->year }}</span>
                </div>
            </flux:card>
        </div>

        <!-- Main Content -->
        <div class="grid gap-4 lg:grid-cols-3">
            <!-- Recent Workers & Payments -->
            <div class="lg:col-span-2 space-y-4">
                <!-- Recent Workers -->
                <flux:card class="p-4 sm:p-6 dark:bg-zinc-900 rounded-lg">
                    <div class="mb-4 flex items-center justify-between">
                        <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">My Workers</h2>
                        <flux:button variant="ghost" size="sm" href="{{ route('workers') }}" wire:navigate>View all</flux:button>
                    </div>

                    @if($isLoadingContent)
                        <flux:skeleton.group animate="shimmer" class="space-y-3">
                            @for($i = 0; $i < 4; $i++)
                            <div class="flex items-center justify-between p-3 rounded-lg bg-zinc-50 dark:bg-zinc-800/50">
                                <div class="flex items-center gap-3">
                                    <flux:skeleton class="size-8 rounded-full" />
                                    <div class="space-y-1.5">
                                        <flux:skeleton class="h-4 w-32 rounded" />
                                        <flux:skeleton class="h-3 w-48 rounded" />
                                    </div>
                                </div>
                                <flux:skeleton class="h-5 w-14 rounded-full" />
                            </div>
                            @endfor
                        </flux:skeleton.group>
                    @else
                        <div class="space-y-3">
                            @forelse($recentWorkers as $worker)
                                <div class="flex items-center justify-between p-3 rounded-lg bg-zinc-50 dark:bg-zinc-800/50">
                                    <div class="flex items-center gap-3">
                                        <flux:avatar size="sm" name="{{ $worker->name }}" />
                                        <div>
                                            <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $worker->name }}</p>
                                            <p class="text-xs text-zinc-600 dark:text-zinc-400">
                                                {{ $worker->position ?? 'Worker' }} • {{ $worker->ic_number }}
                                            </p>
                                            @if($worker->contract_info)
                                                <p class="text-xs text-zinc-500 dark:text-zinc-500">
                                                    Contract: {{ $worker->contract_info->con_start->format('M d, Y') }} - {{ $worker->contract_info->con_end->format('M d, Y') }}
                                                </p>
                                            @endif
                                        </div>
                                    </div>
                                    @if($worker->contract_info && $worker->contract_info->isActive())
                                        <flux:badge color="green" size="sm">Active</flux:badge>
                                    @else
                                        <flux:badge color="zinc" size="sm">Inactive</flux:badge>
                                    @endif
                                </div>
                            @empty
                                <div class="p-6 text-center text-zinc-600 dark:text-zinc-400">
                                    <p>No workers found.</p>
                                </div>
                            @endforelse
                        </div>
                    @endif
                </flux:card>

                <!-- Payment History -->
                <flux:card class="min-w-0 overflow-hidden p-4 sm:p-6 dark:bg-zinc-900 rounded-lg">
                    <div class="mb-4 flex items-center justify-between">
                        <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">Payment History</h2>
                        <flux:button variant="ghost" size="sm" href="{{ route('payments') }}" wire:navigate>View all</flux:button>
                    </div>

                    @if($isLoadingContent)
                        <flux:skeleton.group animate="shimmer" class="space-y-3">
                            @for($i = 0; $i < 3; $i++)
                            <div class="flex items-center gap-4 py-3 border-b border-zinc-200 dark:border-zinc-700">
                                <flux:skeleton class="h-4 w-24 rounded" />
                                <flux:skeleton class="h-4 w-24 rounded" />
                                <flux:skeleton class="h-4 w-16 rounded" />
                                <flux:skeleton class="h-5 w-14 rounded-full" />
                                <flux:skeleton class="h-5 w-14 rounded-full" />
                            </div>
                            @endfor
                        </flux:skeleton.group>
                    @else
                        <!-- Mobile -->
                        <div class="sm:hidden space-y-3">
                            @forelse($recentPayments as $payment)
                                <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-3">
                                    <div class="flex items-center justify-between mb-1">
                                        <span class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $payment->month_year }}</span>
                                        @if($payment->status === 'paid')
                                            <flux:badge color="green" size="sm">Paid</flux:badge>
                                        @elseif($payment->status === 'pending_payment')
                                            <flux:badge color="yellow" size="sm">Pending</flux:badge>
                                        @elseif($payment->status === 'overdue')
                                            <flux:badge color="red" size="sm">Overdue</flux:badge>
                                        @else
                                            <flux:badge color="zinc" size="sm">{{ ucfirst($payment->status) }}</flux:badge>
                                        @endif
                                    </div>
                                    <div class="flex items-center justify-between text-xs text-zinc-600 dark:text-zinc-400">
                                        <span>
                                            @if($payment->status !== 'submitted')
                                                RM {{ number_format($payment->total_due, 2) }}
                                            @else —
                                            @endif
                                        </span>
                                        <span class="flex items-center gap-2">
                                            {{ $payment->total_workers }} {{ Str::plural('worker', $payment->total_workers) }}
                                            @if($payment->has_penalty)
                                                <flux:badge color="red" size="sm">Late</flux:badge>
                                            @endif
                                        </span>
                                    </div>
                                </div>
                            @empty
                                <p class="py-4 text-center text-sm text-zinc-600 dark:text-zinc-400">No payment history available</p>
                            @endforelse
                        </div>

                        <!-- Desktop -->
                        <div class="hidden sm:block overflow-x-auto">
                            <table class="w-full">
                                <thead>
                                    <tr class="border-b border-zinc-200 dark:border-zinc-700">
                                        <th class="pb-3 text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Month</th>
                                        <th class="pb-3 text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Amount</th>
                                        <th class="pb-3 text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Workers</th>
                                        <th class="pb-3 text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Payment</th>
                                        <th class="pb-3 text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                                    @forelse($recentPayments as $payment)
                                        <tr>
                                            <td class="py-3 text-sm text-zinc-900 dark:text-zinc-100 whitespace-nowrap">{{ $payment->month_year }}</td>
                                            <td class="py-3 text-sm font-medium text-zinc-900 dark:text-zinc-100 whitespace-nowrap">
                                                @if($payment->status !== 'submitted')
                                                    RM {{ number_format($payment->total_due, 2) }}
                                                @else
                                                    <span class="text-zinc-400 dark:text-zinc-500">—</span>
                                                @endif
                                            </td>
                                            <td class="py-3 text-sm text-zinc-600 dark:text-zinc-400 whitespace-nowrap">{{ $payment->total_workers }} {{ Str::plural('worker', $payment->total_workers) }}</td>
                                            <td class="py-3">
                                                @if($payment->has_penalty)
                                                    <flux:badge color="red" size="sm">Late</flux:badge>
                                                @else
                                                    <flux:badge color="green" size="sm">Timely</flux:badge>
                                                @endif
                                            </td>
                                            <td class="py-3">
                                                @if($payment->status === 'paid')
                                                    <flux:badge color="green" size="sm">Paid</flux:badge>
                                                @elseif($payment->status === 'pending_payment')
                                                    <flux:badge color="yellow" size="sm">Pending Payment</flux:badge>
                                                @elseif($payment->status === 'overdue')
                                                    <flux:badge color="red" size="sm">Overdue</flux:badge>
                                                @else
                                                    <flux:badge color="zinc" size="sm">{{ ucfirst($payment->status) }}</flux:badge>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="py-6 text-center text-sm text-zinc-600 dark:text-zinc-400">No payment history available</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    @endif
                </flux:card>
            </div>

            <!-- Sidebar -->
            <div class="space-y-4">
                <!-- Quick Actions (static, no loading needed) -->
                <flux:card class="p-4 sm:p-6 dark:bg-zinc-900 rounded-lg">
                    <h2 class="mb-4 text-lg font-semibold text-zinc-900 dark:text-zinc-100">Quick Actions</h2>
                    <div class="space-y-2">
                        <flux:button variant="primary" class="w-full" href="{{ route('timesheet') }}" wire:navigate>
                            <flux:icon.calendar class="size-4" />
                            Submit Timesheet
                        </flux:button>
                        <flux:button variant="outline" class="w-full" href="{{ route('workers') }}" wire:navigate>
                            <flux:icon.users class="size-4" />
                            View Workers
                        </flux:button>
                        <flux:button variant="outline" class="w-full" href="{{ route('invoices') }}" wire:navigate>
                            <flux:icon.document-text class="size-4" />
                            View Invoices
                        </flux:button>
                    </div>
                </flux:card>

                <!-- Notifications -->
                <flux:card class="p-4 sm:p-6 dark:bg-zinc-900 rounded-lg">
                    <h2 class="mb-4 text-lg font-semibold text-zinc-900 dark:text-zinc-100">Notifications</h2>

                    @if($isLoadingContent)
                        <flux:skeleton.group animate="shimmer" class="space-y-3">
                            @for($i = 0; $i < 3; $i++)
                            <div class="flex gap-3 rounded-lg bg-zinc-50 dark:bg-zinc-800/50 p-3">
                                <flux:skeleton class="size-5 rounded-full flex-shrink-0" />
                                <div class="flex-1 space-y-1.5">
                                    <flux:skeleton class="h-4 w-3/4 rounded" />
                                    <flux:skeleton class="h-3 w-full rounded" />
                                </div>
                            </div>
                            @endfor
                        </flux:skeleton.group>
                    @else
                        <div class="space-y-3">
                            @php $hasNotifications = false; @endphp

                            @if($paymentStats['this_month_deadline'] && $paymentStats['this_month_status'] !== 'paid' && $paymentStats['this_month_deadline']->isAfter(now()) && $paymentStats['this_month_deadline']->diffInDays(now()) <= 7)
                                @php $hasNotifications = true; @endphp
                                <div class="flex gap-3 rounded-lg bg-orange-50 dark:bg-orange-900/20 p-3">
                                    <flux:icon.exclamation-triangle class="size-5 flex-shrink-0 text-orange-600 dark:text-orange-400" />
                                    <div>
                                        <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Payment Deadline Approaching</p>
                                        <p class="text-xs text-zinc-600 dark:text-zinc-400">Payment due {{ $paymentStats['this_month_deadline']->format('M d, Y') }}</p>
                                    </div>
                                </div>
                            @endif

                            @if($paymentStats['unsubmitted_workers'] > 0)
                                @php $hasNotifications = true; @endphp
                                <div class="flex gap-3 rounded-lg bg-blue-50 dark:bg-blue-900/20 p-3">
                                    <flux:icon.exclamation-circle class="size-5 flex-shrink-0 text-blue-600 dark:text-blue-400" />
                                    <div>
                                        <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Timesheet Needed</p>
                                        <p class="text-xs text-zinc-600 dark:text-zinc-400">{{ $paymentStats['unsubmitted_workers'] }} {{ Str::plural('worker', $paymentStats['unsubmitted_workers']) }} need timesheet submission this month</p>
                                    </div>
                                </div>
                            @endif

                            @if($stats['expiring_soon'] > 0)
                                @php $hasNotifications = true; @endphp
                                <div class="flex gap-3 rounded-lg bg-orange-50 dark:bg-orange-900/20 p-3">
                                    <flux:icon.exclamation-triangle class="size-5 flex-shrink-0 text-orange-600 dark:text-orange-400" />
                                    <div>
                                        <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Contracts Expiring Soon</p>
                                        <p class="text-xs text-zinc-600 dark:text-zinc-400">{{ $stats['expiring_soon'] }} {{ Str::plural('contract', $stats['expiring_soon']) }} expiring within 30 days</p>
                                    </div>
                                </div>
                            @endif

                            @if($paymentStats['outstanding_balance'] > 0)
                                @php $hasNotifications = true; @endphp
                                <div class="flex gap-3 rounded-lg bg-orange-50 dark:bg-orange-900/20 p-3">
                                    <flux:icon.exclamation-circle class="size-5 flex-shrink-0 text-orange-600 dark:text-orange-400" />
                                    <div>
                                        <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Outstanding Balance</p>
                                        <p class="text-xs text-zinc-600 dark:text-zinc-400">RM {{ number_format($paymentStats['outstanding_balance'], 2) }} pending payment</p>
                                    </div>
                                </div>
                            @endif

                            @if(!$hasNotifications)
                                <div class="flex gap-3 rounded-lg bg-green-50 dark:bg-green-900/20 p-3">
                                    <flux:icon.check-circle class="size-5 flex-shrink-0 text-green-600 dark:text-green-400" />
                                    <div>
                                        <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100">All Caught Up</p>
                                        <p class="text-xs text-zinc-600 dark:text-zinc-400">No pending notifications</p>
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endif
                </flux:card>

                <!-- Company Info (static) -->
                <flux:card class="p-4 sm:p-6 dark:bg-zinc-900 rounded-lg">
                    <h2 class="mb-4 text-lg font-semibold text-zinc-900 dark:text-zinc-100">Company Info</h2>
                    <div class="space-y-2 text-sm">
                        <div>
                            <p class="text-zinc-600 dark:text-zinc-400">Company Name</p>
                            <p class="font-medium text-zinc-900 dark:text-zinc-100">{{ auth()->user()->name }}</p>
                        </div>
                        <div>
                            <p class="text-zinc-600 dark:text-zinc-400">Contact Person</p>
                            <p class="font-medium text-zinc-900 dark:text-zinc-100">{{ auth()->user()->person_in_charge ?? auth()->user()->name }}</p>
                        </div>
                        <div>
                            <p class="text-zinc-600 dark:text-zinc-400">Email</p>
                            <p class="font-medium text-zinc-900 dark:text-zinc-100">{{ auth()->user()->email }}</p>
                        </div>
                        <div>
                            <p class="text-zinc-600 dark:text-zinc-400">Phone</p>
                            <p class="font-medium text-zinc-900 dark:text-zinc-100">{{ auth()->user()->phone ?? 'Not set' }}</p>
                        </div>
                    </div>
                </flux:card>
            </div>
        </div>
    </div>

    <script>
        function toggleWorkerDetails(id) {
            const details = document.getElementById(id);
            const chevron = document.getElementById('chevron-' + id);
            if (!details || !chevron) return;
            if (details.classList.contains('hidden')) {
                details.classList.remove('hidden');
                chevron.style.transform = 'rotate(90deg)';
            } else {
                details.classList.add('hidden');
                chevron.style.transform = 'rotate(0deg)';
            }
        }

        // Carousel
        let currentSlide = 0;
        let autoSlideInterval;

        function showNewsModal() {
            const modal = document.getElementById('newsModal');
            if (modal) {
                modal.classList.remove('opacity-0', 'invisible');
                modal.querySelector('.transform').classList.remove('scale-95');
                modal.querySelector('.transform').classList.add('scale-100');
            }
        }

        function closeNewsModal() {
            const modal = document.getElementById('newsModal');
            if (modal) {
                modal.classList.add('opacity-0', 'invisible');
                modal.querySelector('.transform').classList.remove('scale-100');
                modal.querySelector('.transform').classList.add('scale-95');
                stopAutoSlide();
            }
        }

        function goToSlide(index) {
            const slides = document.querySelectorAll('.carousel-slide');
            const dots = document.querySelectorAll('.carousel-dot');
            const progressBar = document.getElementById('progressBar');
            if (slides.length === 0) return;
            slides.forEach(s => { s.classList.remove('active'); s.style.display = 'none'; });
            slides[index].style.display = 'block';
            setTimeout(() => slides[index].classList.add('active'), 10);
            dots.forEach((dot, i) => {
                if (i === index) { dot.classList.add('bg-blue-600', 'dark:bg-blue-500', 'w-6'); dot.classList.remove('bg-zinc-400', 'dark:bg-zinc-600', 'w-2'); }
                else { dot.classList.remove('bg-blue-600', 'dark:bg-blue-500', 'w-6'); dot.classList.add('bg-zinc-400', 'dark:bg-zinc-600', 'w-2'); }
            });
            if (progressBar) progressBar.style.width = (((index + 1) / slides.length) * 100) + '%';
            currentSlide = index;
            stopAutoSlide();
            startAutoSlide();
        }

        function nextSlide() { const s = document.querySelectorAll('.carousel-slide'); goToSlide((currentSlide + 1) % s.length); }
        function prevSlide() { const s = document.querySelectorAll('.carousel-slide'); goToSlide((currentSlide - 1 + s.length) % s.length); }
        function startAutoSlide() { autoSlideInterval = setInterval(nextSlide, 5000); }
        function stopAutoSlide() { if (autoSlideInterval) clearInterval(autoSlideInterval); }

        document.addEventListener('DOMContentLoaded', function () {
            setTimeout(() => { if (document.querySelectorAll('.carousel-slide').length > 0) { showNewsModal(); goToSlide(0); startAutoSlide(); } }, 500);
            document.addEventListener('keydown', function (e) {
                const modal = document.getElementById('newsModal');
                if (modal && !modal.classList.contains('invisible')) {
                    if (e.key === 'ArrowLeft') prevSlide();
                    else if (e.key === 'ArrowRight') nextSlide();
                    else if (e.key === 'Escape') closeNewsModal();
                }
            });
            document.getElementById('newsModal')?.addEventListener('click', function (e) { if (e.target === this) closeNewsModal(); });
        });

        // Trigger deferred load after stats are done
        document.addEventListener('livewire:initialized', () => {
            Livewire.hook('commit', ({ component, succeed }) => {
                succeed(() => {
                    if (component.name === 'client.dashboard' && !component.snapshot.data.isLoadingStats && component.snapshot.data.isLoadingContent) {
                        window.dispatchEvent(new Event('stats-loaded'));
                    }
                });
            });
        });
    </script>
</div>
