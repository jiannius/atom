{{-- A table with `:paginate="false"` is not paginated, so the view is no list and "Add ..." in the header is a line. --}}
<atom:page>
    <atom:page.header title="Customers">
        <x-slot:primary>
            <atom:button>Add customer</atom:button>
        </x-slot:primary>
    </atom:page.header>

    <atom:table :paginate="false" />
    <atom:table :paginate="null" />
</atom:page>
