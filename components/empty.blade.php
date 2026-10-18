@props([
    'kind' => null,
    'icon' => null,
    'size' => null,
    'subtle' => false,
    'heading' => null,
    'subheading' => null,
])

{{-- Three kinds, each with a next step in the `action` slot: `first-use` (nothing created yet: the accent, and a button
     that creates the first one), `no-results` (the default: a search or filter found nothing: say how to widen it) and
     `error` (it failed to load: say how to try again). `icon`, `heading` and `subheading` override a kind's own; a
     default slot replaces the heading and subheading. The large state draws its icon in once (CSS keyframes in atom.css,
     which skip prefers-reduced-motion); `size="sm"` and `subtle` never animate. --}}
@php
$kind = in_array($kind, ['first-use', 'no-results', 'error'], true) ? $kind : 'no-results';

[$kindIcon, $kindHeading, $kindSubheading] = match ($kind) {
    'first-use' => ['sparkles', 'Nothing here yet', 'Add the first one to get started.'],
    'error' => ['warning', 'Something went wrong', 'It could not be loaded. Try again in a moment.'],
    default => ['inbox', 'No Results', 'We could not find anything.'],
};

$icon ??= $kindIcon;
$heading ??= $kindHeading;
$subheading ??= $kindSubheading;

$hasAction = isset($action) && $action->isNotEmpty();
@endphp

@if ($subtle)
    <div {{ $attributes->class('rounded-control bg-surface-sunken p-3 text-center text-sm text-fg-muted') }} data-atom-empty data-kind="{{ $kind }}">
        {{ t($heading) }}

        @if ($hasAction)
            <div class="mt-2 flex flex-wrap items-center justify-center gap-2">{{ $action }}</div>
        @endif
    </div>
@elseif ($size === 'sm')
    <div {{ $attributes->class('flex items-center justify-center w-full') }} data-atom-empty data-kind="{{ $kind }}">
        <div class="flex justify-center gap-3 py-5">
            <div class="flex shrink-0 justify-center text-fg-subtle">
                <x-dynamic-component :component="'atom::icon.'.$icon" class="size-10" />
            </div>

            <div class="grow self-center">
                @if ($slot->isNotEmpty())
                    {{ $slot }}
                @else
                    <div class="font-medium">{{ t($heading) }}</div>
                    <div class="text-fg-muted">{{ t($subheading) }}</div>
                @endif

                @if ($hasAction)
                    <div class="mt-2 flex flex-wrap items-center gap-2">{{ $action }}</div>
                @endif
            </div>
        </div>
    </div>
@else
    {{-- Every shape of the icon gets pathLength="1", so the CSS draw-in can dash a stroke of any length with one number. --}}
    @php ob_start(); @endphp
    <x-dynamic-component :component="'atom::icon.'.$icon" class="size-14" />
    @php
    $svg = preg_replace('/<(path|circle|line|polyline|polygon|ellipse|rect)\b/', '<$1 pathLength="1"', ob_get_clean());
    @endphp

    <div {{ $attributes->class('flex flex-col items-center justify-center gap-3 px-4 py-10 text-center') }} data-atom-empty data-kind="{{ $kind }}">
        <div @class(['text-accent-ink' => $kind === 'first-use', 'text-fg-subtle' => $kind !== 'first-use']) data-atom-empty-icon>
            {!! $svg !!}
        </div>

        @if ($slot->isNotEmpty())
            {{ $slot }}
        @else
            <div class="flex flex-col items-center justify-center gap-1">
                <div class="text-lg font-semibold">{{ t($heading) }}</div>
                <div class="max-w-sm text-pretty text-fg-muted">{{ t($subheading) }}</div>
            </div>
        @endif

        @if ($hasAction)
            <div class="mt-1 flex flex-wrap items-center justify-center gap-2" data-atom-empty-action>{{ $action }}</div>
        @endif
    </div>
@endif
