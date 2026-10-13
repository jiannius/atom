@props([
    'kbd' => null,
])

@php
$classes = Arr::toCssClasses([
    'relative py-1.5 px-2.5',
    'rounded-md',
    'text-sm text-white font-medium',
    'bg-zinc-800 dark:bg-zinc-700 dark:border dark:border-white/10',
    'p-0 overflow-visible',
]);
@endphp

{{-- tooltip.js writes the id, and floating-ui the inline style, on this node. An open tooltip keeps its own attributes
     through a morph (helpers/morphed.js). The key is fixed because Livewire keys a node by its id: with the id JS wrote
     on one side only, the two would not match and the morph would replace the open popover with a closed copy. --}}
<div popover="manual" wire:key="atom-tooltip-content" {{ $attributes->class($classes) }} data-atom-tooltip-content>
    {{ $slot }}

    @if ($kbd)
        <span class="ps-1 text-zinc-300">{{ $kbd }}</span>
    @endif
</div>