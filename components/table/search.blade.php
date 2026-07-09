@props([
    'placeholder' => 'Search',
])

<div class="relative" data-atom-table-search>
    <atom:input
        icon="search"
        {{ $attributes->merge(['placeholder' => $placeholder]) }}
        x-on:keyup.enter.prevent="$dispatch('table-filter:changed'); $wire.$refresh()" />

    {{-- Scoped to the search's own $refresh so it only spins on search, not on
         pagination/sort. Rows stay visible (no skeleton swap). --}}
    <div
    wire:loading
    wire:target="$refresh"
    class="absolute inset-y-0 right-0 z-1 flex items-center pr-3 text-zinc-400">
        <atom:icon.loading class="size-4" />
    </div>
</div>
