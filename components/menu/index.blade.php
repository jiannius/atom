@props([
    'popover' => false,
])

@php
$classes = Arr::toCssClasses([
    /* a raised layer: the raised surface, the strong hairline and the one shadow token. The 4px of padding and the
       6px radius of an item make the panel's 10px corner concentric. */
    '[:where(&)]:min-w-48 p-1 text-fg',
    'rounded-panel shadow-pop',
    'border border-border-strong',
    'bg-surface-raised',
]);
@endphp

{{-- An open popover menu keeps its own attributes through a morph (floating-ui's inline style); see helpers/morphed.js.
     Not a static wire:ignore.self: a closed menu must keep taking server-driven attributes. --}}
<div {{ $attributes->class($classes)->merge(['role' => 'menu']) }} data-atom-menu {{ $popover ? 'popover' : '' }}>
    {{ $slot }}
</div>
