{{-- Skeleton rows for the Out of Sync tab while drift detection runs --}}
<div class="flex flex-col gap-4 py-2">
    @for ($i = 0; $i < 4; $i++)
        <div class="flex items-center gap-4">
            <flux:skeleton class="h-4 w-6" animate="pulse" />
            <flux:skeleton class="h-4 w-24" animate="pulse" />
            <flux:skeleton class="h-4 flex-1" animate="pulse" />
            <flux:skeleton class="h-4 w-20 hidden md:block" animate="pulse" />
            <flux:skeleton class="h-7 w-32" animate="pulse" />
        </div>
    @endfor
</div>
