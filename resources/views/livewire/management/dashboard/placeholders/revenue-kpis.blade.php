<div class="space-y-4">
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        @for($i = 0; $i < 4; $i++)
            <flux:card class="space-y-3 p-4 sm:p-6 bg-white dark:bg-zinc-900 rounded-lg">
                <div class="flex items-start justify-between">
                    <div class="space-y-2">
                        <flux:skeleton animate="shimmer" class="h-4 w-32 rounded" />
                        <flux:skeleton animate="shimmer" class="h-8 w-28 rounded" />
                        <flux:skeleton animate="shimmer" class="h-3 w-20 rounded" />
                    </div>
                    <flux:skeleton animate="shimmer" class="size-12 rounded-full hidden xl:block" />
                </div>
                <flux:skeleton animate="shimmer" class="h-3 w-full rounded" />
                <flux:skeleton animate="shimmer" class="h-3 w-24 rounded" />
            </flux:card>
        @endfor
    </div>

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        @for($i = 0; $i < 4; $i++)
            <flux:card class="space-y-2 p-4 bg-white dark:bg-zinc-900 rounded-lg">
                <flux:skeleton animate="shimmer" class="h-3 w-24 rounded" />
                <flux:skeleton animate="shimmer" class="h-6 w-16 rounded" />
                <flux:skeleton animate="shimmer" class="h-3 w-20 rounded" />
            </flux:card>
        @endfor
    </div>
</div>
