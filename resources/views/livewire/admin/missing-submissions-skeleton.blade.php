<div class="flex h-full w-full flex-1 flex-col gap-6" aria-busy="true">
    {{-- Page header --}}
    <div class="flex flex-col gap-2">
        <flux:skeleton class="h-7 w-72" animate="pulse" />
        <flux:skeleton class="h-4 w-96 max-w-full" animate="pulse" />
    </div>

    {{-- Contractors card --}}
    <flux:card class="p-4 sm:p-6 dark:bg-zinc-900 rounded-lg">
        <div class="mb-4 flex items-center justify-between gap-4">
            <div class="flex flex-col gap-2">
                <flux:skeleton class="h-5 w-40" animate="pulse" />
                <flux:skeleton class="h-4 w-56 max-w-full" animate="pulse" />
            </div>
            <div class="hidden sm:flex gap-2">
                <flux:skeleton class="h-8 w-24" animate="pulse" />
                <flux:skeleton class="h-8 w-28" animate="pulse" />
                <flux:skeleton class="h-8 w-28" animate="pulse" />
            </div>
        </div>

        {{-- Tabs --}}
        <div class="flex gap-6 mb-4 border-b border-zinc-200 dark:border-zinc-700 pb-3">
            <flux:skeleton class="h-6 w-28" animate="pulse" />
            <flux:skeleton class="h-6 w-24" animate="pulse" />
            <flux:skeleton class="h-6 w-28" animate="pulse" />
        </div>

        {{-- Table rows --}}
        <div class="space-y-4">
            @for ($i = 0; $i < 6; $i++)
                <div class="flex items-center gap-4">
                    <flux:skeleton class="h-4 w-6" animate="pulse" />
                    <flux:skeleton class="h-4 w-24" animate="pulse" />
                    <flux:skeleton class="h-4 flex-1" animate="pulse" />
                    <flux:skeleton class="h-4 w-32 hidden md:block" animate="pulse" />
                    <flux:skeleton class="h-6 w-20" animate="pulse" />
                </div>
            @endfor
        </div>
    </flux:card>
</div>
