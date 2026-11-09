@php
// Every layer that can sit inside a modal, for the Escape specs (#177): one press closes the top layer only, the modal last.
// ?escapable=0 makes the modal `layers` non-escapable.
$escapable = request()->query('escapable') !== '0';
$options = [['value' => 'a', 'label' => 'Draft'], ['value' => 'b', 'label' => 'Sent']];
@endphp

<atom:html title="E2E: Modal Escape Layers" :vite="[]" :dark="false" :styles="['/atom/e2e/consumer-css/latest']" class="min-h-screen bg-white">
<div class="p-4" x-data="{ model: { status: null, range: null, tag: null } }">
    <button type="button" x-on:click="atom.modal('layers').show()" data-open-modal>Open modal</button>
    <button type="button" x-on:click="atom.modal('slide').slide()" data-open-slide>Open slide-over</button>

    <atom:modal name="layers" :escapable="$escapable" data-modal="layers">
        <p>Layers</p>

        <div data-typed><atom:date-picker x-model="model.date" /></div>
        <div data-range><atom:date-picker variant="range" x-model="model.range" /></div>

        <atom:table.toolbar>
            <atom:table.filters chips="inline">
                <atom:select variant="filter" add x-model="model.status" data-filter-key="status" label="Status" :options="$options" />
                <atom:select variant="filter" add searchable x-model="model.tag" data-filter-key="tag" label="Tag" :options="$options" />
            </atom:table.filters>
        </atom:table.toolbar>

        {{-- the table's column picker is a dropdown too --}}
        <atom:table.toolbar strip columns="layers-table" data-columns-toolbar>
            <atom:table.search placeholder="Search" />
        </atom:table.toolbar>

        <atom:table id="layers-table" persist-key="layers-table" :empty="false">
            <x-slot:columns>
                <atom:table.column name="name">Name</atom:table.column>
                <atom:table.column name="email" hideable>Email</atom:table.column>
            </x-slot:columns>
            <x-slot:rows>
                <atom:table.row>
                    <atom:table.cell column="name">A one</atom:table.cell>
                    <atom:table.cell column="email">a@example.com</atom:table.cell>
                </atom:table.row>
            </x-slot:rows>
        </atom:table>

        <div data-searchable><atom:select variant="listbox" searchable :options="$options" /></div>

        {{-- a date picker inside a dropdown: the picker's popover is a descendant of the dropdown's --}}
        <atom:dropdown locked>
            <button type="button" data-open-nested-date>Open dropdown with a date</button>

            <atom:menu popover>
                <div data-nested-date><atom:date-picker x-model="model.nested" /></div>
            </atom:menu>
        </atom:dropdown>

        {{-- a modal dialog inside a popover, inside a modal --}}
        <atom:dropdown locked>
            <button type="button" data-open-host>Open host</button>

            <atom:menu popover data-host-menu>
                <button type="button" x-data x-on:click="atom.modal('inside').show()" data-open-inside>Open the dialog in it</button>

                <atom:modal name="inside" data-modal="inside">
                    <p>Inside</p>

                    <atom:dropdown>
                        <button type="button" data-open-inside-dropdown>Open dropdown</button>

                        <atom:menu popover>
                            <atom:menu.item href="#inside">Inside item</atom:menu.item>
                        </atom:menu>
                    </atom:dropdown>
                </atom:modal>
            </atom:menu>
        </atom:dropdown>

        <div data-editor><atom:tiptap :mention="['Alice', 'Bob', 'Carol']" label="Note" /></div>
    </atom:modal>

    <atom:modal name="slide" data-modal="slide">
        <p>Slide-over</p>

        <atom:dropdown>
            <button type="button" data-open-slide-dropdown>Open dropdown</button>

            <atom:menu popover>
                <atom:menu.item href="#slide">Slide item</atom:menu.item>
            </atom:menu>
        </atom:dropdown>
    </atom:modal>
</div>

@livewireScripts
</atom:html>
