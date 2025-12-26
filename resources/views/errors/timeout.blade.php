<x-layouts.guest>
    <div class="flex min-h-screen w-full flex-1 flex-col items-center justify-center gap-6 px-4">
        <div class="text-center max-w-2xl">
            <!-- Icon -->
            <div class="mb-6">
                <svg class="mx-auto h-24 w-24 text-orange-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>

            <!-- Error Message -->
            <h1 class="text-4xl font-bold text-zinc-900 dark:text-zinc-100 mb-4">
                Request Timeout
            </h1>

            <p class="text-lg text-zinc-600 dark:text-zinc-400 mb-6">
                {{ $message ?? 'The operation took too long to complete.' }}
            </p>

            <!-- Details -->
            <div class="bg-orange-50 dark:bg-orange-900/20 border border-orange-200 dark:border-orange-800 rounded-lg p-6 mb-8 text-left">
                <h2 class="text-sm font-semibold text-orange-900 dark:text-orange-100 mb-3">
                    What happened?
                </h2>
                <ul class="text-sm text-orange-800 dark:text-orange-200 space-y-2">
                    <li>• The server took longer than expected to process your request</li>
                    <li>• This might be due to processing a large payroll submission</li>
                    <li>• Your data has been saved and is safe</li>
                </ul>
            </div>

            <!-- Suggestions -->
            <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-6 mb-8 text-left">
                <h2 class="text-sm font-semibold text-blue-900 dark:text-blue-100 mb-3">
                    What should I do?
                </h2>
                <ul class="text-sm text-blue-800 dark:text-blue-200 space-y-2">
                    <li>• Try refreshing the page to see if your action completed</li>
                    <li>• If processing a large payroll, try splitting it into smaller batches</li>
                    <li>• Wait a few moments and try again</li>
                    <li>• Contact support if the problem persists</li>
                </ul>
            </div>

            <!-- Actions -->
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <flux:button variant="primary" href="{{ url()->previous() }}">
                    <flux:icon.arrow-left class="size-4" />
                    Go Back
                </flux:button>

                <flux:button variant="outline" href="{{ route('dashboard') }}">
                    <flux:icon.home class="size-4" />
                    Return to Dashboard
                </flux:button>

                <flux:button variant="outline" onclick="window.location.reload()">
                    <flux:icon.arrow-path class="size-4" />
                    Refresh Page
                </flux:button>
            </div>

            <!-- Support Info -->
            <div class="mt-8 text-sm text-zinc-500 dark:text-zinc-500">
                <p>Error Code: 504 Gateway Timeout</p>
                <p class="mt-1">If you need assistance, please contact support with this error code.</p>
            </div>
        </div>
    </div>
</x-layouts.guest>
