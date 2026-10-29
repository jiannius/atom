{{-- The column picker needs no Livewire (the choices live in the browser), so this demo is live. The toolbar sits outside its table
     here, as in the list-page pattern, so `columns` names the table's id and `strip` draws the toolbar's own strip. In a Livewire
     page the toolbar goes in the table's header slot (no `strip`, and `columns` alone: <atom:table.toolbar columns>).
     Name a column and say it is `hideable`; its cells follow with `column`. `persist-key` keeps the user's choices in localStorage. --}}
<div class="overflow-hidden rounded-panel border bg-surface">
    <atom:table.toolbar strip :count="false" columns="contacts-demo">
        <atom:table.search placeholder="Search contacts"/>

        <x-slot:actions>
            <atom:button variant="ghost" icon="download">Export</atom:button>
        </x-slot:actions>
    </atom:table.toolbar>

    <atom:table id="contacts-demo" persist-key="contacts-demo" frameless :empty="false">
        <x-slot:columns>
            <atom:table.column name="name">Name</atom:table.column>
            <atom:table.column name="email" hideable>Email</atom:table.column>
            <atom:table.column name="phone" hideable hidden>Phone</atom:table.column>
            <atom:table.column name="city" hideable>City</atom:table.column>
        </x-slot:columns>

        <x-slot:rows>
            @foreach ([['Aisha Rahman', 'aisha@example.com', '555-0101', 'Kuala Lumpur'], ['Ben Tan', 'ben@example.com', '555-0102', 'Penang'], ['Chloe Lim', 'chloe@example.com', '555-0103', 'Johor Bahru']] as [$name, $email, $phone, $city])
                <atom:table.row>
                    <atom:table.cell column="name" class="font-medium">{{ $name }}</atom:table.cell>
                    <atom:table.cell column="email">{{ $email }}</atom:table.cell>
                    <atom:table.cell column="phone">{{ $phone }}</atom:table.cell>
                    <atom:table.cell column="city">{{ $city }}</atom:table.cell>
                </atom:table.row>
            @endforeach
        </x-slot:rows>
    </atom:table>
</div>
