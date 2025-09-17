@props([
    'tabs' => [],
    'size' => null,
    'variant' => null,
])

@php
$classes = Arr::toCssClasses([
    'flex items-center select-none',

    'p-1 bg-zinc-100 dark:bg-zinc-700' => $variant === 'button',
    'border-b border-zinc-300 dark:border-zinc-700' => !$variant,

    'h-8' => $size === 'sm',
    'h-10' => !$size,

    'rounded' => $variant === 'button' && $size === 'sm',
    'rounded-md' => $variant === 'button' && !$size,
]);
@endphp

<div {{ $attributes->class($classes) }} data-atom-tabs>
    @if ($slot->isNotEmpty())
        {{ $slot }}
    @elseif ($tabs)
        @foreach ($tabs as $tab)
            @if ($attributes->wire('model') && data_get($tab, 'value'))
                <atom:tabs.item :tab="$tab" x-bind:data-active="$wire.{{ $attributes->wire('model')->value() }} === {{ js(data_get($tab, 'value')) }}" />
            @else
                <atom:tabs.item :tab="$tab" />
            @endif
        @endforeach
    @endif
</div>