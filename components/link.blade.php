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
    'text-accent-ink underline underline-offset-[3px] decoration-1 decoration-current/40 hover:decoration-current',
    'cursor-pointer' => !$blocked,
    $icon || $iconSuffix ? 'inline-flex items-center gap-2' : '',
]);

$merges = [
    'href' => $href,
    'rel' => $blocked ? null : $rel,
    'target' => $newtab && !$blocked ? '_blank' : null,
    'aria-label' => strip_tags($slot->toHtml()),
];
@endphp

{{-- No whitespace inside the anchor: it would be underlined, and a link in a sentence would run on past its last letter. --}}
<a {{ $attributes->class($classes)->merge($merges) }}>@if ($icon)<x-dynamic-component :component="'atom::icon.'.$icon" class="size-4 shrink-0"/>@endif{{ $slot->isEmpty() ? $href : $slot }}@if ($iconSuffix)<x-dynamic-component :component="'atom::icon.'.$iconSuffix" class="size-4 shrink-0"/>@endif</a>
