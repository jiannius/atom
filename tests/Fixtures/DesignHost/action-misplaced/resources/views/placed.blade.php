{{-- action-misplaced: the look-alikes. Every action here is placed by its surface, or may be (a partial, a host component): none is flagged. --}}
<atom:action wire:click="partial">A partial's action</atom:action>

<atom:page>
    <atom:page.header title="Jobs">
        <x-slot:primary>
            <atom:action wire:click="create">Create job</atom:action>
        </x-slot:primary>

        <x-slot:actions>
            <atom:action wire:click="import">Import</atom:action>
            <atom:action x-show="open" wire:click="export">Export</atom:action>

            @if ($canPrint)
                <atom:action wire:click="print">Print</atom:action>
            @endif

            @foreach ($tools as $tool)
                <atom:action wire:click="use({{ $tool->id }})">{{ $tool->name }}</atom:action>
            @endforeach

            <div class="flex"><atom:action wire:click="plain">In a div</atom:action></div>
        </x-slot:actions>

        <x-slot:menu>
            <atom:action tone="delete" wire:click="destroy">Delete</atom:action>
        </x-slot:menu>

        <atom:action wire:click="in-default">In the default slot</atom:action>
    </atom:page.header>

    <atom:table.toolbar>
        <atom:action wire:click="a">Import</atom:action>

        <x-slot:end>
            <atom:action wire:click="b">Export</atom:action>
        </x-slot:end>

        <x-slot:menu>
            <atom:action wire:click="c">Columns</atom:action>
        </x-slot:menu>
    </atom:table.toolbar>

    <atom:table>
        <x-slot:checked>
            <atom:action wire:click="assign">Assign</atom:action>
        </x-slot:checked>

        <x-slot:checked-menu>
            <atom:action tone="delete" wire:click="destroyMany">Delete selected</atom:action>
        </x-slot:checked-menu>

        <x-slot:rows>
            @foreach ($jobs as $job)
                <atom:table.row>
                    <atom:table.actions>
                        <atom:action wire:click="open({{ $job->id }})">Open</atom:action>
                        <atom:action tone="delete" wire:click="destroy({{ $job->id }})">Delete</atom:action>
                    </atom:table.actions>
                </atom:table.row>
            @endforeach
        </x-slot:rows>
    </atom:table>

    <atom:card heading="Customer">
        <x-slot:actions>
            <atom:action wire:click="edit">Edit</atom:action>
        </x-slot:actions>

        <x-slot:menu>
            <atom:action wire:click="copy">Copy</atom:action>
        </x-slot:menu>
    </atom:card>

    <atom:panel heading="Lines">
        <x-slot:actions>
            <atom:action wire:click="add">Add line</atom:action>
        </x-slot:actions>
    </atom:panel>

    <x-card-with-header>
        <atom:action wire:click="host">Inside a host component</atom:action>
    </x-card-with-header>

    <atom:grid>
        <atom:action wire:click="unknown">Inside a tag the check does not know</atom:action>
    </atom:grid>

    <livewire:job-actions>
        <atom:action wire:click="livewire">Inside a Livewire child</atom:action>
    </livewire:job-actions>

    <atom:modal name="job">
        <atom:page.header title="Job" level="2" size="sm">
            <x-slot:actions>
                <atom:action wire:click="preview">Preview</atom:action>
            </x-slot:actions>
        </atom:page.header>
    </atom:modal>
</atom:page>
