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

// A bound search is a filter like the selects beside it, so it reports to
// the table filter bar the same way: a chip named by the placeholder, and a
// do-clear listener so "Clear all" (or the chip's own x) empties the box too.
// The key is the one the select and date filters use. A search with no binding
// keeps the plain enter-to-refresh behaviour.
$filterKey = $attributes->wire('model')->value() ?: $attributes->get('data-filter-key');

$onEnter = ($filterKey ? 'emit(); ' : '')."\$dispatch('table-filter:changed'); \$wire.\$refresh()";
@endphp

<div
class="relative"
@if ($filterKey)
x-data="{
    emit () {
        // $el is whichever element is calling (the input on Enter, the wrapper otherwise)
        const value = this.$el.closest('[data-atom-table-search]').querySelector('input').value.trim()

        this.$dispatch('table-filter:set', { key: @js($filterKey), label: @js(t($placeholder)), display: value === '' ? null : value })
    },
}"
x-init="$nextTick(() => emit())"
x-on:change="emit()"
x-on:table-filter:do-clear.window="
    if ($event.detail.key === @js($filterKey)) {
        const input = $el.querySelector('input')

        input.value = ''
        input.dispatchEvent(new Event('input', { bubbles: true }))
        emit()
        $dispatch('table-filter:changed')
        $wire.$refresh()
    }
"
@endif
data-atom-table-search>
    <atom:input
        icon="search"
        {{ $attributes->merge($merges) }}
        x-on:keyup.enter.prevent="{!! $onEnter !!}" />

    {{-- Scoped to the search's own $refresh so it only spins on search, not on
         pagination/sort. Rows stay visible (no skeleton swap). --}}
    <div
    wire:loading
    wire:target="$refresh"
    class="absolute inset-y-0 right-0 z-1 flex items-center pr-3 text-zinc-400">
        <atom:icon.loading class="size-4" />
    </div>
</div>
