{{-- Actions built to the rules: two visible actions and a primary in the header (one of them by status), a toolbar of two, a bulk bar of three with the delete last, rows that open the record and keep their actions in the ⋯, a line table that edits, and menus in order. --}}
<atom:page>
    <atom:page.header title="Jobs">
        <x-slot:actions>
            <atom:button icon="upload">Import</atom:button>

            @if ($archived)
                <atom:button>Show active</atom:button>
            @else
                <atom:button>Show archived</atom:button>
            @endif
        </x-slot:actions>

        <x-slot:menu>
            <atom:menu.item icon="copy">Duplicate</atom:menu.item>
            <atom:menu.item icon="download">Export all</atom:menu.item>
            <atom:menu.separator />
            <atom:menu.item variant="warning" confirm>Archive finished</atom:menu.item>
            <atom:menu.item variant="delete">Delete drafts</atom:menu.item>
        </x-slot:menu>

        <x-slot:primary>
            @if ($draft)
                <atom:button variant="primary">Schedule</atom:button>
            @else
                <atom:button variant="primary">Close</atom:button>
            @endif
        </x-slot:primary>
    </atom:page.header>

    <atom:panel>
        <atom:table.toolbar strip>
            <atom:table.search placeholder="Search jobs" />

            <x-slot:actions>
                <atom:button variant="ghost" icon="download">Export</atom:button>
                <atom:button variant="ghost" icon="printer">Print</atom:button>
            </x-slot:actions>

            <x-slot:menu>
                <atom:menu.item icon="settings">Columns</atom:menu.item>
            </x-slot:menu>
        </atom:table.toolbar>

        <atom:table frameless>
            <x-slot:checked>
                <atom:button>Assign</atom:button>
                <atom:button>Schedule</atom:button>
                <atom:button>Export selected</atom:button>
                <atom:button type="delete">Delete</atom:button>
            </x-slot:checked>

            <x-slot:columns>
                <atom:table.column>Job</atom:table.column>
            </x-slot:columns>

            <x-slot:rows>
                @foreach ($jobs as $job)
                    <atom:table.row :href="route('jobs.show', $job)">
                        <atom:table.cell>{{ $job->name }}</atom:table.cell>
                        <atom:table.actions>
                            <atom:menu.item icon="copy">Duplicate</atom:menu.item>
                            <atom:menu.separator />
                            <atom:menu.item variant="warning" confirm>Cancel</atom:menu.item>
                            <atom:menu.item variant="delete">Delete</atom:menu.item>
                        </atom:table.actions>
                    </atom:table.row>
                @endforeach
            </x-slot:rows>
        </atom:table>
    </atom:panel>

    <atom:panel heading="Lines">
        <x-slot:actions>
            <atom:button variant="ghost" icon="add">Add line</atom:button>
        </x-slot:actions>

        <atom:table frameless>
            <x-slot:columns>
                <atom:table.column>Quantity</atom:table.column>
            </x-slot:columns>

            <x-slot:rows>
                @foreach ($lines as $index => $line)
                    <atom:table.row wire:key="line-{{ $index }}">
                        <atom:table.cell><atom:input wire:model="lines.{{ $index }}.quantity" aria-label="Quantity" /></atom:table.cell>
                        <atom:table.cell :filler="false"><atom:button variant="ghost" size="sm" icon="x" aria-label="Remove line" wire:click="removeLine({{ $index }})" /></atom:table.cell>
                    </atom:table.row>
                @endforeach
            </x-slot:rows>
        </atom:table>
    </atom:panel>
</atom:page>
