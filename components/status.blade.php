@props([
    'tone' => 'neutral',
    'label' => null,
    'running' => false,
])

{{-- A dot plus a word, coloured by tone. The colour is never the only signal: the word is always printed, so pass a
     `label` or a slot (with neither, the tone's own name is printed). `running` pulses the dot (static under
     prefers-reduced-motion, see atom.css). The classes are written out in full so Tailwind can find each one. --}}
@php
$tone = in_array($tone, ['neutral', 'info', 'success', 'warning', 'danger', 'accent'], true) ? $tone : 'neutral';

$classes = Arr::toCssClasses([
    'inline-flex h-[22px] max-w-full items-center gap-1.5 rounded-pill px-2 text-xs font-medium whitespace-nowrap',

    match ($tone) {
        'info' => 'bg-info-bg text-info-fg',
        'success' => 'bg-success-bg text-success-fg',
        'warning' => 'bg-warning-bg text-warning-fg',
        'danger' => 'bg-danger-bg text-danger-fg',
        'accent' => 'bg-accent-soft text-accent-ink',
        default => 'bg-neutral-bg text-neutral-fg',
    },
]);
@endphp

<span {{ $attributes->class($classes) }} data-atom-status data-tone="{{ $tone }}" @if ($running) data-running @endif>
    <span class="size-1.5 shrink-0 rounded-full bg-current" data-atom-status-dot aria-hidden="true"></span>
    <span class="truncate">{{ $slot->isNotEmpty() ? $slot : (filled($label) ? t($label) : t(ucfirst($tone))) }}</span>
</span>
