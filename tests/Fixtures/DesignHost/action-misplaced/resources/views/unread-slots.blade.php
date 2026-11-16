{{-- action-misplaced: a surface's slot that nothing reads, and the body of a card or panel. --}}
<atom:page>
    <atom:page.header title="Jobs">
        <x-slot:status>
            <atom:action wire:click="refresh">Refresh</atom:action> {{-- expect: action-misplaced --}}
        </x-slot:status>

        <x-slot:meta>
            <span><atom:action wire:click="refresh">Refresh</atom:action></span> {{-- expect: action-misplaced --}}
        </x-slot:meta>
    </atom:page.header>

    <atom:card heading="Customer">
        <atom:action wire:click="edit">Edit</atom:action> {{-- expect: action-misplaced --}}

        <x-slot:footer>
            <atom:action wire:click="edit">Edit</atom:action> {{-- expect: action-misplaced --}}
        </x-slot:footer>
    </atom:card>

    <atom:panel heading="Lines">
        <div>
            <atom:action wire:click="add">Add line</atom:action> {{-- expect: action-misplaced --}}
        </div>

        <x-slot:heading>
            <atom:action wire:click="add">Add line</atom:action> {{-- expect: action-misplaced --}}
        </x-slot:heading>
    </atom:panel>
</atom:page>
