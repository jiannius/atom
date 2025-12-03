@props([
    'tabs' => [],
    'size' => null,
    'variant' => null,
])

<div @class([
    'select-none',

    'p-1 bg-zinc-100 dark:bg-zinc-700' => $variant === 'button',
    'border-b border-zinc-300 dark:border-zinc-700' => !$variant,

    'h-8' => $size === 'sm',
    'h-10' => !$size,
    '[[data-atom-table-toolbar]_&]:h-12',

    'rounded' => $variant === 'button' && $size === 'sm',
    'rounded-md' => $variant === 'button' && !$size,
]) data-atom-tabs>
    <div {{ $attributes->class(['inline-flex items-center h-full']) }}>
        @foreach ($tabs as $tab)
            @if ($attributes->wire('model') && data_get($tab, 'value'))
                <atom:tabs.item :tab="$tab" x-bind:data-active="$wire.{{ $attributes->wire('model')->value() }} === {{ js(data_get($tab, 'value')) }}" />
            @else
                <atom:tabs.item :tab="$tab" />
            @endif
        @endforeach

        {{ $slot }}
    </div>
</div>