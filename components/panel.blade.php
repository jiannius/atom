@props([
    'heading' => null,
    'count' => null,
    'level' => 2,
    'padded' => false,
])

{{-- A panel: the box a list lives in, edge to edge. Where `<atom:card>` is padded and scrolls, a panel has no padding of its own and
     CLIPS (`overflow-clip`, and `overflow: clip` on `[data-atom-panel]` in atom.css so a rig without Tailwind clips too), so a table, saved
     views and a toolbar run to its edges and its rounded corners cut them. It must never be `overflow-hidden`: that makes the box a scroll
     container, and a table's pager, pinned to the bottom of the screen while the table runs past it, would stick to a box that never
     scrolls. A wide table scrolls sideways inside the table's own scroll box, never the page.
     `heading` (a string through t(), or an `<x-slot:heading>`) is the section's title, 14px semibold at `level` 2, 3 or 4 (default 2),
     then `count` (printed as given, muted: a total, "128"), then the `actions` slot (a link or a ghost button) and the `menu` slot
     (`<atom:menu.item>`s behind a ghost ⋯) at the end, over a hairline; no heading, actions or menu, no row. The default slot is the
     body, unpadded, for `<atom:table frameless>`, `<atom:table.views>` and a `<atom:table.toolbar strip>`; `padded` pads it (12px, the
     cells' own) for text. The `footer` slot is a muted, wrapping row under a hairline for "4 of 8 invoices" or a totals line. The edges
     are the cells' padding (`px-row`), so the title lines up with the first column. --}}
@php
$has = fn ($s) => isset($s) && $s->hasActualContent();
@endphp

<div {{ $attributes->class('min-w-0 overflow-clip rounded-panel border bg-surface') }} data-atom-panel>
    <atom:_heading-row
        variant="panel"
        :level="$level"
        :title="$heading"
        :count="$count"
        :actions="$actions ?? null"
        :menu="$menu ?? null"
    />

    <div @class(['min-w-0', 'px-row py-3' => $padded]) data-atom-panel-body>
        {{ $slot }}
    </div>

    @if ($has($footer ?? null))
        <div {{ $footer->attributes->class('flex flex-wrap items-center gap-x-4 gap-y-1 border-t px-row py-2.5 text-xs text-fg-subtle tabular-nums') }} data-atom-panel-footer>
            {{ $footer }}
        </div>
    @endif
</div>
