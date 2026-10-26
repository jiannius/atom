@php
// The filter bar, the toolbar and the pager over a real Livewire round trip, on the consumer's Tailwind: ?tw=floor|latest, ?dark=1
$tw = in_array(request()->query('tw'), ['floor', 'latest'], true) ? request()->query('tw') : 'latest';
@endphp

<atom:html title="E2E: Table Filter Bar" :vite="[]" :dark="request()->query('dark') === '1'" :styles="['/atom/e2e/consumer-css/'.$tw]">
<div class="p-6">
    <div class="rounded-panel border bg-surface" data-testid="panel">
        {{-- ?count=0 turns the toolbar's count off, ?plain=1 renders the table with no paginator, ?rows=N cuts the data to N rows --}}
        <livewire:atom-e2e-table-filter-bar
        :count="request()->query('count') !== '0'"
        :plain="request()->query('plain') === '1'"
        :limit="is_numeric(request()->query('rows')) ? (int) request()->query('rows') : null" />
    </div>
</div>

@livewireScripts
</atom:html>
