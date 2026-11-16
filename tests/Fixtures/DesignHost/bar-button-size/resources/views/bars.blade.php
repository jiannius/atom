{{-- bar-button-size: a small button in a toolbar or a bulk bar. A line marked `expect:` is a finding; every other line must stay clean. --}}
<atom:page>
    <atom:page.header title="Jobs" size="sm">
        <x-slot:actions>
            <atom:button size="sm">Import</atom:button>
        </x-slot:actions>
    </atom:page.header>

    <atom:panel>
        <atom:table.toolbar>
            <atom:table.search placeholder="Search jobs" />

            <atom:button size="sm">Refresh</atom:button> {{-- expect: bar-button-size --}}

            <x-slot:actions>
                <atom:button size="sm">Export</atom:button> {{-- expect: bar-button-size --}}
                <atom:button size="xs" variant="ghost">Print</atom:button> {{-- expect: bar-button-size --}}
                <atom:button xs>Share</atom:button> {{-- expect: bar-button-size --}}
                <atom:button size="md">Refresh</atom:button>
                <atom:button :size="$size">Bound</atom:button>
                <atom:button xs :size="$size">Bound, and a bare one</atom:button>

                <atom:dropdown>
                    <atom:button size="sm" icon="down">Choose</atom:button> {{-- expect: bar-button-size --}}
                    <atom:menu popover><atom:menu.item>CSV</atom:menu.item></atom:menu>
                </atom:dropdown>
                <atom:button>Plain</atom:button>

                <atom:button.group>
                    <atom:button size="sm">Day</atom:button> {{-- expect: bar-button-size --}}
                    <atom:button>Week</atom:button>
                </atom:button.group>
            </x-slot:actions>

            <x-slot:end>
                <atom:confirm.trigger>
                    <atom:button size="sm" variant="ghost">Reset</atom:button> {{-- expect: bar-button-size --}}
                </atom:confirm.trigger>
            </x-slot:end>
        </atom:table.toolbar>

        <atom:table>
            <x-slot:checked>
                {{-- expect: bar-button-size --}}
                <atom:button
                    size="sm"
                    variant="ghost"
                >
                    Assign
                </atom:button>
                <atom:button type="delete">Delete</atom:button>
            </x-slot:checked>

            <x-slot:rows>
                <atom:table.row>
                    <atom:table.cell><atom:button size="sm">View</atom:button></atom:table.cell>
                </atom:table.row>
            </x-slot:rows>
        </atom:table>
    </atom:panel>

    {{-- the look-alikes: the shell's slots, a header, a card, a button in a popover of the toolbar --}}
    <x-dynamic-component component="atom::layouts.sidebar" title="Record">
        <x-slot:actions>
            <atom:button size="sm">Previous</atom:button>
            <atom:button size="sm">Next</atom:button>
        </x-slot:actions>
    </x-dynamic-component>

    <atom:card heading="Customer">
        <x-slot:actions>
            <atom:button size="sm" variant="ghost">Edit</atom:button>
        </x-slot:actions>

        <x-slot:checked>
            <atom:button size="sm">Not a bulk bar</atom:button>
        </x-slot:checked>
    </atom:card>

    <atom:table.toolbar>
        <atom:dropdown>
            <atom:button>Filters</atom:button>
            <atom:menu popover>
                <atom:button size="sm">Apply</atom:button>
            </atom:menu>
        </atom:dropdown>
    </atom:table.toolbar>

    <!-- <atom:table.toolbar><x-slot:actions><atom:button size="sm">Commented</atom:button></x-slot:actions></atom:table.toolbar> -->
</atom:page>
