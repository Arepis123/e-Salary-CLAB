<x-layouts.guest>
    <div class="flex min-h-screen w-full flex-1 flex-col items-center justify-center gap-6 px-4">
        <div class="text-center max-w-2xl">
            <!-- Icon -->
            <div class="mb-6">
                <svg class="mx-auto h-24 w-24 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
            </div>

            <!-- Error Message -->
            <h1 class="text-4xl font-bold text-zinc-900 dark:text-zinc-100 mb-4">
                Session Expired
            </h1>

            <p class="text-lg text-zinc-600 dark:text-zinc-400 mb-6">
                Your session has expired due to inactivity. Please refresh the page to continue.
            </p>

            <!-- Details -->
            <flux:callout class="my-3">
                <flux:callout.heading>What happened?</flux:callout.heading>
                <flux:callout.text>
                    <ul class="text-sm text-start">
                        <li>• Your session expired after being inactive for too long</li>
                        <li>• This is a security feature to protect your account</li>
                        <li>• Any unsaved changes may have been lost</li>
                    </ul>                
                </flux:callout.text>
            </flux:callout>            

            <!-- Suggestions -->
            <flux:callout>
                <flux:callout.heading>What should I do?</flux:callout.heading>
                <flux:callout.text>
                    <ul class="text-sm text-start">
                        <li>• Click the "Refresh Page" button below to reload and log in again</li>
                        <li>• Make sure to save your work frequently</li>
                        <li>• Consider completing long tasks in smaller steps</li>
                        <li>• If this happens frequently, contact support</li>
                    </ul>                
                </flux:callout.text>
            </flux:callout>     

            <!-- Actions -->
            <div class="flex flex-col sm:flex-row gap-4 justify-center my-3">
                <flux:button variant="primary" onclick="window.location.reload()">
                    <flux:icon.arrow-path class="size-4" />
                    Refresh Page
                </flux:button>

                <flux:button variant="outline" href="{{ route('dashboard') }}">
                    <flux:icon.home class="size-4" />
                    Return to Dashboard
                </flux:button>

                @guest
                <flux:button variant="outline" href="{{ route('login') }}">
                    <flux:icon.log-in class="size-4" />
                    Login Again
                </flux:button>
                @endguest
            </div>

            <!-- Support Info -->
            <div class="mt-8 text-sm text-zinc-500 dark:text-zinc-500">
                <p>Error Code: 419 - Page Expired</p>
                <p class="mt-1">If you continue experiencing this issue, please contact support.</p>
            </div>
        </div>
    </div>
</x-layouts.guest>
