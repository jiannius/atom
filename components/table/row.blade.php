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
// - a click that lands on an interactive descendant is that control's, not the row's: a link, button, field, label, summary,
//   contenteditable, an element with an interactive role (button, link, menuitem, checkbox, switch), a `wire:click` or `x-on:click`
//   (the exact attribute: `wire:click.prevent` or `@click` is not matched), a `tabindex`, or `data-atom-row-ignore`. The row itself has a
//   tabindex, so only an element strictly inside it counts (see the check below).
if ($href) {
    $target = \Illuminate\Support\Js::from($href)->toHtml();
    $go = $attributes->has('wire:navigate') ? "Livewire.navigate($target)" : "window.location.href = $target";
    $interactive = 'a, button, input, select, textarea, label, summary, [contenteditable], [role=button], [role=link], [role=menuitem], [role=checkbox], [role=switch], [wire\\\\:click], [x-on\\\\:click], [tabindex], [data-atom-row-ignore]';
    $guard = "const hit = e.target.closest?.('$interactive'); if (hit && hit !== e.currentTarget && e.currentTarget.contains(hit)) return;";
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
