<atom:table :empty="false">
    <x-slot:columns>
        <atom:table.column>Name</atom:table.column>
        <atom:table.column></atom:table.column>
    </x-slot:columns>
    <x-slot:rows>
        <atom:table.row>
            <atom:table.cell>Jane Cooper</atom:table.cell>
            <atom:table.actions>
                <atom:menu.item>Edit</atom:menu.item>
                <atom:menu.item class="text-red-600">Delete</atom:menu.item>
            </atom:table.actions>
        </atom:table.row>
    </x-slot:rows>
</atom:table>
