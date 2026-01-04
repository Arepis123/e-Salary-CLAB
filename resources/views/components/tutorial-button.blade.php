@props(['page' => 'dashboard'])

<flux:button
    id="tutorial-button"
    variant="ghost"
    size="sm"
    onclick="startTutorial('{{ $page }}')"
    {{ $attributes }}
>
    <flux:icon.question-mark-circle class="size-4" />
    Tutorial
</flux:button>

@if(!auth()->user()->hasTutorialCompleted($page))
<script>
    // Auto-show tutorial for this page if not completed
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(function() {
            if (typeof startTutorial === 'function') {
                startTutorial('{{ $page }}');
            }
        }, 500);
    });
</script>
@endif
