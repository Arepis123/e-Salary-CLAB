<div class="grid gap-4 xl:grid-cols-2">
    @for($i = 0; $i < 2; $i++)
        <flux:card class="p-4 sm:p-6 bg-white dark:bg-zinc-900 rounded-lg">
            <div class="mb-4 space-y-2">
                <flux:skeleton animate="shimmer" class="h-5 w-48 rounded" />
                <flux:skeleton animate="shimmer" class="h-4 w-64 rounded" />
            </div>
            <flux:skeleton animate="shimmer" class="h-72 w-full rounded" />
        </flux:card>
    @endfor
</div>
