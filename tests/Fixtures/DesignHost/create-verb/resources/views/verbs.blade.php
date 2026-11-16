{{-- create-verb: "New" or "Add" for a record. A line marked `expect:` is a finding; every other line must stay clean. --}}
<atom:page>
    <atom:page.header title="Invoices">
        <x-slot:primary>
            <atom:button variant="primary" icon="add">New invoice</atom:button> {{-- expect: create-verb --}}
        </x-slot:primary>
    </atom:page.header>

    <atom:page.header title="Customers">
        <x-slot:primary>
            <atom:button variant="primary" icon="add">{{ t('Add customer') }}</atom:button> {{-- expect: create-verb --}}
        </x-slot:primary>

        <x-slot:actions>
            <atom:button>Add note</atom:button>
        </x-slot:actions>
    </atom:page.header>

    <atom:page.header title="Parts">
        <x-slot:primary>
            <atom:button variant="primary" icon="add">Create part</atom:button>
        </x-slot:primary>
    </atom:page.header>

    <atom:table.toolbar>
        <x-slot:menu>
            <atom:menu.item icon="add">New group</atom:menu.item>
            <atom:menu.item icon="add">Create group</atom:menu.item>
            <atom:menu.item>Newest first</atom:menu.item>
            <atom:menu.item>Renew contract</atom:menu.item>
        </x-slot:menu>
    </atom:table.toolbar>

    <atom:table>
        <x-slot:empty>
            <atom:empty heading="No parts yet">
                <x-slot:action>
                    <atom:button>Add part</atom:button>
                    <atom:button>New part</atom:button> {{-- expect: create-verb --}}
                </x-slot:action>
            </atom:empty>
        </x-slot:empty>
    </atom:table>

    <x-card>
        <x-slot:primary>
            <atom:button>Add note</atom:button>
        </x-slot:primary>
    </x-card>

    <atom:form wire:submit="save">
        <atom:button type="button" wire:click="addLine">Add line</atom:button>
        <atom:button>New</atom:button>
        <atom:button>New message</atom:button>
        <atom:button type="button">New chat</atom:button>
        <atom:button>{{ $verb }} invoice</atom:button>
        <atom:button>New {{ $kind }}</atom:button>
        <atom:button>Add</atom:button>
    </atom:form>
</atom:page>
