{{-- action-cap: more visible actions than a surface holds; the finding is the first one over the cap. A line marked `expect:` is a finding; every other line must stay clean. --}}
<atom:page>
    <atom:page.header title="Jobs">
        <x-slot:actions>
            <atom:button>Import</atom:button>
            <atom:button>Export</atom:button>
            <atom:button>Print</atom:button> {{-- expect: action-cap --}}
            <atom:button>Archive</atom:button>
        </x-slot:actions>

        <x-slot:primary>
            <atom:button variant="primary">Create job</atom:button>
        </x-slot:primary>
    </atom:page.header>

    <atom:page.header title="Jobs, with a group and a dropdown">
        <x-slot:actions>
            <atom:button>Import</atom:button>

            <atom:button.group>
                <atom:button>Day</atom:button>
                <atom:button>Week</atom:button>
            </atom:button.group>

            <atom:dropdown> {{-- expect: action-cap --}}
                <atom:button>Export</atom:button>
                <atom:menu popover><atom:menu.item>CSV</atom:menu.item></atom:menu>
            </atom:dropdown>
        </x-slot:actions>
    </atom:page.header>

    <atom:panel heading="Jobs">
        <x-slot:actions>
            <atom:link href="/jobs/all">View all</atom:link>
            <atom:button variant="ghost">Refresh</atom:button>
            <atom:button variant="ghost">Filter</atom:button> {{-- expect: action-cap --}}
        </x-slot:actions>

        <atom:table.toolbar>
            <x-slot:actions>
                <atom:button>Import</atom:button>
                <atom:button>Export</atom:button>
            </x-slot:actions>

            <x-slot:end>
                <atom:button>Print</atom:button> {{-- expect: action-cap --}}
            </x-slot:end>
        </atom:table.toolbar>

        <atom:table>
            <x-slot:checked>
                <atom:button>Assign</atom:button>
                <atom:button>Schedule</atom:button>
                <atom:button>Export selected</atom:button>
                <atom:button> {{-- expect: action-cap --}}
                    Send reminders
                </atom:button>
                <atom:button type="delete">Delete</atom:button>
            </x-slot:checked>

            <x-slot:empty>
                <atom:empty heading="No jobs yet">
                    <x-slot:action>
                        <atom:button>Create a job</atom:button>
                        <atom:button>Import jobs</atom:button> {{-- expect: action-cap --}}
                    </x-slot:action>
                </atom:empty>
            </x-slot:empty>
        </atom:table>
    </atom:panel>

    <atom:card heading="Customer">
        <x-slot:actions>
            <atom:button variant="ghost">Edit</atom:button>
            <atom:button variant="ghost">Change</atom:button> {{-- expect: action-cap --}}
        </x-slot:actions>
    </atom:card>
</atom:page>

<atom:modal name="job" slide>
    <atom:page.header title="New job" level="2" size="sm">
        <x-slot:actions>
            <atom:button size="sm">Preview</atom:button>
            <atom:button size="sm">Reset</atom:button> {{-- expect: action-cap --}}
        </x-slot:actions>
    </atom:page.header>
</atom:modal>
