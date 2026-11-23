@props([])

{{-- A main column, a nav column and a rail: the default slot is the main column, the `nav` slot is a 200px column of navigation before it
     (a settings screen: `<x-slot:nav><atom:navlist>...</atom:navlist></x-slot:nav>`), and the `rail` slot is a 320px <aside> beside it (`<x-slot:rail label="Work
     order details" sticky>`: `label` is the aside's accessible name, "Details" without one (an `aria-label` of your own on the slot wins), and
     `sticky` keeps the rail under the shell's bar while it sits beside the main column, scrolling inside itself when it is taller than the
     window leaves; it is on for a bare `sticky`, `true`, `'true'` and `'1'`, and off for `false`, `0`, `null`, an empty string and `'false'`).
     Whether the rail sits beside or under the main column depends on the room the columns have, not on the window: the
     layout is a wrapping flex row (atom.css, `[data-atom-page-columns]`, in `@layer base` so a utility of yours wins) in which the main column
     keeps at least 60% of the width and the rail wraps below it when it no longer fits beside it, so a collapsed sidebar or a wider shell
     brings it beside the main column sooner. The page's 22px gap sits between the main column's blocks and between the two columns; the
     rail's cards are 14px apart. Put the default slot's blocks straight in it, as in a page.
     The `nav` slot is a plain <div> (the navlist in it is already the landmark), sticky under the shell's bar like a sticky rail, and an
     empty slot prints nothing. It sits beside the main column from a row 768px wide; narrower, it is a row of its own above the main column
     and the navlist becomes a strip that scrolls sideways (atom.css, `[data-atom-page-columns-nav]`; the `pageColumnsNav` factory keeps the
     current item in view). With a nav the row carries `data-has-nav`, and a save bar in the main column reaches back under the nav
     (`--atom-page-nav-offset`) so it spans nav and content while its buttons stay where the content starts. --}}
@php
$hasNav = isset($nav) && $nav->hasActualContent();
$hasRail = isset($rail) && $rail->hasActualContent();
$sticky = $hasRail && filter_var($rail->attributes->get('sticky'), FILTER_VALIDATE_BOOLEAN);
$label = $hasRail ? $rail->attributes->get('label') : null;
$named = $hasRail && $rail->attributes->has('aria-label');

/* a slot's bound attributes are escaped already; a bag prints them without escaping twice */
$navAttributes = $hasNav ? new \Illuminate\View\ComponentAttributeBag($nav->attributes->getAttributes()) : null;

$railAttributes = $hasRail
    ? new \Illuminate\View\ComponentAttributeBag([
        ...$rail->attributes->except(['label', 'sticky'])->getAttributes(),
        ...(filled($label) ? ['aria-label' => $label] : ($named ? [] : ['aria-label' => t('Details')])),
        ...($sticky ? ['data-sticky' => true] : []),
    ])
    : null;
@endphp

<div {{ $attributes }} data-atom-page-columns @if ($hasNav) data-has-nav @endif>
    @if ($hasNav)
        <div {{ $navAttributes }} data-atom-page-columns-nav>
            {{ $nav }}
        </div>
    @endif

    <div data-atom-page-columns-main>{{ $slot }}</div>

    @if ($hasRail)
        <aside {{ $railAttributes }} data-atom-page-columns-rail>
            {{ $rail }}
        </aside>
    @endif
</div>
