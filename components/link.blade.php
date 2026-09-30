@props([
    'href' => null,
    'icon' => null,
    'iconSuffix' => null,
    'variant' => null,
    'rel' => 'noopener noreferrer nofollow',
    'newtab' => false,
])

@php
// a link that was given an href which safe_url() refused goes nowhere, so it
// must not look or act like one (a link with no href at all may carry wire:click)
$blocked = filled($href) && safe_url($href) === null;
$href = safe_url($href);

$classes = Arr::toCssClasses([
    'underline underline-offset-5 decoration-dotted',
    'cursor-pointer' => !$blocked,
    $variant === 'accent' ? 'text-accent' : 'text-sky-600 dark:text-zinc-300',
    $icon || $iconSuffix ? 'inline-flex items-center gap-2' : '',
]);

$merges = [
    'href' => $href,
    'rel' => $blocked ? null : $rel,
    'target' => $newtab && !$blocked ? '_blank' : null,
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
