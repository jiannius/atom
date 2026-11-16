{{-- action-cap: none of these is over its cap. --}}
<x-dynamic-component component="atom::layouts.sidebar" title="Record">
    <x-slot:actions>
        <atom:button size="sm">Previous</atom:button>
        <atom:button size="sm">Next</atom:button>
        <atom:button size="sm">Print</atom:button>
    </x-slot:actions>

    <atom:page>
        <atom:page.header title="Job">
            <x-slot:actions>
                <atom:button>Import</atom:button>

                @if ($job->isDraft())
                    <atom:button>Publish</atom:button>
                @else
                    <atom:button>Unpublish</atom:button>
                @endif
            </x-slot:actions>
        </atom:page.header>

        <atom:page.header title="Jobs, with a group and a dropdown">
            <x-slot:actions>
                <atom:button.group>
                    <atom:button>Day</atom:button>
                    <atom:button>Week</atom:button>
                    <atom:button>Month</atom:button>
                </atom:button.group>

                <atom:dropdown>
                    <atom:button>Export</atom:button>
                    <atom:menu popover>
                        <atom:menu.item>CSV</atom:menu.item>
                        <atom:menu.item>PDF</atom:menu.item>
                    </atom:menu>
                </atom:dropdown>
            </x-slot:actions>
        </atom:page.header>

        <atom:page.header title="Jobs, in a loop">
            <x-slot:actions>
                <atom:button>Import</atom:button>

                @foreach ($shortcuts as $shortcut)
                    <atom:button :href="$shortcut->url">{{ $shortcut->name }}</atom:button>
                @endforeach
            </x-slot:actions>
        </atom:page.header>

        <atom:page.header title="Jobs, with a modal of its own" >
            <x-slot:actions>
                <atom:button x-on:click="atom.modal('import').show()">Import</atom:button>

                <atom:modal name="import">
                    <atom:button>Choose a file</atom:button>
                    <atom:button>Paste</atom:button>
                    <atom:button>Cancel</atom:button>
                </atom:modal>
            </x-slot:actions>
        </atom:page.header>

        <atom:page.header title="A size that is not read" :size="$size">
            <x-slot:actions>
                <atom:button>One</atom:button>
                <atom:button>Two</atom:button>
                <atom:button>Three</atom:button>
            </x-slot:actions>
        </atom:page.header>

        <atom:page.header title="New job" level="2" size="sm">
            <x-slot:actions>
                <atom:button size="sm">Preview</atom:button>
            </x-slot:actions>
        </atom:page.header>

        <atom:table.toolbar>
            <atom:button>Not in a slot</atom:button>
            <atom:button>Nor this</atom:button>
            <atom:button>Nor this one</atom:button>

            <x-slot:actions>
                <atom:button>Import</atom:button>
                <atom:button>Export</atom:button>
            </x-slot:actions>
        </atom:table.toolbar>

        <atom:table>
            <x-slot:checked>
                <atom:button>Assign</atom:button>
                <atom:button>Schedule</atom:button>
                <atom:button>Export selected</atom:button>
            </x-slot:checked>
        </atom:table>

        <atom:table>
            <x-slot:checked>
                <atom:button>Assign</atom:button>
                <atom:button>Schedule</atom:button>
                <atom:button type="delete">Delete</atom:button>
            </x-slot:checked>
        </atom:table>

        <atom:table>
            <x-slot:checked>
                <atom:button>Assign</atom:button>
                <atom:button>Schedule</atom:button>
                <atom:button>Export selected</atom:button>
                <atom:button type="delete">Delete</atom:button>
            </x-slot:checked>
        </atom:table>

        <atom:table>
            <x-slot:checked>
                <atom:button>Assign</atom:button>
                <atom:button>Schedule</atom:button>
                <atom:button>Export selected</atom:button>
                <atom:button variant="danger" inverted>Remove</atom:button>
            </x-slot:checked>
        </atom:table>

        <atom:card heading="Customer">
            <x-slot:actions>
                <atom:link href="#">View all</atom:link>
            </x-slot:actions>
        </atom:card>

        <atom:empty heading="Nothing here">
            <x-slot:action>
                <atom:button>Create</atom:button>
            </x-slot:action>
        </atom:empty>

        {{-- <atom:card heading="Commented out"><x-slot:actions><atom:button>A</atom:button><atom:button>B</atom:button></x-slot:actions></atom:card> --}}
    </atom:page>
</x-dynamic-component>
