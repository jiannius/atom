@props([
    'popover' => false,
])

@php
$classes = Arr::toCssClasses([
    '[:where(&)]:min-w-48 p-[.3125rem]',
    'rounded-lg shadow-xs',
    'border border-zinc-200 dark:border-zinc-700',
    'bg-white dark:bg-zinc-800',
    'focus:outline-hidden',
]);
@endphp

{{-- An open popover menu keeps its own attributes through a morph (floating-ui's inline style); see helpers/morphed.js.
     Not a static wire:ignore.self: a closed menu must keep taking server-driven attributes. --}}
<div {{ $attributes->class($classes)->merge(['role' => 'menu']) }} data-atom-menu {{ $popover ? 'popover' : '' }}>
    {{ $slot }}
</div>
