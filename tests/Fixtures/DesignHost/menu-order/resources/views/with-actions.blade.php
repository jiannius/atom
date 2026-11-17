{{-- menu-order with `<atom:action>`s: atom prints the rows it makes (ActionPlacement) after the host's own items, with its separator, so a host warning or danger item before an action's utility row is not last. The finding stays on the host's item. A line marked `expect:` is a finding; every other line must stay clean. --}}
<atom:page>
    <atom:page.header title="A host warning before a utility action">
        <x-slot:menu>
            <atom:menu.item variant="warning" confirm>Void</atom:menu.item> {{-- expect: menu-order --}}
            <atom:action priority="low" wire:click="duplicate">Duplicate</atom:action>
        </x-slot:menu>
    </atom:page.header>

    <atom:page.header title="A host danger before a warning action">
        <x-slot:menu>
            <atom:menu.item variant="delete">Delete</atom:menu.item> {{-- expect: menu-order --}}
            <atom:action tone="warning" wire:click="void">Void</atom:action>
        </x-slot:menu>
    </atom:page.header>

    <atom:page.header title="An action that overflows from the actions slot lands after the host warning">
        <x-slot:actions>
            <atom:action wire:click="a">One</atom:action>
            <atom:action wire:click="b">Two</atom:action>
            <atom:action wire:click="c">Three</atom:action>
        </x-slot:actions>

        <x-slot:menu>
            <atom:menu.item variant="warning" confirm>Void</atom:menu.item> {{-- expect: menu-order --}}
        </x-slot:menu>
    </atom:page.header>

    <atom:page.header title="The same tone, or a heavier one, after the host item is in order">
        <x-slot:menu>
            <atom:menu.item>Print</atom:menu.item>
            <atom:action tone="warning" wire:click="void">Void</atom:action>
            <atom:action tone="delete" wire:click="destroy">Delete</atom:action>
        </x-slot:menu>
    </atom:page.header>

    <atom:page.header title="A host warning, then an action's warning and delete">
        <x-slot:menu>
            <atom:menu.item variant="warning" confirm>Archive</atom:menu.item>
            <atom:action tone="warning" wire:click="void">Void</atom:action>
            <atom:action tone="delete" wire:click="destroy">Delete</atom:action>
        </x-slot:menu>
    </atom:page.header>

    <atom:page.header title="A host danger, then an action's delete">
        <x-slot:menu>
            <atom:menu.item variant="danger" confirm>Remove</atom:menu.item>
            <atom:action tone="delete" wire:click="destroy">Delete</atom:action>
        </x-slot:menu>
    </atom:page.header>

    <atom:page.header title="A bound tone is not read">
        <x-slot:menu>
            <atom:menu.item variant="warning" confirm>Void</atom:menu.item>
            <atom:action :tone="$tone" wire:click="duplicate">Duplicate</atom:action>
        </x-slot:menu>
    </atom:page.header>

    <atom:page.header title="Actions alone are never reported">
        <x-slot:menu>
            <atom:action tone="delete" wire:click="destroy">Delete</atom:action>
            <atom:action wire:click="duplicate">Duplicate</atom:action>
        </x-slot:menu>
    </atom:page.header>

    <atom:card heading="Customer">
        <x-slot:menu>
            <atom:menu.item variant="danger" confirm>Remove</atom:menu.item> {{-- expect: menu-order --}}
            <atom:action wire:click="copy">Copy</atom:action>
        </x-slot:menu>
    </atom:card>

    <atom:panel heading="Lines">
        <x-slot:menu>
            <atom:menu.item variant="warning" confirm>Void all</atom:menu.item> {{-- expect: menu-order --}}
            <atom:action wire:click="import">Import</atom:action>
        </x-slot:menu>

        <atom:table.toolbar>
            <x-slot:menu>
                <atom:menu.item variant="warning" confirm>Reset</atom:menu.item> {{-- expect: menu-order --}}
                <atom:action wire:click="columns">Columns</atom:action>
            </x-slot:menu>
        </atom:table.toolbar>

        <atom:table>
            <x-slot:checked-menu>
                <atom:menu.item variant="warning" confirm>Void</atom:menu.item> {{-- expect: menu-order --}}
                <atom:action priority="low" wire:click="export">Export selected</atom:action>
            </x-slot:checked-menu>

            <x-slot:rows>
                <atom:table.row>
                    <atom:table.actions>
                        <atom:menu.item variant="danger" confirm>Remove</atom:menu.item> {{-- expect: menu-order --}}
                        <atom:action wire:click="open">Open</atom:action>
                    </atom:table.actions>
                </atom:table.row>
            </x-slot:rows>
        </atom:table>
    </atom:panel>
</atom:page>
