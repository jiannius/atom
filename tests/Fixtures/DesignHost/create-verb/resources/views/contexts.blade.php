{{-- "New ..." makes a record when the button wears the add icon or the primary variant, or sits where records are created. A line marked `expect:` is a finding; every other line must stay clean. --}}
<atom:layouts.header>
    <x-slot:actions>
        <atom:button size="sm">New purchase order</atom:button> {{-- expect: create-verb --}}
    </x-slot:actions>

    <x-slot:brand>
        <atom:button>New brand</atom:button>
    </x-slot:brand>

    <atom:page>
        <atom:page.header title="Invoices">
            <x-slot:actions>
                <atom:button>New invoice</atom:button> {{-- expect: create-verb --}}
            </x-slot:actions>
        </atom:page.header>

        <atom:table.toolbar>
            <x-slot:actions>
                <atom:button>New customer</atom:button> {{-- expect: create-verb --}}
                <atom:link href="#">New supplier</atom:link> {{-- expect: create-verb --}}
            </x-slot:actions>
        </atom:table.toolbar>

        <atom:panel heading="Lines">
            <x-slot:toolbar>
                <atom:button>New line item</atom:button> {{-- expect: create-verb --}}
            </x-slot:toolbar>
            <x-slot:actions>
                <atom:button>New group</atom:button> {{-- expect: create-verb --}}
            </x-slot:actions>
        </atom:panel>

        <atom:button icon="add">New client</atom:button> {{-- expect: create-verb --}}
        <atom:button icon="plus">New quote</atom:button> {{-- expect: create-verb --}}
        <atom:button variant="primary" :href="route('x')">New order</atom:button> {{-- expect: create-verb --}}
        <atom:link href="#" icon="add">New region</atom:link> {{-- expect: create-verb --}}
        <atom:button size="sm" variant="primary" icon="add">New purchase order</atom:button> {{-- expect: create-verb --}}

        {{-- the app's own verbs, and places that are not for records --}}
        <atom:button>New invoice</atom:button>
        <atom:button label="New invoice" />
        <atom:button x-on:click="chat">New chat</atom:button>
        <atom:button icon="send">New message</atom:button>
        <atom:button variant="ghost">New conversation</atom:button>
        <atom:button :icon="$icon">New thing</atom:button>
        <atom:link href="#">New invoice</atom:link>
        <atom:menu.item icon="add">New invoice</atom:menu.item>
        <atom:menu.item>New conversation</atom:menu.item>
        <atom:button icon="add">New</atom:button>
        <atom:button icon="add">Newest first</atom:button>
        <atom:button icon="add">{{ t('New') }} {{ $type }}</atom:button>
        <atom:card heading="Notes">
            <x-slot:actions>
                <atom:button>New note</atom:button>
            </x-slot:actions>
        </atom:card>
    </atom:page>
</atom:layouts.header>

<x-layouts.plain>
    <x-slot:actions>
        <atom:button>New thing</atom:button>
    </x-slot:actions>
</x-layouts.plain>
