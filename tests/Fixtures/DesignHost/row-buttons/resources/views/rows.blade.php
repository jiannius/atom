{{-- row-buttons: a button in a row. A line marked `expect:` is a finding; every other line must stay clean. --}}
<atom:page>
    <atom:page.header title="Jobs" />

    <atom:panel>
        <atom:table>
            <x-slot:columns>
                <atom:table.column>Name</atom:table.column>
                <atom:table.column>Status</atom:table.column>
            </x-slot:columns>

            <x-slot:rows>
                @foreach ($jobs as $job)
                    <atom:table.row :href="route('jobs.show', $job)">
                        <atom:table.cell><atom:button size="sm">View</atom:button></atom:table.cell> {{-- expect: row-buttons --}}
                        <atom:table.cell>
                            <atom:button variant="ghost" icon="edit" size="sm" /> {{-- expect: row-buttons --}}
                        </atom:table.cell>
                        <atom:table.cell>
                            <div class="flex gap-1">
                                <button type="button" wire:click="open({{ $job->id }})">Open</button> {{-- expect: row-buttons --}}
                                <atom:confirm.trigger>
                                    <atom:button variant="ghost">Archive</atom:button> {{-- expect: row-buttons --}}
                                </atom:confirm.trigger>
                            </div>
                        </atom:table.cell>
                        <atom:table.actions>
                            <atom:menu.item icon="eye">View</atom:menu.item>
                            <atom:menu.item variant="delete">Delete</atom:menu.item>
                        </atom:table.actions>
                    </atom:table.row>
                @endforeach

                <atom:table.row>
                    <atom:button size="sm">Open</atom:button> {{-- expect: row-buttons --}}
                </atom:table.row>

                {{-- the look-alikes --}}
                <atom:table.row>
                    <atom:table.cell>
                        <atom:link href="/jobs/1">Open</atom:link>
                        <atom:table.checkbox />
                    </atom:table.cell>
                    <atom:table.cell>
                        <button type="button" x-on:click="open = ! open" aria-expanded="false"><atom:icon.chevron-down /></button>
                    </atom:table.cell>
                    <atom:table.cell>
                        <atom:dropdown>
                            <atom:button size="sm">Change status</atom:button>
                            <atom:menu popover><atom:menu.item>Done</atom:menu.item></atom:menu>
                        </atom:dropdown>
                    </atom:table.cell>
                    <atom:table.cell>
                        <atom:modal name="job-notes">
                            <atom:button>Close notes</atom:button>
                        </atom:modal>
                    </atom:table.cell>
                    <atom:table.cell><button>{{ $label }}</button></atom:table.cell>
                    <atom:table.actions>
                        <atom:button variant="ghost">In the actions cell</atom:button>
                    </atom:table.actions>
                </atom:table.row>
            </x-slot:rows>

            <x-slot:footer>
                <atom:button>Add line</atom:button>
            </x-slot:footer>
        </atom:table>

        {{-- a table with a row that edits something is a form of lines: its buttons are controls (one table for each way a control is written) --}}
        <atom:table>
            <x-slot:rows>
                <atom:table.row>
                    <atom:table.cell><atom:input wire:model="lines.0.quantity" /></atom:table.cell>
                    <atom:table.cell><atom:button variant="ghost" icon="x" size="sm" wire:click="removeLine(0)" /></atom:table.cell>
                </atom:table.row>
            </x-slot:rows>
        </atom:table>

        <atom:table>
            <x-slot:rows>
                <atom:table.row>
                    <atom:table.cell><input type="number" name="quantity"></atom:table.cell>
                    <atom:table.cell><atom:button variant="ghost" size="sm" wire:click="removeLine(1)">Remove</atom:button></atom:table.cell>
                </atom:table.row>
            </x-slot:rows>
        </atom:table>

        <atom:table>
            <x-slot:rows>
                <atom:table.row>
                    <atom:table.cell><atom:select name="status" aria-label="Status" /></atom:table.cell>
                    <atom:table.cell><atom:button size="sm">Move</atom:button></atom:table.cell>
                </atom:table.row>
            </x-slot:rows>
        </atom:table>

        <atom:table>
            <x-slot:rows>
                <atom:table.row>
                    <atom:table.cell><div x-model="note">Note</div></atom:table.cell>
                    <atom:table.cell><atom:button size="sm">Save note</atom:button></atom:table.cell>
                </atom:table.row>
            </x-slot:rows>
        </atom:table>

        <atom:table>
            <x-slot:rows>
                <atom:table.row>
                    <atom:table.cell><span wire:model.live="note">Note</span></atom:table.cell>
                    <atom:table.cell><atom:button size="sm">Save note</atom:button></atom:table.cell>
                </atom:table.row>
            </x-slot:rows>
        </atom:table>
    </atom:panel>

    <atom:button>Outside any table</atom:button>

    <!-- <atom:table.row><atom:table.cell><atom:button>Commented</atom:button></atom:table.cell></atom:table.row> -->
</atom:page>
