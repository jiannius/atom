@props([
    'kbd' => null,
])

@php
/* The inverse of the surface it floats over (the fg as a fill, the canvas as its text): dark on a light page and light on a
   dark one, 15:1 either way. It is a label, not a panel, so it has no hairline and no shadow. */
$classes = Arr::toCssClasses([
    'relative px-2 py-1',
    'rounded-nav',
    'text-xs text-canvas font-medium',
    'bg-fg',
    'overflow-visible',
]);
@endphp

{{-- tooltip.js writes the id, and floating-ui the inline style, on this node. An open tooltip keeps its own attributes
     through a morph (helpers/morphed.js). The key is fixed because Livewire keys a node by its id: with the id JS wrote
     on one side only, the two would not match and the morph would replace the open popover with a closed copy. --}}
<div popover="manual" wire:key="atom-tooltip-content" {{ $attributes->class($classes) }} data-atom-tooltip-content>
    {{ $slot }}

    @if ($kbd)
        <span class="ps-1 text-canvas/70">{{ $kbd }}</span>
    @endif
</div>