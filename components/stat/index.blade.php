@props([
    'label' => null,
    'value' => null,
    'prefix' => null,
    'delta' => null,
    'inverse' => false,
    'breakdown' => null,
    'tone' => null,
    'href' => null,
])

{{-- One figure of a totals strip: label, a tabular value with an optional small prefix (a currency), an optional
     delta and a breakdown line. Sized by its content, never a fixed height. With an `href` it is a link (a clickable
     filter). `delta` reads as up or down from its sign; `inverse` flips which direction is good (overdue going down). --}}
@php
$href = safe_url($href);
$el = $href ? 'a' : 'div';

$tone = in_array($tone, ['danger', 'warning', 'success'], true) ? $tone : null;

$delta = is_scalar($delta) ? trim((string) $delta) : '';
// only a figure has a direction: `n/a`, `new` or a dash is printed as it is, with no arrow, tint or word
$direction = match (true) {
    ! preg_match('/^[+\-−]?\d/u', $delta) => null,
    str_starts_with($delta, '-') || str_starts_with($delta, '−') => 'down',
    (bool) preg_match('/^\+?0+([.,]0+)?%?$/', $delta) => 'flat',
    default => 'up',
};
$isGood = $direction && $direction !== 'flat' ? (($direction === 'up') xor (bool) $inverse) : null;

$classes = Arr::toCssClasses([
    'block min-w-0 rounded-panel border bg-surface px-[18px] py-3.5 text-start',
    'transition-colors hover:bg-surface-sunken' => $href,
]);

$valueClasses = Arr::toCssClasses([
    'text-[20px] leading-[1.3] font-semibold tracking-[-0.015em] tabular-nums break-words',

    match ($tone) {
        'danger' => 'text-danger-fg',
        'warning' => 'text-warning-fg',
        'success' => 'text-success-fg',
        default => '',
    },
]);

$deltaClasses = Arr::toCssClasses([
    'inline-flex items-center gap-0.5 font-medium tabular-nums',
    'text-fg-subtle' => $isGood === null,
    'text-success-fg' => $isGood === true,
    'text-danger-fg' => $isGood === false,
]);
@endphp

<{{ $el }} {{ $attributes->class($classes)->merge(['href' => $href]) }} data-atom-stat>
    <span class="block text-fg-muted">{{ t($label) }}</span>

    <span class="mt-[3px] block {{ $valueClasses }}" data-atom-stat-value>
        @if (filled($prefix))
            <small class="me-[3px] text-xs font-medium tracking-normal text-fg-subtle">{{ $prefix }}</small>
        @endif
        @if ($slot->isNotEmpty())
            {{ $slot }}
        @else
            {{ $value }}
        @endif
    </span>

    @if ($delta !== '' || filled($breakdown))
        <span class="mt-[3px] flex flex-wrap items-center gap-x-2 text-xs text-fg-subtle" data-atom-stat-sub>
            @if ($delta !== '')
                <span class="{{ $deltaClasses }}" data-atom-stat-delta @if ($direction) data-direction="{{ $direction }}" @endif>
                    @if ($direction === 'up')
                        <atom:icon.arrow-up class="size-3"/>
                        <span class="sr-only">{{ t('Up') }}</span>
                    @elseif ($direction === 'down')
                        <atom:icon.arrow-down class="size-3"/>
                        <span class="sr-only">{{ t('Down') }}</span>
                    @endif
                    {{ $delta }}
                </span>
            @endif

            @if (filled($breakdown))
                <span>{{ t($breakdown) }}</span>
            @endif
        </span>
    @endif
</{{ $el }}>
