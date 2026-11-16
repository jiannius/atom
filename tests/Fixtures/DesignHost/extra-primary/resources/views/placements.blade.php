{{-- extra-primary: a primary where none belongs. The header has none, so a primary here is flagged for where it sits and for nothing else; the one at the end is the screen's, and the flagged ones do not count against it. --}}
<atom:page>
    <atom:page.header title="Jobs" />

    <atom:panel>
        <atom:table.toolbar>
            <x-slot:actions>
                <atom:button variant="primary">Export</atom:button> {{-- expect: extra-primary --}}
                <atom:button variant="ghost">Print</atom:button>
            </x-slot:actions>
        </atom:table.toolbar>

        <atom:table>
            <x-slot:checked>
                {{-- expect: extra-primary --}}
                <atom:button
                    variant="primary"
                >
                    Approve
                </atom:button>
            </x-slot:checked>

            <x-slot:checked-menu>
                <atom:button type="submit">Send</atom:button> {{-- expect: extra-primary --}}
            </x-slot:checked-menu>

            <x-slot:rows>
                @foreach ($jobs as $job)
                    <atom:table.row :href="route('jobs.show', $job)">
                        <atom:table.cell>
                            <atom:button variant="primary" size="sm">Open</atom:button> {{-- expect: extra-primary --}}
                        </atom:table.cell>
                    </atom:table.row>
                @endforeach

                <atom:table.row>
                    <atom:table.actions>
                        <atom:button variant="primary">Open</atom:button> {{-- expect: extra-primary --}}
                    </atom:table.actions>
                </atom:table.row>

                {{-- the look-alikes: a row that edits its line has its own controls, however they are written --}}
                <atom:table.row>
                    <atom:table.cell><atom:input aria-label="Quantity" /></atom:table.cell>
                    <atom:table.cell><atom:button variant="primary" size="sm">Apply</atom:button></atom:table.cell>
                </atom:table.row>

                <atom:table.row>
                    <atom:table.cell><input type="number" name="quantity"></atom:table.cell>
                    <atom:table.cell><atom:button variant="primary" size="sm">Apply</atom:button></atom:table.cell>
                </atom:table.row>

                <atom:table.row>
                    <atom:table.cell><div wire:model.live="quantity"></div></atom:table.cell>
                    <atom:table.cell><atom:button variant="primary" size="sm">Apply</atom:button></atom:table.cell>
                </atom:table.row>

                <atom:table.row>
                    <atom:table.cell><div x-model="quantity"></div></atom:table.cell>
                    <atom:table.cell><atom:button variant="primary" size="sm">Apply</atom:button></atom:table.cell>
                </atom:table.row>
            </x-slot:rows>

            <x-slot:empty>
                <atom:empty heading="No jobs yet">
                    <x-slot:action>
                        <atom:button variant="primary">Create the first job</atom:button> {{-- expect: extra-primary --}}
                    </x-slot:action>
                </atom:empty>
            </x-slot:empty>
        </atom:table>
    </atom:panel>

    {{-- a slot that is called `checked` on something that is not a table is nothing special, and this is the screen's one primary --}}
    <atom:card heading="Next step">
        <x-slot:checked>
            <atom:button variant="primary">Start the job</atom:button>
        </x-slot:checked>
    </atom:card>
</atom:page>
