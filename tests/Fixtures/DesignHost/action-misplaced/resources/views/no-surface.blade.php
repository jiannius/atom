{{-- action-misplaced: an action that no surface places draws nothing. A line marked `expect:` is a finding; every other line must stay clean. --}}
<atom:page>
    <atom:action wire:click="refresh">Refresh</atom:action> {{-- expect: action-misplaced --}}

    <atom:callout heading="Heads up">
        <atom:action wire:click="refresh">Refresh</atom:action> {{-- expect: action-misplaced --}}
    </atom:callout>

    <atom:form wire:submit="save">
        <atom:action wire:click="draft">Save as draft</atom:action> {{-- expect: action-misplaced --}}

        <atom:form.actions>
            <atom:action wire:click="cancel">Cancel</atom:action> {{-- expect: action-misplaced --}}
        </atom:form.actions>
    </atom:form>

    <atom:table>
        <x-slot:rows>
            @foreach ($jobs as $job)
                <atom:table.row>
                    <atom:table.cell>
                        <div class="flex gap-2">
                            <atom:action wire:click="open({{ $job->id }})">Open</atom:action> {{-- expect: action-misplaced --}}
                        </div>
                    </atom:table.cell>

                    <atom:table.cell>
                        <atom:table.actions>
                            <atom:action wire:click="open({{ $job->id }})">Open</atom:action>
                        </atom:table.actions>
                    </atom:table.cell>
                </atom:table.row>
            @endforeach

            <atom:action wire:click="more">Load more</atom:action> {{-- expect: action-misplaced --}}
        </x-slot:rows>

        <x-slot:empty>
            <atom:empty heading="No jobs yet">
                <x-slot:action>
                    <atom:action wire:click="create">Create a job</atom:action> {{-- expect: action-misplaced --}}
                </x-slot:action>
            </atom:empty>
        </x-slot:empty>

        <x-slot:footer>
            <atom:action wire:click="export">Export</atom:action> {{-- expect: action-misplaced --}}
        </x-slot:footer>
    </atom:table>
</atom:page>

<atom:modal name="job">
    <atom:action wire:click="close">Close</atom:action> {{-- expect: action-misplaced --}}
</atom:modal>

<atom:form.modal name="invite" wire:submit="invite">
    <x-slot:actions>
        <atom:action wire:click="close">Close</atom:action> {{-- expect: action-misplaced --}}
    </x-slot:actions>
</atom:form.modal>
