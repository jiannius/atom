{{-- dup-export with `<atom:action>`s: an action is read like a button, in the same slots (the toolbar's default slot too). A line marked `expect:` is a finding; every other line must stay clean. --}}
<atom:page>
    <atom:panel>
        <atom:table>
            <x-slot:header>
                <atom:table.toolbar>
                    <atom:action icon="download" wire:click="export">Export</atom:action>

                    <x-slot:actions>
                        <atom:action wire:click="import">Import</atom:action>
                        <atom:button>Print</atom:button>
                    </x-slot:actions>
                </atom:table.toolbar>
            </x-slot:header>

            <x-slot:checked>
                <atom:action icon="download" wire:click="exportSelected"> {{-- expect: dup-export --}}
                    export
                </atom:action>
                <atom:action wire:click="import">Import</atom:action> {{-- expect: dup-export --}}
                <atom:button>Print</atom:button> {{-- expect: dup-export --}}
                <atom:action wire:click="assign">Assign</atom:action>
                <atom:action label="Export selected" wire:click="exportSelected" />
                <atom:action wire:click="x">{{ $label }}</atom:action>
            </x-slot:checked>
        </atom:table>
    </atom:panel>

    {{-- a toolbar with the same label, in its menu slot, is not a button there --}}
    <atom:panel>
        <atom:table>
            <x-slot:header>
                <atom:table.toolbar>
                    <x-slot:menu>
                        <atom:action wire:click="a">Archive</atom:action>
                    </x-slot:menu>
                </atom:table.toolbar>
            </x-slot:header>

            <x-slot:checked>
                <atom:action wire:click="a">Archive</atom:action>
            </x-slot:checked>
        </atom:table>
    </atom:panel>
</atom:page>
