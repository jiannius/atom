{{-- extra-primary: a `priority="primary"` action on any surface but the header is drawn as a plain action. --}}
<atom:page>
    <atom:page.header title="No primary here" />

    <atom:table.toolbar>
        <x-slot:actions>
            <atom:action priority="primary" wire:click="export">Export</atom:action> {{-- expect: extra-primary --}}
        </x-slot:actions>
    </atom:table.toolbar>

    <atom:table>
        <x-slot:checked>
            <atom:action priority="primary" wire:click="approve">Approve</atom:action> {{-- expect: extra-primary --}}
        </x-slot:checked>

        <x-slot:rows>
            <atom:table.row>
                <atom:table.actions>
                    <atom:action priority="primary" wire:click="open">Open</atom:action> {{-- expect: extra-primary --}}
                </atom:table.actions>
            </atom:table.row>
        </x-slot:rows>
    </atom:table>

    <atom:card heading="Customer">
        <x-slot:actions>
            <atom:action priority="primary" wire:click="edit">Edit</atom:action> {{-- expect: extra-primary --}}
        </x-slot:actions>
    </atom:card>

    <atom:panel heading="Lines">
        <x-slot:menu>
            <atom:action priority="primary" wire:click="add">Add line</atom:action> {{-- expect: extra-primary --}}
        </x-slot:menu>
    </atom:panel>
</atom:page>
