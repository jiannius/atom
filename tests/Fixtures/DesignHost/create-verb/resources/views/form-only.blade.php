{{-- A view with no table: a header primary "Add ..." is a line or a child here, so it stays unflagged; "New ..." in any header slot is still flagged. --}}
<atom:page>
    <atom:page.header title="Invoice">
        <x-slot:primary>
            <atom:button variant="primary">Add line</atom:button>
        </x-slot:primary>
        <x-slot:actions>
            <atom:button>New version</atom:button> {{-- expect: create-verb --}}
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
