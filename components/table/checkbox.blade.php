@props([
    'label' => null,
])

{{-- A real checkbox to the keyboard and to a screen reader, drawn as before: a focusable `role="checkbox"` box named by `label`
     (the cell and the header pass their own; a host sets a row's through `checkbox-label` on <atom:table.cell checkbox>), Space
     toggles it by clicking itself (so the wiring is the click handler the cell or header gives it, once), and the state is
     `data-checked` for the styling and tests plus `aria-checked`. The select-all one also has a mixed state (some, not all, of the
     page's rows are selected): `data-mixed` and `aria-checked="mixed"`, drawn like checked with a dash instead of the tick. Wherever the caller binds `x-bind:aria-checked` Alpine owns it, as it
     owns `data-checked`: a Livewire morph then never resets it to a server-rendered value. A static one reads `data-checked`.
     It is a div rather than a native input because the box holds the check icon, and the JS and the tests address it as
     `[data-atom-table-checkbox]`. The row's href guard already skips `[role=checkbox]`, so Space and a click never open the row. --}}
@php
$ariaChecked = $attributes->has('x-bind:aria-checked') ? null : ($attributes->has('data-mixed') ? 'mixed' : ($attributes->has('data-checked') ? 'true' : 'false'));
@endphp

<div
role="checkbox"
tabindex="0"
aria-label="{{ $label ?: t('atom::messages.select-row') }}"
@if ($ariaChecked) aria-checked="{{ $ariaChecked }}" @endif
x-on:keydown.space.prevent="$event.repeat || $el.click()"
{{ $attributes->class([
    'size-4 rounded grid place-items-center cursor-pointer',
    'bg-surface border border-fg-subtle text-surface',
    'data-[checked]:border-accent-ink data-[checked]:bg-accent-ink',
    'data-[mixed]:border-accent-ink data-[mixed]:bg-accent-ink',
]) }}
data-atom-table-checkbox>
    {{-- the tick and the dash share the one cell; the focus ring is the base :focus-visible rule in atom.tailwind.css --}}
    <atom:icon.check class="size-3 col-start-1 row-start-1 opacity-0 [[data-checked]:not([data-mixed])>&]:opacity-100" data-atom-checkbox-tick />
    <atom:icon.minus class="size-3 col-start-1 row-start-1 opacity-0 [[data-mixed]>&]:opacity-100" data-atom-checkbox-dash />
</div>
