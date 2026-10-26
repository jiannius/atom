@php
// The filter bar, the toolbar and the pager over a real Livewire round trip, on the consumer's Tailwind: ?tw=floor|latest, ?dark=1
$tw = in_array(request()->query('tw'), ['floor', 'latest'], true) ? request()->query('tw') : 'latest';
@endphp

<atom:html title="E2E: Table Filter Bar" :vite="[]" :dark="request()->query('dark') === '1'" :styles="['/atom/e2e/consumer-css/'.$tw]">
<div class="p-6">
    <div class="rounded-panel border bg-surface" data-testid="panel">
        <livewire:atom-e2e-table-filter-bar />
    </div>
</div>

@livewireScripts
</atom:html>
