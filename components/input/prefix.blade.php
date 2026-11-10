@props([
    'prefix' => null,
    'suffix' => null,
])

@php
/* The box takes the faint border of a disabled field beside it (a control prints `data-atom-disabled`, whether its own or from a disabled form), and the
   focus border when that field is focused: stacked after it, because two `group-has` utilities have no order of their own. */
$class = Arr::toCssClasses([
    'h-control px-2.5 border border-control-border bg-surface-sunken text-sm text-fg-muted flex items-center justify-center',
    'group-has-[[data-atom-disabled]]:border-border',
    'group-has-[:focus-visible]:border-focus group-has-[:focus-visible]:group-has-[[data-atom-disabled]]:border-focus',
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
