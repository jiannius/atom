@props([
    'size' => null,
])

@php
$classes = Arr::toCssClasses([
    'w-full',
    'py-4 text-lg' => $size === 'lg',
    'py-8 text-xl' => $size === 'xl',
    'py-0' => !$size,
    'flex items-center' => $slot->isNotEmpty(),
]);
@endphp

<div role="none" data-atom-separator {{ $attributes->class($classes) }}>
    <div class="border-0 bg-zinc-800/15 h-px w-full dark:bg-zinc-600"></div>

    @if ($slot->isNotEmpty())
        <span class="shrink mx-4 font-medium text-zinc-400 whitespace-nowrap text-center">{{ $slot }}</span>
        <div class="border-0 bg-zinc-800/15 h-px w-full"></div>
    @endif
</div>
