@php
// The inline filter pills on the consumer's Tailwind: ?tw=floor|latest, ?dark=1, ?accent=%23rrggbb
$tw = in_array(request()->query('tw'), ['floor', 'latest'], true) ? request()->query('tw') : 'latest';
$accent = preg_match('/^#[0-9a-f]{6}$/i', (string) request()->query('accent')) ? request()->query('accent') : null;
@endphp

<atom:html title="E2E: Table Filters Inline" :vite="[]" :dark="request()->query('dark') === '1'" :styles="['/atom/e2e/consumer-css/'.$tw]">
@if ($accent)
<style>:root { --color-accent: {{ $accent }}; }</style>
@endif

<div x-data="{ filters: { customer: null, state: 'overdue', issued: null, multi: [], region: null, zone: 'e', bsingle: null, priority: null, bmulti: [], region_card: null, region_modal: null, region_popover: null, zones_card: [], zones_modal: [], zones_popover: [] } }" class="grid gap-8 p-6">
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
                {{-- not clearable: the chip still has its x, or no chip could clear it --}}
                <atom:select variant="filter" add :clearable="false" x-model="filters.priority" data-filter-key="filters.priority" label="Priority" :options="[
                    ['value' => 'high', 'label' => 'High'],
                    ['value' => 'low', 'label' => 'Low'],
                ]" />
                <atom:select variant="filter" add multiple x-model="filters.multi" data-filter-key="filters.multi" label="Tag" :options="[
                    ['value' => 'x', 'label' => 'Tag X'],
                    ['value' => 'y', 'label' => 'Tag Y'],
                ]" />
            </atom:table.filters>
        </atom:table.toolbar>
    </div>

    {{-- the search INSIDE the filters (the other placement): it still comes first --}}
    <div data-testid="inline-inside" class="rounded-panel border bg-surface p-3">
        <atom:table.filters chips="inline">
            <atom:table.search data-filter-key="filters.q" placeholder="Search inside"/>
            <atom:select variant="filter" add x-model="filters.zone" data-filter-key="filters.zone" label="Zone" :options="[
                ['value' => 'e', 'label' => 'East'],
            ]" />
            <atom:select variant="filter" add x-model="filters.region" data-filter-key="filters.region" label="Region" :options="[
                ['value' => 'n', 'label' => 'North'],
            ]" />
        </atom:table.filters>
    </div>
    {{-- chips="below" keeps its own chips on a row of their own, and an add pill that steps aside only for a single value --}}
    <div data-testid="below" class="rounded-panel border bg-surface p-3">
        <atom:table.filters>
            <atom:select variant="filter" add x-model="filters.bsingle" data-filter-key="filters.bsingle" label="Kind" :options="[
                ['value' => 'k1', 'label' => 'Kind One'],
            ]" />
            <atom:select variant="filter" add multiple x-model="filters.bmulti" data-filter-key="filters.bmulti" label="Group" :options="[
                ['value' => 'g1', 'label' => 'Group One'],
                ['value' => 'g2', 'label' => 'Group Two'],
            ]" />
        </atom:table.filters>
    </div>

    {{-- the "More filters" button in each overflow mode, behind an active chip: it is the LAST control in the row --}}
    @foreach (['card', 'modal', 'popover'] as $mode)
        <div data-testid="overflow-{{ $mode }}" class="rounded-panel border bg-surface p-3">
            <atom:table.filters chips="inline" :overflow="$mode === 'popover' ? null : $mode">
                <atom:select variant="filter" add x-model="filters.state" data-filter-key="filters.state" label="Status" :options="[
                    ['value' => 'paid', 'label' => 'Paid'],
                    ['value' => 'overdue', 'label' => 'Overdue'],
                ]" />
                <x-slot:more>
                    <atom:select variant="filter" add x-model="filters.region_{{ $mode }}" data-filter-key="filters.region_{{ $mode }}" label="Region" :options="[
                        ['value' => 'n', 'label' => 'North'],
                    ]" />
                    <atom:select variant="filter" add multiple x-model="filters.zones_{{ $mode }}" data-filter-key="filters.zones_{{ $mode }}" label="Zones" :options="[
                        ['value' => 'z1', 'label' => 'Zone 1'],
                        ['value' => 'z2', 'label' => 'Zone 2'],
                    ]" />
                </x-slot:more>
            </atom:table.filters>
        </div>
    @endforeach
</div>

@livewireScripts
</atom:html>
