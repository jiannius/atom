@props([
    'placeholder' => 'Search',
])

<atom:input
    icon="search"
    {{ $attributes->merge(['placeholder' => $placeholder]) }}
    x-on:keyup.enter.prevent="$wire.$refresh()"
    data-atom-table-search />
