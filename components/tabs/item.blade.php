@props([
    'tab' => null,
    'rel' => null,
    'href' => null,
    'icon' => null,
    'label' => null,
    'value' => null,
    'count' => null,
    'current' => false,
    'newtab' => false,
])

@php
$rel ??= data_get($tab, 'rel');
$href ??= data_get($tab, 'href');
$icon ??= data_get($tab, 'icon');
$label ??= data_get($tab, 'label');
$value ??= data_get($tab, 'value');
$count ??= data_get($tab, 'count');
$current ??= data_get($tab, 'current');
$newtab ??= data_get($tab, 'newtab');

$element = $href ? 'a' : 'button';

$classes = Arr::toCssClasses([
    'self-stretch transition-colors duration-200 hover:bg-zinc-50 dark:hover:bg-zinc-600 md:grow',
    'flex items-center gap-2 justify-center',
    'data-[active]:bg-white dark:data-[active]:bg-zinc-500',
    'data-[active]:shadow-sm',
    'data-[active]:font-medium',
    'data-[active]:whitespace-nowrap',
    'data-[active]:w-max',
    'dark:data-[active]:text-zinc-100',
    'not-[data-active]:truncate',
    'not-[data-active]:text-zinc-400',
]);

$merges = [
    'href' => $href,
    'type' => $element === 'button' ? 'button' : null,
    'rel' => $element === 'a' ? $rel : null,
    'target' => $element === 'a' && $newtab ? '_blank' : null,
];
@endphp

<{{ $element }}
@if ($value) x-on:click.stop="$dispatch('input', {{ js($value) }})" @endif
@if ($current) data-active @endif
{{ $attributes->class($classes)->merge($merges) }}>
    @if ($icon)
        <x-dynamic-component :component="'atom::icon.'.$icon" class="shrink-0" />
    @endif

    @if ($slot->isNotEmpty())
        {{ $slot }}
    @else
        {!! $label !!}
    @endif
</{{ $element }}>
