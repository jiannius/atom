@props([
    'tabs' => [],
    'size' => null,
])

@php
$classes = Arr::toCssClasses([
    'inline-flex flex-wrap md:flex-nowrap items-center gap-1 select-none p-1',
    'bg-zinc-100 dark:bg-zinc-700',

    match ($size) {
        'sm' => 'rounded *:rounded-sm *:text-sm *:py-1 *:px-3',
        default => 'rounded-lg *:rounded-md *:py-1.5 *:px-4',
    },
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