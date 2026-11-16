{{-- A list is a paginated table (or one with filters, a search or a toolbar), so "Add ..." in the header creates a record. --}}
<atom:page>
    <atom:page.header title="Suppliers">
        <x-slot:primary>
            <atom:button variant="primary" icon="add">Add supplier</atom:button> {{-- expect: create-verb --}}
            <atom:link href="#">Add contact</atom:link>
        </x-slot:primary>
    </atom:page.header>

    <atom:table :paginate="$suppliers" frameless>
        <x-slot:rows>
            <atom:table.row><atom:table.cell>Acme</atom:table.cell></atom:table.row>
        </x-slot:rows>
    </atom:table>
</atom:page>
