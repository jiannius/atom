@props([
    'prefix' => null,
    'suffix' => null,
])

@php
$class = Arr::toCssClasses([
    'h-control px-2.5 border border-control-border bg-surface-sunken text-sm text-fg-muted flex items-center justify-center',
    'group-has-[:focus-visible]:border-focus',
]);
@endphp

<div {{ $attributes->class([
    'group flex items-center w-full',
    '[&_[data-atom-input]_input]:rounded-l-none' => !empty($prefix),
    '[&_[data-atom-input]_input]:rounded-r-none' => !empty($suffix),
    '[&_[data-atom-date-picker]_input]:rounded-l-none' => !empty($prefix),
    '[&_[data-atom-date-picker]_input]:rounded-r-none' => !empty($suffix),
]) }}>
    @if ($prefix)
        <div @class([$class, 'rounded-l-control border-r-0'])>
            {{ t($prefix) }}
        </div>
    @endif

    <div class="grow">
        {{ $slot }}
    </div>

    @if ($suffix)
        <div @class([$class, 'rounded-r-control border-l-0 -ml-px'])>
            {{ t($suffix) }}
        </div>
    @endif
</div>
