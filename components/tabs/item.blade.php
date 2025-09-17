@aware(['variant', 'size'])

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
@class([
    'grow self-stretch transition-colors duration-200 text-muted-foreground dark:text-muted',
    'flex items-center gap-2 justify-center px-4',
    'not-[data-active]:truncate',
    'hover:text-zinc-800 dark:hover:text-white',
    
    'rounded' => $variant === 'button',

    '-mb-px pb-px' => !$variant,

    'data-[active]:font-medium',
    'data-[active]:whitespace-nowrap',
    'data-[active]:w-max',
    'dark:data-[active]:text-white',

    'data-[active]:bg-white' => $variant === 'button',
    'dark:data-[active]:bg-zinc-500' => $variant === 'button',
    'data-[active]:shadow-sm' => $variant === 'button',

    'data-[active]:border-b-2' => !$variant,
    'data-[active]:border-zinc-800' => !$variant,
    'dark:data-[active]:border-white' => !$variant,
    'data-[active]:text-zinc-800' => !$variant,
])
{{ $attributes->merge($merges)->except('class') }}>
    @if ($icon)
        <x-dynamic-component :component="'atom::icon.'.$icon" class="shrink-0" />
    @endif

    @if ($slot->isNotEmpty())
        {{ $slot }}
    @else
        {!! $label !!}
    @endif
</{{ $element }}>
