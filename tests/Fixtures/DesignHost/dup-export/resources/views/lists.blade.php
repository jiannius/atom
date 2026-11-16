{{-- dup-export: the same button in the toolbar and in the bulk bar. A line marked `expect:` is a finding; every other line must stay clean. --}}
<atom:page>
    <atom:page.header title="Jobs" />

    {{-- a toolbar in the table's own header slot --}}
    <atom:panel>
        <atom:table>
            <x-slot:header>
                <atom:table.toolbar>
                    <x-slot:actions>
                        <atom:button icon="download">Export</atom:button>
                        <atom:button icon="upload">Import</atom:button>
                    </x-slot:actions>
                </atom:table.toolbar>
            </x-slot:header>

            <x-slot:checked>
                <atom:button icon="download"> {{-- expect: dup-export --}}
                    export
                </atom:button>
                <atom:button>Assign</atom:button>
            </x-slot:checked>
        </atom:table>
    </atom:panel>

    {{-- a toolbar above the table, in the same panel --}}
    <atom:panel>
        <atom:table.toolbar>
            <x-slot:actions>
                <atom:button>Print</atom:button>
            </x-slot:actions>

            <x-slot:end>
                <atom:button.group>
                    <atom:button>Share  list</atom:button>
                </atom:button.group>
            </x-slot:end>
        </atom:table.toolbar>

        <atom:table>
            <x-slot:checked>
                <atom:button>Print</atom:button> {{-- expect: dup-export --}}
                <atom:button>Share list</atom:button> {{-- expect: dup-export --}}
            </x-slot:checked>
        </atom:table>
    </atom:panel>

    {{-- the look-alikes: "Export" beside "Export selected"; labels that are not read; another table's toolbar --}}
    <atom:panel>
        <atom:table.toolbar>
            <x-slot:actions>
                <atom:button>Export</atom:button>
                <atom:button>{{ $label }}</atom:button>
                <atom:button icon="download" />
            </x-slot:actions>
        </atom:table.toolbar>

        <atom:table>
            <x-slot:checked>
                <atom:button>Export selected</atom:button>
                <atom:button>{{ $label }}</atom:button>
                <atom:button icon="download" />
            </x-slot:checked>
        </atom:table>
    </atom:panel>

    <atom:panel>
        <atom:table.toolbar>
            <x-slot:actions>
                <atom:button>Archive</atom:button>
            </x-slot:actions>
        </atom:table.toolbar>
    </atom:panel>

    <atom:panel>
        <atom:table>
            <x-slot:checked>
                <atom:button>Archive</atom:button>
            </x-slot:checked>
        </atom:table>
    </atom:panel>

    {{-- a toolbar below the table steers nothing: the table is above it --}}
    <atom:panel>
        <atom:table>
            <x-slot:checked>
                <atom:button>Archive again</atom:button>
            </x-slot:checked>
        </atom:table>

        <atom:table.toolbar>
            <x-slot:actions>
                <atom:button>Archive again</atom:button>
            </x-slot:actions>
        </atom:table.toolbar>
    </atom:panel>

    {{-- a toolbar in one table's header and the bulk bar of the one beside it --}}
    <atom:panel>
        <atom:table>
            <x-slot:header>
                <atom:table.toolbar>
                    <x-slot:actions>
                        <atom:button>Print labels</atom:button>
                    </x-slot:actions>
                </atom:table.toolbar>
            </x-slot:header>
        </atom:table>

        <atom:table>
            <x-slot:checked>
                <atom:button>Print labels</atom:button>
            </x-slot:checked>
        </atom:table>
    </atom:panel>

    <atom:button>Export</atom:button>
</atom:page>
