@php
// A tooltip on a page taller than the viewport, on the consumer's Tailwind (?tw=floor|latest). The trigger sits below the fold,
// so the spec scrolls first and checks the tooltip is drawn against its trigger and not off by the scroll distance.
$tw = in_array(request()->query('tw'), ['floor', 'latest'], true) ? request()->query('tw') : null;
@endphp

<atom:html title="E2E: Tooltip scroll" :vite="[]" :dark="false" :styles="$tw ? ['/atom/e2e/consumer-css/'.$tw] : []">
<div class="h-[1400px] p-8">Spacer</div>

<div class="flex justify-center p-8">
    <atom:tooltip content="Hello tip" data-testid="tip-root">
        <atom:button data-testid="tip-trigger">Hover me</atom:button>
    </atom:tooltip>
</div>

<div class="h-[1400px] p-8">Spacer</div>

@livewireScripts
</atom:html>
