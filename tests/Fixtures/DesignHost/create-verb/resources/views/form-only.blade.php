{{-- A view with no table: a header primary "Add ..." is a line or a child here, so it stays unflagged; "New ..." outside the header primary and an empty state is not read either. --}}
<atom:page>
    <atom:page.header title="Invoice">
        <x-slot:primary>
            <atom:button variant="primary">Add line</atom:button>
        </x-slot:primary>
        <x-slot:actions>
            <atom:button>New version</atom:button>
        </x-slot:actions>
    </atom:page.header>
</atom:page>

<atom:page.header title="Export">
    <x-slot:primary>
        <atom:button>
            <x-slot:end>New</x-slot:end>
            Export
        </atom:button>
    </x-slot:primary>
</atom:page.header>
