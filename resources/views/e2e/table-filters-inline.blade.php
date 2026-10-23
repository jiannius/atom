@php
// The inline filter pills on the consumer's Tailwind: ?tw=floor|latest, ?dark=1, ?accent=%23rrggbb
$tw = in_array(request()->query('tw'), ['floor', 'latest'], true) ? request()->query('tw') : 'latest';
$accent = preg_match('/^#[0-9a-f]{6}$/i', (string) request()->query('accent')) ? request()->query('accent') : null;
@endphp

<atom:html title="E2E: Table Filters Inline" :vite="[]" :dark="request()->query('dark') === '1'" :styles="['/atom/e2e/consumer-css/'.$tw]">
@if ($accent)
<style>:root { --color-accent: {{ $accent }}; }</style>
@endif

<div x-data="{ filters: { customer: null, state: 'overdue', issued: null, multi: [] } }" class="grid gap-8 p-6">
    <div data-testid="inline" class="rounded-panel border bg-surface p-3">
        <atom:table.toolbar>
            <atom:table.search data-filter-key="filters.search" placeholder="Search number or customer" />

            <atom:table.filters chips="inline">
                <atom:select variant="filter" add x-model="filters.customer" data-filter-key="filters.customer" label="Customer" :options="[
                    ['value' => 'a', 'label' => 'Customer A'],
                    ['value' => 'b', 'label' => 'Customer B'],
                ]" />
                <atom:select variant="filter" add x-model="filters.state" data-filter-key="filters.state" label="Status" :options="[
                    ['value' => 'paid', 'label' => 'Paid'],
                    ['value' => 'overdue', 'label' => 'Overdue'],
                ]" />
                <atom:date-picker variant="range" add x-model="filters.issued" data-filter-key="filters.issued" placeholder="Issued" />
                <atom:select variant="filter" add multiple x-model="filters.multi" data-filter-key="filters.multi" label="Tag" :options="[
                    ['value' => 'x', 'label' => 'Tag X'],
                    ['value' => 'y', 'label' => 'Tag Y'],
                ]" />
            </atom:table.filters>
        </atom:table.toolbar>
    </div>
</div>

@livewireScripts
</atom:html>
