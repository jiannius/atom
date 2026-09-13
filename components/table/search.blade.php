@props([
    'placeholder' => 'Search',
])

@php
// The search box normally has no visible label, so the placeholder is its name. A caller
// can still pass one, and then it names the field — an aria-label would win over the
// <label> and the announced name would stop matching the visible one (WCAG 2.5.3).
$merges = ['placeholder' => $placeholder];

if (!filled($attributes->get('label'))) {
    $merges['aria-label'] = t($placeholder);
}
@endphp

<div class="relative" data-atom-table-search>
    <atom:input
        icon="search"
        {{ $attributes->merge($merges) }}
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
