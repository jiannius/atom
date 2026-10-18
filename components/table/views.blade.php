@props([
    'counts' => [],
    'current' => null,
    'label' => null,
])

{{-- Saved views: a strip of tabs over a list ("All", "Unpaid", "Overdue") that each set one filter value, with a count
     beside the name. Put it at the top of the panel the table sits in, above <atom:table.toolbar>.

     Bind it with wire:model (add .live to refetch at once): the bound property holds the chosen view's `value`, so
     a component that uses WithUrlFilters can carry the view in the URL. Pass `current` as well to draw the first paint
     right (the browser reads the bound property only after Alpine starts); with no binding, `current` is all the
     strip knows and an item with an `href` is a plain link.

     The host supplies the counts and atom never runs a count query: `counts` is an array keyed by the item's `value`
     (`['all' => 151, 'unpaid' => 12]`) or a closure that receives the `value` and returns the count
     (`fn ($value) => $this->count($value)`). A closure runs once per item on every render of the page, so keep it
     cheap (a cached or grouped count); an item's own `count` wins over both. A count prints as text. --}}
@php
$model = $attributes->wire('model')->value();
$current = is_scalar($current) ? (string) $current : null;
@endphp

<nav
{{ $attributes->class('flex list-none gap-0.5 overflow-x-auto overflow-y-hidden border-b px-2.5 pb-px')->merge(['aria-label' => $label ? t($label) : t('Saved views')]) }}
x-data="{ value: {{ $model ? '$wire.$get('.js($model).')' : js($current) }} }"
@if ($model) x-modelable="value" @endif
data-atom-table-views>
    {{ $slot }}
</nav>
