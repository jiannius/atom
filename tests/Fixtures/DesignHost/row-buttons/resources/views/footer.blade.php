{{-- row-buttons: the table's footer slot is not a row, the totals under the rows included. --}}
<atom:table>
    <x-slot:rows>
        <atom:table.row href="/x">
            <atom:table.cell>a</atom:table.cell>
            <atom:table.actions><atom:menu.item>Edit</atom:menu.item></atom:table.actions>
        </atom:table.row>
    </x-slot:rows>

    <x-slot:footer>
        <atom:button>Load more</atom:button>

        <atom:table.totals>
            <atom:table.row>
                <atom:table.cell><atom:button>Recalculate</atom:button></atom:table.cell>
            </atom:table.row>
        </atom:table.totals>
    </x-slot:footer>
</atom:table>
