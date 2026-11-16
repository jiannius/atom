{{-- A record page: its lines table has no toolbar, filters, search or pagination, so it is no list and "Add line" in the header is a line. --}}
<atom:page>
    <atom:page.header title="Invoice 1042">
        <x-slot:primary>
            <atom:button variant="primary" icon="add">Add line</atom:button>
        </x-slot:primary>
    </atom:page.header>

    <atom:panel heading="Lines">
        <atom:table frameless>
            <x-slot:rows>
                <atom:table.row><atom:table.cell>Labour</atom:table.cell></atom:table.row>
            </x-slot:rows>
        </atom:table>
    </atom:panel>
</atom:page>
