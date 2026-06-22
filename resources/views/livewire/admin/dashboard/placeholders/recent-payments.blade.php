<flux:card class="order-last lg:order-first lg:col-span-2 min-w-0 overflow-hidden p-4 sm:p-6 bg-white dark:bg-zinc-900 rounded-lg">
    <div class="mb-4 flex items-center justify-between">
        <flux:skeleton animate="shimmer" class="h-6 w-40 rounded" />
        <flux:skeleton animate="shimmer" class="h-6 w-16 rounded" />
    </div>
    <flux:skeleton.group animate="shimmer" class="space-y-3">
        @for($i = 0; $i < 5; $i++)
            <div class="flex items-center gap-4 py-3 border-b border-zinc-200 dark:border-zinc-700">
                <flux:skeleton class="h-4 w-32 rounded" />
                <flux:skeleton class="h-4 w-20 rounded" />
                <flux:skeleton class="h-5 w-16 rounded-full" />
            </div>
        @endfor
    </flux:skeleton.group>
</flux:card>
