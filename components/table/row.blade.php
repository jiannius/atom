@props([
    'href' => null,
])

@php
$href = safe_url($href);
$clickable = $href || \Jiannius\Atom\Services\Attributes::hasLike($attributes, 'x-on:click*', 'wire:click*');

// A row with a (safe) href opens it from the keyboard and in a new tab as well as on a click. It keeps its table
// semantics: no role="link" (a <tr> that is a link stops being a row to a screen reader), no hidden "Open" text.
// - Enter on the focused row itself opens it (`.self`: Enter inside an input or on a button belongs to that control);
// - Ctrl or Cmd with a click or Enter, and a middle click, open it in a new tab (`noopener`);
// - a click that lands on an interactive descendant is that control's, not the row's. The guard walks from the event target up to
//   the row along the event's composed path (never the row itself, which has a tabindex of its own; the path is fixed at dispatch, so a
//   control whose handler removes the clicked node still counts; an open shadow root in it is not an element and is skipped) and stops at the first element that is a link, button, field,
//   label, summary, contenteditable, an element with an interactive role (button, link, menuitem, checkbox, switch), one with a
//   `tabindex` or `data-atom-row-ignore`, or one with an attribute whose NAME starts with `wire:click`, `x-on:click` or `@click`
//   (`wire:click.prevent`, `x-on:click.outside`, `@click.stop`): CSS cannot match an attribute-name prefix, so that part is a walk.
if ($href) {
    $target = \Illuminate\Support\Js::from($href)->toHtml();
    $go = $attributes->has('wire:navigate') ? "Livewire.navigate($target)" : "window.location.href = $target";
    $interactive = 'a, button, input, select, textarea, label, summary, [contenteditable], [role=button], [role=link], [role=menuitem], [role=checkbox], [role=switch], [tabindex], [data-atom-row-ignore]';
    $guard = "for (const n of e.composedPath()) { if (n === e.currentTarget) break; if (n.nodeType !== 1) continue; if (n.matches('$interactive') || n.getAttributeNames().some((a) => ['wire:click', 'x-on:click', '@click'].some((p) => a.startsWith(p)))) return }";
    $open = "(e) => { $guard if (e.ctrlKey || e.metaKey) { window.open($target, '_blank', 'noopener'); return } $go }";
    $aux = "(e) => { if (e.button !== 1) return; $guard e.preventDefault(); window.open($target, '_blank', 'noopener') }";
}
@endphp

<tr
@if ($href)
tabindex="0"
data-href="{{ $href }}"
x-on:click="{{ $open }}"
x-on:keydown.enter.self="{{ $open }}"
x-on:auxclick="{{ $aux }}"
@endif
{{ $attributes->class([
    'hover:bg-fg/[0.035]',
    $clickable ? 'cursor-pointer' : '',
    'focus-visible:bg-fg/[0.035] focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-focus' => $href,
])->except('wire:navigate') }}
data-atom-table-row>
    {{ $slot }}
</tr>
