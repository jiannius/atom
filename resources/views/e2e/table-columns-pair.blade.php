@php
// Several plain tables on one page, to see that column hiding stays inside each (table-column-visibility.spec.js): A and B have a `phone`
// column with different defaults and different keys; C and D share a key; E has a table nested in a cell, with a column of the same name.
$tw = in_array(request()->query('tw'), ['floor', 'latest'], true) ? request()->query('tw') : 'latest';
@endphp

<atom:html title="E2E: Table columns, several tables" :vite="[]" :dark="false" :styles="['/atom/e2e/consumer-css/'.$tw]">
<div class="space-y-8 p-6">
    <atom:table :empty="false" persist-key="pair-a" data-testid="a">
        <x-slot:columns>
            <atom:table.column name="name">Name</atom:table.column>
            <atom:table.column name="phone" hideable hidden>Phone</atom:table.column>
        </x-slot:columns>
        <x-slot:rows>
            <atom:table.row>
                <atom:table.cell column="name">A one</atom:table.cell>
                <atom:table.cell column="phone">555-0001</atom:table.cell>
            </atom:table.row>
        </x-slot:rows>
    </atom:table>

    <atom:table :empty="false" persist-key="pair-b" data-testid="b">
        <x-slot:columns>
            <atom:table.column name="name">Name</atom:table.column>
            <atom:table.column name="phone" hideable>Phone</atom:table.column>
        </x-slot:columns>
        <x-slot:rows>
            <atom:table.row>
                <atom:table.cell column="name">B one</atom:table.cell>
                <atom:table.cell column="phone">555-0002</atom:table.cell>
            </atom:table.row>
        </x-slot:rows>
    </atom:table>

    @foreach (['c', 'd'] as $id)
        <atom:table :empty="false" persist-key="pair-shared" data-testid="{{ $id }}">
            <x-slot:columns>
                <atom:table.column name="name">Name</atom:table.column>
                <atom:table.column name="email" hideable>Email</atom:table.column>
            </x-slot:columns>
            <x-slot:rows>
                <atom:table.row>
                    <atom:table.cell column="name">{{ strtoupper($id) }} one</atom:table.cell>
                    <atom:table.cell column="email">{{ $id }}@example.com</atom:table.cell>
                </atom:table.row>
            </x-slot:rows>
        </atom:table>
    @endforeach

    <atom:table :empty="false" persist-key="pair-outer" data-testid="outer">
        <x-slot:columns>
            <atom:table.column name="name">Name</atom:table.column>
            <atom:table.column name="status" hideable>Status</atom:table.column>
        </x-slot:columns>
        <x-slot:rows>
            <atom:table.row>
                <atom:table.cell column="name">
                    Outer one
                    <atom:table :empty="false" persist-key="pair-inner" data-testid="inner">
                        <x-slot:columns>
                            <atom:table.column name="item">Item</atom:table.column>
                            <atom:table.column name="status" hideable>Status</atom:table.column>
                        </x-slot:columns>
                        <x-slot:rows>
                            <atom:table.row>
                                <atom:table.cell column="item">Inner one</atom:table.cell>
                                <atom:table.cell column="status">inner status</atom:table.cell>
                            </atom:table.row>
                        </x-slot:rows>
                    </atom:table>
                </atom:table.cell>
                <atom:table.cell column="status">outer status</atom:table.cell>
            </atom:table.row>
        </x-slot:rows>
    </atom:table>
</div>

@livewireScripts
</atom:html>
