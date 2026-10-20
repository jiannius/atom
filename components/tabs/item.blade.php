@aware(['variant', 'size'])

@props([
    'tab' => null,
    'rel' => null,
    'href' => null,
    'icon' => null,
    'label' => null,
    'value' => null,
    'count' => null,
    'current' => null,
    'newtab' => null,
])

@php
$rel ??= data_get($tab, 'rel');
$href = safe_url($href ?? data_get($tab, 'href'));
$icon ??= data_get($tab, 'icon');
$label ??= data_get($tab, 'label');
$value ??= data_get($tab, 'value');
$count ??= data_get($tab, 'count');
$current ??= data_get($tab, 'current');
$newtab ??= data_get($tab, 'newtab');

$element = $href ? 'a' : 'button';

$merges = [
    'href' => $href,
    'type' => $element === 'button' ? 'button' : null,
    'rel' => $element === 'a' ? $rel : null,
    'target' => $element === 'a' && $newtab ? '_blank' : null,
];

/* The label is fg-muted on the track or the page (6:1 on either) and fg when current, in both themes. A line tab is underlined
   with a 2px fg border that overlaps the strip's hairline; a segment is a pill of the surface with its own hairline, which
   is what tells it from the sunken track now that nothing casts a shadow. The ring is the base :focus-visible rule, drawn inside. */
$classes = [
    'grow self-stretch transition-colors duration-200 text-fg-muted text-sm font-medium',
    'flex items-center gap-2 justify-center px-3',
    'not-[data-active]:truncate',
    'hover:text-fg',
    'focus-visible:-outline-offset-2',

    'rounded-nav border border-transparent' => $variant === 'button',

    '-mb-px pb-px' => !$variant,

    'data-[active]:whitespace-nowrap',
    'data-[active]:w-max',
    'data-[active]:text-fg',

    'data-[active]:bg-surface data-[active]:border-border-strong' => $variant === 'button',

    'border-b-2 border-transparent data-[active]:border-fg' => !$variant,
];
@endphp

<{{ $element }}
@if ($value) x-on:click.stop="$dispatch('tabs-input', {{ js($value) }})" @endif
@if ($current) data-active @endif
{{ $attributes->merge($merges)->class($classes) }}>
    @if ($icon)
        <x-dynamic-component :component="'atom::icon.'.$icon" class="shrink-0 size-4" />
    @endif

    @if ($slot->isNotEmpty())
        {{ $slot }}
    @else
        {{ $label }}
    @endif
</{{ $element }}>
