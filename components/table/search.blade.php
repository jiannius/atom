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

// The chip is named by the placeholder, and the bar appends its own ':', so a
// trailing "..." or ellipsis would read "Search customers...:".
$chipLabel = preg_replace('/[\s.\x{2026}]+$/u', '', t($placeholder)) ?: t($placeholder);

// The bound model, when there is one, is what the chip follows when something other
// than the box changes it (a host reset, a .live round trip).
$wireModel = $attributes->wire('model')->value();

$onEnter = ($filterKey ? 'emit(); ' : '')."\$dispatch('table-filter:changed'); \$wire.\$refresh()";
@endphp

<div
class="relative"
@if ($filterKey)
x-data="{
    unwatch: null,
@if ($wireModel)
    {{-- report only: no refresh and no 'changed', so it cannot loop back into a request.
         A method, not x-init: x-init runs its last expression, and $watch returns the
         function that stops it, so ending x-init on it would unwatch at once. --}}
    init () {
        this.unwatch = this.$wire.$watch(@js($wireModel), value => this.report(value))
    },
    destroy () {
        this.unwatch?.()
    },
@endif
    report (value) {
        value = String(value ?? '').trim()

        this.$dispatch('table-filter:set', { key: @js($filterKey), label: @js($chipLabel), display: value === '' ? null : value })
    },
    emit () {
        // $el is whichever element is calling (the input on Enter, the wrapper otherwise)
        this.report(this.$el.closest('[data-atom-table-search]').querySelector('input').value)
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
