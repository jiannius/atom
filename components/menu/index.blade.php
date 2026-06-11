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

<div {{ $attributes->class($classes)->merge(['role' => 'menu']) }} data-atom-menu {{ $popover ? 'popover' : '' }}>
    {{ $slot }}
</div>
