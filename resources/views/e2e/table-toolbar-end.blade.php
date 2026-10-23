@php
// The table toolbar on a phone with an end group of each width: ?tw=floor|latest, ?dark=1
$tw = in_array(request()->query('tw'), ['floor', 'latest'], true) ? request()->query('tw') : 'latest';
@endphp

<atom:html title="E2E: toolbar end group" :vite="[]" :dark="request()->query('dark') === '1'" :styles="['/atom/e2e/consumer-css/'.$tw]">
<div class="grid grid-cols-[minmax(0,1fr)] gap-6 p-4">
    <div class="rounded-panel border bg-surface" data-testid="toolbar-narrow">
        <atom:table.toolbar strip>
            <atom:table.search placeholder="Search"/>
            <x-slot:end>
                <atom:button variant="ghost" icon="download" aria-label="Export"><span class="max-sm:sr-only">Export</span></atom:button>
            </x-slot:end>
        </atom:table.toolbar>
    </div>

    <div class="rounded-panel border bg-surface" data-testid="toolbar-wide">
        <atom:table.toolbar strip>
            <atom:table.search placeholder="Search"/>
            <atom:table.filters>
                <atom:select variant="filter" data-filter-key="filters.status" label="Status" :options="[['value' => 'a', 'label' => 'Active']]"/>
            </atom:table.filters>
            <x-slot:end>
                <atom:button variant="ghost">Show archived</atom:button>
                <atom:button>Reconcile selected</atom:button>
            </x-slot:end>
        </atom:table.toolbar>
    </div>

    <div class="rounded-panel border bg-surface" data-testid="toolbar-trashed">
        <atom:table.toolbar strip trashed>
            <atom:table.search placeholder="Search"/>
        </atom:table.toolbar>
    </div>
</div>

@livewireScripts
</atom:html>
