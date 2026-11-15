@props([
    'value' => null,
    'tone' => null,
    'label' => null,
    'running' => false,
])

{{-- A dot plus a word, coloured by tone. The colour is never the only signal: the word is always printed, so pass a
     `label` or a slot (with neither, the tone's own name is printed). `running` pulses the dot (static under
     prefers-reduced-motion, see atom.css). The classes are written out in full so Tailwind can find each one.
     `value` is a state read from a backed enum (or an array or object with `label` and `color` or `tone`). The tone is the
     first of: the `tone` prop, the enum's own `tone()`, its `color()` mapped through Color::tone() (green success, yellow
     warning, red danger, blue info, gray neutral; black and a hex fall to neutral), then neutral. The word is the first of: the
     slot, the `label` prop, the enum's `label()`, a `label` key, then the tone's name. An enum or array label prints as text,
     not through t(), as the badge does. --}}
@php
$tones = ['neutral', 'info', 'success', 'warning', 'danger', 'accent'];

$fromValue = function (string $key) use ($value) {
    if (is_object($value) && method_exists($value, $key)) {
        return $value->{$key}();
    }

    return is_array($value) || (is_object($value) && ! is_enum($value)) ? data_get($value, $key) : null;
};

$derived = $fromValue('tone');
$derived = is_string($derived) && in_array($derived, $tones, true) ? $derived : \Jiannius\Atom\Services\Color::tone($fromValue('color'));

$tone = filled($tone) ? $tone : $derived;
$tone = in_array($tone, $tones, true) ? $tone : 'neutral';

$valueLabel = $fromValue('label');
$valueLabel = is_string($valueLabel) || $valueLabel instanceof \Stringable ? (string) $valueLabel : null;

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
    <span class="truncate">{{ $slot->isNotEmpty() ? $slot : (filled($label) ? t($label) : (filled($valueLabel) ? $valueLabel : t(ucfirst($tone)))) }}</span>
</span>@php /* the file's last newline stays out of the output (#179) */ @endphp
