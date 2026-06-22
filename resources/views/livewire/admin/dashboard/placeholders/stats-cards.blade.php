<div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
    @for($i = 0; $i < 4; $i++)
        <flux:card class="space-y-2 p-4 sm:p-6 bg-white dark:bg-zinc-900 rounded-lg">
            <div class="flex items-center justify-between">
                <div class="space-y-2">
                    <flux:skeleton animate="shimmer" class="h-4 w-28 rounded" />
                    <flux:skeleton animate="shimmer" class="h-8 w-16 rounded" />
                </div>
                <flux:skeleton animate="shimmer" class="size-12 rounded-full hidden lg:hidden xl:block" />
            </div>
            <flux:skeleton animate="shimmer" class="h-4 w-32 rounded" />
        </flux:card>
    @endfor
</div>
