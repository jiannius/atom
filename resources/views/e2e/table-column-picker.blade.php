@php
// The column picker on static tables (table-column-picker.spec.js), on the consumer's Tailwind: ?tw=floor|latest (default latest).
// A: a toolbar OUTSIDE its table (the list-page pattern), `columns="picker-a"`, with a button before the picker (so a divider);
//    persist-key, a locked Name, a default-hidden Phone, and a header that holds text that looks like markup.
// B: a toolbar with `columns` and nothing else, no count: no divider. It steers the table it is `for`.
// C: a table with an outside toolbar (`columns="picker-c"`) on a persist-key it shares with D: a second table, no picker. E: a table with
// ONE hideable column and a locked one. F: only hideable columns (the last shown one cannot be hidden). G: a toolbar whose `columns` names no table. (A table's own header slot needs $wire, so the picker without `for` is proven on the Livewire fixture.)
$tw = in_array(request()->query('tw'), ['floor', 'latest'], true) ? request()->query('tw') : 'latest';
@endphp

<atom:html title="E2E: Column picker" :vite="[]" :dark="false" :styles="['/atom/e2e/consumer-css/'.$tw]">
<div class="space-y-8 p-6">
    <div class="rounded-panel border bg-surface" data-testid="a-panel">
        <atom:table.toolbar strip columns="picker-a">
            <atom:table.search placeholder="Search" />

            <x-slot:actions>
                <atom:button variant="ghost" icon="download">Export</atom:button>
            </x-slot:actions>
        </atom:table.toolbar>
    </div>

    <atom:table id="picker-a" persist-key="picker-a" :empty="false" data-testid="a">
        <x-slot:columns>
            <atom:table.column name="name">Name</atom:table.column>
            <atom:table.column name="email" hideable>Email</atom:table.column>
            <atom:table.column name="phone" hideable hidden>Phone</atom:table.column>
            <atom:table.column name="notes" hideable>{{ 'Notes <b>bold</b> & <img src=x onerror="window.__picked=1">' }}</atom:table.column>
        </x-slot:columns>
        <x-slot:rows>
            <atom:table.row>
                <atom:table.cell column="name">A one</atom:table.cell>
                <atom:table.cell column="email">a@example.com</atom:table.cell>
                <atom:table.cell column="phone">555-0001</atom:table.cell>
                <atom:table.cell column="notes">note a</atom:table.cell>
            </atom:table.row>
        </x-slot:rows>
    </atom:table>

    <div class="rounded-panel border bg-surface" data-testid="b-panel">
        <atom:table.toolbar strip :count="false" columns="picker-b" />
    </div>

    <atom:table id="picker-b" persist-key="picker-b" :empty="false" data-testid="b">
        <x-slot:columns>
            <atom:table.column name="name">Name</atom:table.column>
            <atom:table.column name="email" hideable>Email</atom:table.column>
        </x-slot:columns>
        <x-slot:rows>
            <atom:table.row>
                <atom:table.cell column="name">B one</atom:table.cell>
                <atom:table.cell column="email">b@example.com</atom:table.cell>
            </atom:table.row>
        </x-slot:rows>
    </atom:table>

    <div class="rounded-panel border bg-surface" data-testid="c-panel">
        <atom:table.toolbar strip :count="false" columns="picker-c" />
    </div>

    @foreach (['c', 'd'] as $id)
        <atom:table :id="$id === 'c' ? 'picker-c' : null" persist-key="picker-shared" :empty="false" data-testid="{{ $id }}">
            <x-slot:columns>
                <atom:table.column name="name">Name</atom:table.column>
                <atom:table.column name="email" hideable>Email</atom:table.column>
                <atom:table.column name="city" hideable>City</atom:table.column>
            </x-slot:columns>
            <x-slot:rows>
                <atom:table.row>
                    <atom:table.cell column="name">{{ strtoupper($id) }} one</atom:table.cell>
                    <atom:table.cell column="email">{{ $id }}@example.com</atom:table.cell>
                    <atom:table.cell column="city">{{ $id }} town</atom:table.cell>
                </atom:table.row>
            </x-slot:rows>
        </atom:table>
    @endforeach

    <div class="rounded-panel border bg-surface" data-testid="e-panel">
        <atom:table.toolbar strip :count="false" columns="picker-e" />
    </div>

    <atom:table id="picker-e" :empty="false" data-testid="e">
        <x-slot:columns>
            <atom:table.column name="name">Name</atom:table.column>
            <atom:table.column name="email" hideable>Email</atom:table.column>
        </x-slot:columns>
        <x-slot:rows>
            <atom:table.row>
                <atom:table.cell column="name">E one</atom:table.cell>
                <atom:table.cell column="email">e@example.com</atom:table.cell>
            </atom:table.row>
        </x-slot:rows>
    </atom:table>

    <div class="rounded-panel border bg-surface" data-testid="f-panel">
        <atom:table.toolbar strip :count="false" columns="picker-f" />
    </div>

    <atom:table id="picker-f" persist-key="picker-f" :empty="false" data-testid="f">
        <x-slot:columns>
            <atom:table.column name="email" hideable>Email</atom:table.column>
            <atom:table.column name="city" hideable>City</atom:table.column>
        </x-slot:columns>
        <x-slot:rows>
            <atom:table.row>
                <atom:table.cell column="email">f@example.com</atom:table.cell>
                <atom:table.cell column="city">f town</atom:table.cell>
            </atom:table.row>
        </x-slot:rows>
    </atom:table>

    <div class="rounded-panel border bg-surface" data-testid="g-panel">
        <atom:table.toolbar strip :count="false" columns="picker-missing" />
    </div>
</div>

@livewireScripts
</atom:html>
