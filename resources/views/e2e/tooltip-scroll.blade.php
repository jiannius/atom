@php
// A tooltip on a page taller than the viewport, on the consumer's Tailwind (?tw=floor|latest). Three triggers at known depths: one
// above the fold (so it can be measured at scroll 0), and two further down that the spec scrolls to, so the page is scrolled by the
// stated distance when the tooltip is measured and the trigger is already in view (hovering it scrolls nothing).
$tw = in_array(request()->query('tw'), ['floor', 'latest'], true) ? request()->query('tw') : null;
@endphp

<atom:html title="E2E: Tooltip scroll" :vite="[]" :dark="false" :styles="$tw ? ['/atom/e2e/consumer-css/'.$tw] : []">
<div class="flex h-[200px] justify-center p-8">
    <atom:tooltip content="Hello tip" data-testid="tip-root-top">
        <atom:button data-testid="tip-top">Top trigger</atom:button>
    </atom:tooltip>
</div>

<div class="h-[1300px] p-8">Spacer</div>

<div class="flex h-[200px] justify-center p-8">
    <atom:tooltip content="Hello tip" data-testid="tip-root-middle">
        <atom:button data-testid="tip-middle">Middle trigger</atom:button>
    </atom:tooltip>
</div>

<div class="h-[1300px] p-8">Spacer</div>

<div class="flex h-[200px] justify-center p-8">
    <atom:tooltip content="Hello tip" data-testid="tip-root-bottom">
        <atom:button data-testid="tip-bottom">Bottom trigger</atom:button>
    </atom:tooltip>
</div>

<div class="h-[1300px] p-8">Spacer</div>

@livewireScripts
</atom:html>
