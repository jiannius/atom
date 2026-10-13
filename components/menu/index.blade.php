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

{{-- A popover menu is positioned by floating-ui with an inline style, which a morph would strip while the menu is open
     (it would jump to the corner of the page). `.self` keeps the node's own attributes and still morphs what is inside it. --}}
<div {{ $attributes->class($classes)->merge(['role' => 'menu']) }} data-atom-menu {{ $popover ? 'popover wire:ignore.self' : '' }}>
    {{ $slot }}
</div>
