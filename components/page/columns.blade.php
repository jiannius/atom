@props([])

{{-- A main column and a rail: the default slot is the main column, the `rail` slot is a 320px <aside> beside it (`<x-slot:rail label="Work
     order details" sticky>`: `label` is the aside's accessible name, "Details" without one (an `aria-label` of your own on the slot wins), and
     `sticky` keeps the rail under the shell's bar while it sits beside the main column, scrolling inside itself when it is taller than the
     window leaves; it is on for a bare `sticky`, `true`, `'true'` and `'1'`, and off for `false`, `0`, `null`, an empty string and `'false'`).
     Whether the rail sits beside or under the main column depends on the room the columns have, not on the window: the
     layout is a wrapping flex row (atom.css, `[data-atom-page-columns]`, in `@layer base` so a utility of yours wins) in which the main column
     keeps at least 60% of the width and the rail wraps below it when it no longer fits beside it, so a collapsed sidebar or a wider shell
     brings it beside the main column sooner. The page's 22px gap sits between the main column's blocks and between the two columns; the
     rail's cards are 14px apart. Put the default slot's blocks straight in it, as in a page. --}}
@php
$hasRail = isset($rail) && $rail->hasActualContent();
$sticky = $hasRail && filter_var($rail->attributes->get('sticky'), FILTER_VALIDATE_BOOLEAN);
$label = $hasRail ? $rail->attributes->get('label') : null;
$named = $hasRail && $rail->attributes->has('aria-label');

/* a slot's bound attributes are escaped already; a bag prints them without escaping twice */
$railAttributes = $hasRail
    ? new \Illuminate\View\ComponentAttributeBag([
        ...$rail->attributes->except(['label', 'sticky'])->getAttributes(),
        ...(filled($label) ? ['aria-label' => $label] : ($named ? [] : ['aria-label' => t('Details')])),
        ...($sticky ? ['data-sticky' => true] : []),
    ])
    : null;
@endphp

<div {{ $attributes }} data-atom-page-columns>
    <div data-atom-page-columns-main>{{ $slot }}</div>

    @if ($hasRail)
        <aside {{ $railAttributes }} data-atom-page-columns-rail>
            {{ $rail }}
        </aside>
    @endif
</div>
