@props([
    'kind' => 'checkbox',
    'tile' => false,
])

{{-- The drawn box of a choice control (checkbox, radio, toggle), after the sr-only native input that is its `peer`: `kind` picks the shape and the
     name of the control's `group/...` (the error outline reads `group-has-[.error]/<kind>`), `tile` the selectable-tile look (no focus or error outline of its own: the
     tile's label draws them from atom.css; marked data-atom-tile-indicator). A toggle is never a tile. The classes are written out in full so Tailwind can find each one. --}}
@php
$tile = $tile && $kind !== 'toggle';

$classes = Arr::toCssClasses([
    match ($kind) {
        'toggle' => 'group h-5 w-9 relative inline-flex items-center rounded-full transition',
        'radio' => 'size-4 rounded-full flex items-center justify-center',
        default => 'size-4 rounded flex items-center justify-center',
    },

    match ($kind) {
        'checkbox' => 'bg-surface border border-fg-subtle text-surface',
        default => 'bg-surface border border-fg-subtle',
    },

    match ($kind) {
        'toggle' => 'peer-checked:bg-accent-ink peer-checked:border-accent-ink',
        default => 'peer-checked:border-accent-ink peer-checked:bg-accent-ink peer-checked:*:opacity-100',
    },

    'peer-checked:[&>span]:translate-x-[19px] peer-checked:[&>span]:bg-surface' => $kind === 'toggle',

    $tile ? 'peer-disabled:opacity-50' : 'peer-disabled:opacity-50 peer-disabled:cursor-not-allowed',

    'peer-focus-visible:outline-2 peer-focus-visible:outline-offset-1 peer-focus-visible:outline-focus' => ! $tile,

    match ($kind) {
        'toggle' => 'group-has-[.error]/toggle:outline-1 group-has-[.error]/toggle:outline-danger-fg group-has-[.error]/toggle:outline-offset-1',
        'radio' => 'group-has-[.error]/radio:outline-1 group-has-[.error]/radio:outline-danger-fg group-has-[.error]/radio:outline-offset-1',
        default => 'group-has-[.error]/checkbox:outline-1 group-has-[.error]/checkbox:outline-danger-fg group-has-[.error]/checkbox:outline-offset-1',
    } => ! $tile,
]);
@endphp

<div
aria-hidden="true"
{{ $tile ? 'data-atom-tile-indicator' : '' }}
@class([$classes])>
    @if ($kind === 'toggle')
        <span class="size-3 rounded-full transition translate-x-[3px] bg-fg-subtle"></span>
    @elseif ($kind === 'radio')
        <div class="size-1.5 opacity-0 rounded-full bg-surface"></div>
    @else
        <div class="size-3 opacity-0 flex items-center justify-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="size-3" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
        </div>
    @endif
</div>
