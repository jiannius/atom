@props([
    'href' => null,
    'icon' => null,
    'iconSuffix' => null,
    'variant' => null,
    'rel' => 'noopener noreferrer nofollow',
    'newtab' => false,
])

@php
$classes = Arr::toCssClasses([
    'underline underline-offset-5 decoration-dotted cursor-pointer',
    $variant === 'accent' ? 'text-accent' : 'text-sky-600 dark:text-white',
    $icon || $iconSuffix ? 'inline-flex items-center gap-2' : '',
]);

$merges = [
    'href' => $href,
    'rel' => $rel,
    'target' => $newtab ? '_blank' : null,
    'aria-label' => strip_tags($slot->toHtml()),
];
@endphp

<a {{ $attributes->class($classes)->merge($merges) }}>
    @if ($icon)
        <x-dynamic-component :component="'atom::icon.'.$icon" class="shrink-0"/>
    @endif

    @if ($slot->isEmpty())    
        {{ $href }}
    @else
        {{ $slot }}
    @endif

    @if ($iconSuffix)
        <x-dynamic-component :component="'atom::icon.'.$iconSuffix" class="shrink-0"/>
    @endif
</a>
