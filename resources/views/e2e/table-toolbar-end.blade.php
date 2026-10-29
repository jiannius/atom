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

    {{-- the wide end group in a 420px container (a sidebar column, a slide-over, a modal) on a wide screen: the 12rem floor of the start group
         makes it wrap, where the box's own width, not the screen's, is what is left --}}
    <div class="w-[420px] rounded-panel border bg-surface" data-testid="toolbar-narrow-box">
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

    {{-- eight active chips: more than one line of them, so the start group wraps inside itself and the end group stays at the top end --}}
    <div class="rounded-panel border bg-surface" data-testid="toolbar-many" x-data="{ f: { a: 'a1', b: 'b1', c: 'c1', d: 'd1', e: 'e1', f: 'f1', g: 'g1', h: 'h1' } }">
        <atom:table.toolbar strip>
            <atom:table.search placeholder="Search"/>
            <atom:table.filters chips="inline">
                @foreach (['a' => 'Customer', 'b' => 'Warehouse', 'c' => 'Carrier', 'd' => 'Priority', 'e' => 'Category', 'f' => 'Supplier', 'g' => 'Owner', 'h' => 'Region'] as $key => $label)
                    <atom:select variant="filter" add x-model="f.{{ $key }}" data-filter-key="filters.{{ $key }}" :label="$label" :options="[['value' => $key.'1', 'label' => $label.' One']]" />
                @endforeach
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

    {{-- the `actions` slot, the ⋯ `menu` slot and the older `end` slot together: count (none here), actions, end, trashed, ⋯ --}}
    <div class="rounded-panel border bg-surface" data-testid="toolbar-menu">
        <atom:table.toolbar strip trashed>
            <atom:table.search placeholder="Search"/>
            <x-slot:actions>
                <atom:button variant="ghost" icon="download" aria-label="Export" data-testid="act-export"><span class="max-sm:sr-only">Export</span></atom:button>
            </x-slot:actions>
            <x-slot:end>
                <atom:button variant="ghost" icon="upload" aria-label="Import" data-testid="act-import" />
            </x-slot:end>
            <x-slot:menu>
                <atom:menu.item icon="archive" data-testid="menu-archive" x-on:click="$el.closest('[data-testid=toolbar-menu]').dataset.picked = 'archive'">Archive all</atom:menu.item>
                <atom:menu.item icon="upload">Import</atom:menu.item>
            </x-slot:menu>
        </atom:table.toolbar>
    </div>

    {{-- only the ⋯ menu, icon-only: a narrow end group that shares the search's row on a phone --}}
    <div class="rounded-panel border bg-surface" data-testid="toolbar-menu-only">
        <atom:table.toolbar strip>
            <atom:table.search placeholder="Search"/>
            <x-slot:menu>
                <atom:menu.item icon="archive">Archive all</atom:menu.item>
            </x-slot:menu>
        </atom:table.toolbar>
    </div>
</div>

@livewireScripts
</atom:html>
