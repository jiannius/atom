@props([
    'status' => null,
    'size' => null,
    'icon' => null,
    'color' => null,
    'label' => null,
])

{{-- A borderless pill: a tone's tint with its own ink (4.5:1 or more, both themes), the same recipe as <atom:status> without
     the dot. `color` is a tone (neutral, info, success, warning, danger, accent), a colour name a host plausibly passes
     (Color::tone() maps it: red is danger, amber is warning, indigo is info...), `black` (a solid fg pill) or a strict hex. Inline: no newline after a closing tag (`</div>@else`), or text after the badge gets a space (#179). --}}
@php
$color ??= (is_enum($status) ? $status->color() : data_get($status, 'color'));

/* only a strict hex colour takes the hex branch, anything else is a name: a tone, or neutral when it is not one we know.
   The hex is written as #rrggbb, so nothing but a validated colour reaches the style. The tint and the ink follow the theme:
   the hex mixed into the surface (14%) behind, and the ink is the hex with its lightness held inside the accent-ink band
   (dark enough in light, light enough in dark: the recipe of accent-ink), so it holds 4.5:1 on its own tint whatever the hex is.
   A browser without relative colour keeps the first `color`, the foreground. A caller `style` is appended last so it wins. */
$hex = \Jiannius\Atom\Services\Color::opaque($color);

$style = ! $hex ? null : Arr::toCssStyles(array_filter([
    'color: var(--atom-fg)',
    'color: oklch(from '.$hex.' clamp(var(--atom-ink-min), l, var(--atom-ink-max)) c h)',
    'background-color: color-mix(in oklab, '.$hex.' 14%, var(--atom-surface))',
    $attributes->get('style'),
]));

$tone = \Jiannius\Atom\Services\Color::tone($color);

$classes = Arr::toCssClasses([
    'inline-flex items-center justify-center rounded-pill font-medium whitespace-nowrap',
    'gap-1' => $icon,

    match ($size) {
        'lg' => 'h-[26px] px-3 text-sm',
        'xs' => 'h-[18px] px-1.5 text-xs',
        default => 'h-[22px] px-2 text-xs',
    },

    ! $hex ? match ($tone) {
        'info' => 'bg-info-bg text-info-fg',
        'success' => 'bg-success-bg text-success-fg',
        'warning' => 'bg-warning-bg text-warning-fg',
        'danger' => 'bg-danger-bg text-danger-fg',
        'accent' => 'bg-accent-soft text-accent-ink',
        'inverse' => 'bg-fg text-surface',
        default => 'bg-neutral-bg text-neutral-fg',
    } : '',
]);
@endphp

{{-- The same element either way: a hex badge carries its colours in `style` (the caller's `style` folded into it), a tone badge keeps the caller's `style`
     among its attributes and names its tone. `$before` and `$after` are what the badge adds around its `data-atom-badge` mark, after the caller's attributes. --}}
@php
$before = new \Illuminate\View\ComponentAttributeBag($hex ? ['style' => e($style)] : []);
$after = new \Illuminate\View\ComponentAttributeBag($hex ? [] : ['data-tone' => $tone]);
@endphp
<div
@class([
    $classes,
    $attributes->get('class', 'max-w-xs'),
])
{{ $hex ? $attributes->except(['class', 'style']) : $attributes->except('class') }}
{{ $before }}
data-atom-badge
{{ $after }}>
    @if ($icon)
        <x-dynamic-component :component="'atom::icon.'.$icon" @class([$size === 'lg' ? 'size-4' : 'size-3.5'])/>
    @endif

    @if ($slot->isNotEmpty())
        {{ $slot }}
    @else
        <div class="grow truncate">
            @if ($label)
                {{ t($label) }}
            @elseif (is_enum($status))
                {{ $status->label() }}
            @elseif (data_get($status, 'label'))
                {{ data_get($status, 'label') }}
            @endif
        </div>
    @endif
</div>@php /* the file's last newline stays out of the output (#179) */ @endphp
