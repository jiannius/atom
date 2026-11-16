{{-- menu-order: a menu not in the order utilities, warning, danger, or with a hand separator. A line marked `expect:` is a finding; every other line must stay clean. --}}
<atom:page>
    <atom:page.header title="Jobs">
        <x-slot:menu>
            <atom:menu.item variant="delete">Delete</atom:menu.item>
            <atom:menu.item icon="copy">Duplicate</atom:menu.item> {{-- expect: menu-order --}}
        </x-slot:menu>
    </atom:page.header>

    <atom:card heading="Customer">
        <x-slot:menu>
            <atom:menu.item variant="danger" confirm>Remove</atom:menu.item>
            <atom:menu.item variant="warning" confirm>Archive</atom:menu.item> {{-- expect: menu-order --}}
            <atom:menu.item>Edit</atom:menu.item> {{-- expect: menu-order --}}
        </x-slot:menu>
    </atom:card>

    <atom:panel heading="Lines">
        <x-slot:menu>
            <atom:menu.item>Import</atom:menu.item>
            <atom:menu.item variant="warning" confirm>Void</atom:menu.item> {{-- expect: menu-order --}}
            <atom:menu.separator />
            <atom:menu.item variant="delete">Delete</atom:menu.item>
        </x-slot:menu>

        <atom:table.toolbar>
            <x-slot:menu>
                <atom:menu.item>Columns</atom:menu.item>
                <atom:separator class="my-1" /> {{-- expect: menu-order --}}
                <atom:menu.item>Settings</atom:menu.item>
                <hr> {{-- expect: menu-order --}}
            </x-slot:menu>
        </atom:table.toolbar>

        <atom:table>
            <x-slot:checked-menu>
                <atom:menu.item variant="delete">Delete selected</atom:menu.item>
                <atom:menu.item>Export selected</atom:menu.item> {{-- expect: menu-order --}}
            </x-slot:checked-menu>

            <x-slot:rows>
                <atom:table.row>
                    <atom:table.actions>
                        <atom:menu.item variant="warning" confirm>Cancel</atom:menu.item>
                        <atom:menu.item icon="eye">View</atom:menu.item> {{-- expect: menu-order --}}
                    </atom:table.actions>
                </atom:table.row>
            </x-slot:rows>
        </atom:table>
    </atom:panel>

    <atom:card heading="Contact">
        <x-slot:menu>
            <atom:menu.item variant="warning" confirm>Archive</atom:menu.item>
            <atom:menu.item>Edit</atom:menu.item> {{-- expect: menu-order --}}
            <atom:menu.item variant="delete">Delete</atom:menu.item>
        </x-slot:menu>
    </atom:card>

    <atom:dropdown>
        <atom:button>Actions</atom:button>
        <atom:menu popover>
            <atom:menu.item variant="remove">Remove</atom:menu.item>
            <atom:menu.item>Edit</atom:menu.item> {{-- expect: menu-order --}}
        </atom:menu>
    </atom:dropdown>

    <atom:dropdown>
        <atom:button>Quick</atom:button>
        <atom:menu popover>
            <atom:menu.item>Duplicate</atom:menu.item>
            <atom:menu.item variant="warning" confirm>Void</atom:menu.item> {{-- expect: menu-order --}}
        </atom:menu>
    </atom:dropdown>
</atom:page>
