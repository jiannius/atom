{{-- menu-order: orders and shapes that are fine. --}}
<atom:page>
    {{-- the record page's own menu: utility, separator, warning --}}
    <atom:page.header title="Work order WO-1042">
        <x-slot:menu>
            <atom:menu.item icon="copy">Duplicate</atom:menu.item>
            <atom:menu.separator />
            <atom:menu.item variant="warning" confirm>Cancel job</atom:menu.item>
        </x-slot:menu>
    </atom:page.header>

    <atom:card heading="Customer">
        <x-slot:menu>
            <atom:menu.item>Edit</atom:menu.item>
            <atom:menu.item>Change</atom:menu.item>
            <atom:menu.separator />
            <atom:menu.item variant="warning" confirm>Archive</atom:menu.item>
            <atom:menu.item variant="delete">Delete</atom:menu.item>
        </x-slot:menu>
    </atom:card>

    <atom:panel heading="Lines">
        <x-slot:menu>
            <atom:menu.item variant="default">Import</atom:menu.item>
            <atom:menu.separator />
            <atom:menu.item variant="danger" confirm>Remove all</atom:menu.item>
        </x-slot:menu>
    </atom:panel>

    {{-- the branches of one chain are never on screen together --}}
    <atom:page.header title="Job">
        <x-slot:menu>
            <atom:menu.item>Duplicate</atom:menu.item>
            @if ($job->isActive())
                <atom:menu.separator />
                <atom:menu.item variant="warning" confirm>Pause</atom:menu.item>
            @else
                <atom:menu.item>Resume</atom:menu.item>
            @endif
        </x-slot:menu>
    </atom:page.header>

    {{-- an item whose variant is not read: nothing is said about the one after it --}}
    <atom:page.header title="Another job">
        <x-slot:menu>
            <atom:menu.item>Duplicate</atom:menu.item>
            <atom:menu.item :variant="$tone">Depends</atom:menu.item>
            <atom:menu.item variant="delete">Delete</atom:menu.item>
        </x-slot:menu>
    </atom:page.header>

    {{-- a default item and a toned one in the two branches of one chain --}}
    <atom:page.header title="One more job">
        <x-slot:menu>
            @if ($job->isActive())
                <atom:menu.item>Pause</atom:menu.item>
            @else
                <atom:menu.item variant="warning" confirm>Cancel</atom:menu.item>
            @endif
        </x-slot:menu>
    </atom:page.header>

    {{-- items inside a component of the host's own: not read, it may order them --}}
    <atom:page.header title="Yet another job">
        <x-slot:menu>
            <x-job.menu-items>
                <atom:menu.item variant="delete">Delete</atom:menu.item>
                <atom:menu.item>Edit</atom:menu.item>
            </x-job.menu-items>
        </x-slot:menu>
    </atom:page.header>

    {{-- an account menu of plain items --}}
    <atom:dropdown>
        <atom:button>Account</atom:button>
        <atom:menu popover>
            <atom:menu.item icon="settings">Account settings</atom:menu.item>
            <atom:menu.item icon="logout">Sign out</atom:menu.item>
        </atom:menu>
    </atom:dropdown>

    {{-- a separator that is not in a menu --}}
    <atom:separator />
    <hr>

    <atom:table>
        <x-slot:rows>
            <atom:table.row>
                <atom:table.actions>
                    <atom:menu.item>View</atom:menu.item>
                    <atom:menu.separator />
                    <atom:menu.item variant="warning" confirm>Cancel</atom:menu.item>
                    <atom:menu.item variant="delete">Delete</atom:menu.item>
                </atom:table.actions>
            </atom:table.row>
        </x-slot:rows>
    </atom:table>

    <!-- <atom:menu><atom:menu.item variant="delete">X</atom:menu.item><atom:menu.item>Y</atom:menu.item></atom:menu> -->
</atom:page>
