@props([
    'value' => 0,
    'max' => 100,
    'variant' => null,
    'size' => null,
    'indeterminate' => false,
    'label' => false,
])

@php
$percent = max(0, min(100, (int) round($value / max((float) $max, 1) * 100)));

$showLabel = $label !== false && (! $indeterminate || is_string($label));
$labelText = is_string($label) ? t($label) : $percent.'%';

$track = Arr::toCssClasses([
    'relative w-full overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-700',
    match ($size) {
        'sm' => 'h-1.5',
        default => 'h-2.5',
    },
]);

$bar = Arr::toCssClasses([
    'h-full rounded-full',
    match ($variant) {
        'success' => 'bg-green-500',
        'warning' => 'bg-yellow-500',
        'danger', 'error' => 'bg-red-500',
        'info' => 'bg-sky-500',
        default => 'bg-zinc-900 dark:bg-white',
    },
]);
@endphp

<div {{ $attributes->class(['flex flex-col gap-1.5']) }} data-atom-progress>
    @if ($showLabel)
        <div class="flex items-center justify-between text-sm text-zinc-600 dark:text-zinc-300">
            <span>{{ $labelText }}</span>
        </div>
    @endif

    <div
        class="{{ $track }}"
        role="progressbar"
        aria-valuemin="0"
        aria-valuemax="100"
        @unless ($indeterminate) aria-valuenow="{{ $percent }}" @endunless>
        @if ($indeterminate)
            <div class="{{ $bar }} absolute inset-y-0 w-2/5" style="animation: atom-progress-indeterminate 1.5s ease-in-out infinite"></div>
        @else
            <div class="{{ $bar }}" style="width: {{ $percent }}%"></div>
        @endif
    </div>
</div>
