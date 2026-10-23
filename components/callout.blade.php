@props([
    'icon' => null,
    'heading' => null,
    'content' => null,
    'variant' => null,
    'closeable' => false,
])

@php
$hasActions = isset($actions) && $actions->isNotEmpty();

$icon ??= match ($variant) {
    'info' => 'info',
    'warning' => 'warning',
    'success' => 'check-circle',
    'danger', 'error' => 'error',
    default => 'announce',
};

/* A tone's tint and a hairline of its ink; the heading takes the tone's ink and the text stays fg, so the body reads at full
   contrast. An unknown variant is the neutral tint. */
$tone = match ($variant) {
    'accent' => 'accent',
    'info' => 'info',
    'success' => 'success',
    'warning' => 'warning',
    'danger', 'error' => 'danger',
    default => 'neutral',
};

$classes = Arr::toCssClasses([
    '[:where(&)]:relative [:where(&)]:w-full rounded-panel border px-4 py-3 text-fg',
    'pe-11' => $closeable,
    match ($tone) {
        'accent' => 'bg-accent-soft border-accent-ink/25 [&_[data-atom-icon]]:text-accent-ink [&_[data-atom-callout-heading]]:text-accent-ink',
        'info' => 'bg-info-bg border-info-fg/20 [&_[data-atom-icon]]:text-info-fg [&_[data-atom-callout-heading]]:text-info-fg',
        'success' => 'bg-success-bg border-success-fg/20 [&_[data-atom-icon]]:text-success-fg [&_[data-atom-callout-heading]]:text-success-fg',
        'warning' => 'bg-warning-bg border-warning-fg/20 [&_[data-atom-icon]]:text-warning-fg [&_[data-atom-callout-heading]]:text-warning-fg',
        'danger' => 'bg-danger-bg border-danger-fg/20 [&_[data-atom-icon]]:text-danger-fg [&_[data-atom-callout-heading]]:text-danger-fg',
        default => 'bg-neutral-bg border-border [&_[data-atom-icon]]:text-fg-muted',
    },
]);
@endphp

<div x-data="{ show: true }" x-show="show" {{ $attributes->class($classes) }}>
    <div class="flex gap-3">
        @if ($icon)
            <x-dynamic-component :component="'atom::icon.'.$icon" class="size-4 shrink-0 mt-[3px]" variant="solid" />
        @endif

        <div class="flex flex-col gap-1 min-w-0 flex-1">
            @if ($heading)
                @if ($heading instanceof \Illuminate\View\ComponentSlot)
                    <div data-atom-callout-heading>{{ $heading }}</div>
                @else
                    <div class="font-semibold" data-atom-callout-heading>{{ t($heading) }}</div>
                @endif
            @endif

            @if ($slot->isNotEmpty())
                {{ $slot }}
            @elseif ($content)
                {{ t($content) }}
            @endif

            {{-- a decision box: the buttons under the text, the one the reader is meant to press last (and primary) --}}
            @if ($hasActions)
                <div {{ $actions->attributes->class('mt-2 flex flex-wrap items-center gap-2') }} data-atom-callout-actions>
                    {{ $actions }}
                </div>
            @endif
        </div>
    </div>

    @if ($closeable)
        <button
        type="button"
        x-on:click.stop="show = false"
        aria-label="{{ t('Dismiss') }}"
        class="absolute top-2 right-2 size-7 flex items-center justify-center rounded-control text-fg-muted transition-colors hover:bg-fg/[0.06] hover:text-fg">
            <atom:icon.close class="size-4"/>
        </button>
    @endif
</div>
