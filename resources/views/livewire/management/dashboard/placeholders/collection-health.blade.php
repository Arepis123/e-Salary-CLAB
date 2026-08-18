<div class="space-y-4">
    <div class="grid gap-4 xl:grid-cols-3">
        <flux:card class="p-4 sm:p-6 bg-white dark:bg-zinc-900 rounded-lg">
            <div class="mb-4 space-y-2">
                <flux:skeleton animate="shimmer" class="h-5 w-40 rounded" />
                <flux:skeleton animate="shimmer" class="h-4 w-56 rounded" />
            </div>
            <div class="space-y-4">
                @for($i = 0; $i < 5; $i++)
                    <div class="space-y-2">
                        <flux:skeleton animate="shimmer" class="h-4 w-full rounded" />
                        <flux:skeleton animate="shimmer" class="h-2 w-full rounded-full" />
                    </div>
                @endfor
            </div>
        </flux:card>

        <flux:card class="p-4 sm:p-6 bg-white dark:bg-zinc-900 rounded-lg xl:col-span-2">
            <div class="mb-4 space-y-2">
                <flux:skeleton animate="shimmer" class="h-5 w-44 rounded" />
                <flux:skeleton animate="shimmer" class="h-4 w-72 rounded" />
            </div>
            <flux:skeleton animate="shimmer" class="h-72 w-full rounded" />
        </flux:card>
    </div>

    <div class="grid gap-4 xl:grid-cols-3">
        <flux:card class="p-4 sm:p-6 bg-white dark:bg-zinc-900 rounded-lg">
            <div class="mb-4 space-y-2">
                <flux:skeleton animate="shimmer" class="h-5 w-44 rounded" />
                <flux:skeleton animate="shimmer" class="h-4 w-56 rounded" />
            </div>
            <div class="grid grid-cols-3 gap-3">
                @for($i = 0; $i < 3; $i++)
                    <div class="space-y-2">
                        <flux:skeleton animate="shimmer" class="h-8 w-full rounded" />
                        <flux:skeleton animate="shimmer" class="h-3 w-full rounded" />
                    </div>
                @endfor
            </div>
        </flux:card>

        <flux:card class="p-4 sm:p-6 bg-white dark:bg-zinc-900 rounded-lg xl:col-span-2">
            <div class="mb-4 space-y-2">
                <flux:skeleton animate="shimmer" class="h-5 w-48 rounded" />
                <flux:skeleton animate="shimmer" class="h-4 w-72 rounded" />
            </div>
            <div class="space-y-4">
                @for($i = 0; $i < 3; $i++)
                    <div class="space-y-2">
                        <flux:skeleton animate="shimmer" class="h-4 w-full rounded" />
                        <flux:skeleton animate="shimmer" class="h-2 w-full rounded-full" />
                    </div>
                @endfor
            </div>
        </flux:card>
    </div>
</div>
