@props([
    'tabs' => [],
    'size' => null,
    'variant' => null,
])

@php
$model = $attributes->wire('model')->value();

$height = match (true) {
    $variant === 'button' && $size === 'sm' => 'h-control',
    $variant === 'button' => 'h-control-md',
    $size === 'sm' => 'h-control-md',
    default => 'h-control-lg',
};
@endphp

{{-- Two looks, both fills and hairlines with no shadow. The default is a line: the strip is ruled underneath and the current tab
     is underlined. `variant="button"` is a segmented control: a sunken track with the current tab as the surface. Heights come from
     the control token (the line is a control and a half; the segments are one control inside a 2px track). --}}
<div @class([
    'select-none',

    'p-0.5 bg-surface-sunken' => $variant === 'button',
    'border-b border-border' => !$variant,

    $height,

    'rounded-control' => $variant === 'button',
]) data-atom-tabs>
    <div {{ $attributes->class(['inline-flex items-center h-full']) }}
        @if ($model)
            x-data="{ value: $wire.{{ $model }} }"
            x-modelable="value"
            x-on:tabs-input="value = $event.detail"
        @endif
    >
        @foreach ($tabs as $tab)
            @if ($model && data_get($tab, 'value'))
                <atom:tabs.item :tab="$tab" x-bind:data-active="value === {{ js(data_get($tab, 'value')) }}" />
            @else
                <atom:tabs.item :tab="$tab" />
            @endif
        @endforeach

        {{ $slot }}
    </div>
</div>