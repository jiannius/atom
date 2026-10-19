@props([
    'color' => null,
])

{{-- A count or a tag on a nav row: the same tones as <atom:badge> (Color::tone() maps a colour name), at nav size. --}}
@php
$class = Arr::toCssClasses([
    'text-xs text-center font-medium rounded-pill px-1.5 py-px min-w-5 tabular-nums',

    match (\Jiannius\Atom\Services\Color::tone($color)) {
        'info' => 'bg-info-bg text-info-fg',
        'success' => 'bg-success-bg text-success-fg',
        'warning' => 'bg-warning-bg text-warning-fg',
        'danger' => 'bg-danger-bg text-danger-fg',
        'accent' => 'bg-accent-soft text-accent-ink',
        'inverse' => 'bg-fg text-surface',
        default => 'bg-neutral-bg text-neutral-fg',
    },
]);
@endphp

<span {{ $attributes->class($class) }}>{{ $slot }}</span>
