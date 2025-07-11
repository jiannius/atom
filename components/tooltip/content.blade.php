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

<div popover="manual" {{ $attributes->class($classes) }} data-atom-tooltip-content>
    {{ $slot }}

    @if ($kbd)
        <span class="ps-1 text-zinc-300">{{ $kbd }}</span>
    @endif
</div>