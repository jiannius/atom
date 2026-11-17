{{-- action-cap with `<atom:action>`s: the buttons draw first, then the actions atom makes visible (ActionPlacement), so the finding sits on a button. A line marked `expect:` is a finding; every other line must stay clean. --}}
<atom:page>
    <atom:page.header title="2 buttons and 2 actions">
        <x-slot:actions>
            <atom:button>Import</atom:button> {{-- expect: action-cap --}}
            <atom:button>Export</atom:button>
            <atom:action wire:click="print">Print</atom:action>
            <atom:action wire:click="refresh">Refresh</atom:action>
        </x-slot:actions>
    </atom:page.header>

    <atom:page.header title="1 button and 2 actions">
        <x-slot:actions>
            <atom:button>Import</atom:button> {{-- expect: action-cap --}}
            <atom:action wire:click="print">Print</atom:action>
            <atom:action wire:click="refresh">Refresh</atom:action>
        </x-slot:actions>
    </atom:page.header>

    <atom:page.header title="Actions alone never reach the cap">
        <x-slot:actions>
            <atom:action wire:click="a">One</atom:action>
            <atom:action wire:click="b">Two</atom:action>
            <atom:action wire:click="c">Three</atom:action>
            <atom:action wire:click="d">Four</atom:action>
            <atom:action wire:click="e">Five</atom:action>
        </x-slot:actions>
    </atom:page.header>

    <atom:page.header title="A button and one action fit">
        <x-slot:actions>
            <atom:button>Import</atom:button>
            <atom:action wire:click="print">Print</atom:action>
        </x-slot:actions>
    </atom:page.header>

    <atom:page.header title="What atom does not show does not count">
        <x-slot:actions>
            <atom:button>Import</atom:button>
            <atom:button>Export</atom:button>
            <atom:action priority="low" wire:click="a">Duplicate</atom:action>
            <atom:action tone="warning" wire:click="b">Void</atom:action>
            <atom:action tone="delete" wire:click="c">Delete</atom:action>
            <atom:action priority="primary" wire:click="d">Send</atom:action>
            <atom:action :priority="$level" wire:click="e">Bound</atom:action>
            <atom:action :tone="$tone" wire:click="f">Bound</atom:action>
        </x-slot:actions>
    </atom:page.header>

    <atom:page.header title="Branches that cannot show together">
        <x-slot:actions>
            @if ($open)
                <atom:button>Import</atom:button>
                <atom:button>Export</atom:button>
            @else
                <atom:action wire:click="a">One</atom:action>
                <atom:action wire:click="b">Two</atom:action>
                <atom:action wire:click="c">Three</atom:action>
            @endif
        </x-slot:actions>
    </atom:page.header>

    <atom:page.header title="A size sm header holds 1" size="sm" level="2">
        <x-slot:actions>
            <atom:button size="sm">Preview</atom:button> {{-- expect: action-cap --}}
            <atom:action wire:click="a">Reset</atom:action>
        </x-slot:actions>
    </atom:page.header>

    <atom:page.header title="A bound size is not read" :size="$size">
        <x-slot:actions>
            <atom:button>Preview</atom:button>
            <atom:action wire:click="a">Reset</atom:action>
            <atom:action wire:click="b">Reload</atom:action>
        </x-slot:actions>
    </atom:page.header>

    <atom:page.header title="The host fills the primary slot, so a primary action is a normal one">
        <x-slot:actions>
            <atom:button>Import</atom:button> {{-- expect: action-cap --}}
            <atom:button>Export</atom:button>
            <atom:action priority="primary" wire:click="a">Send</atom:action>
        </x-slot:actions>

        <x-slot:primary>
            <atom:button variant="primary">Create</atom:button>
        </x-slot:primary>
    </atom:page.header>

    <atom:page.header title="The slot is only actions, so the action in it is the primary and costs no button">
        <x-slot:actions>
            <atom:button>Import</atom:button>
            <atom:button>Export</atom:button>
        </x-slot:actions>

        <x-slot:primary>
            @if ($ready)
                <atom:action wire:click="b">Create</atom:action>
            @endif
        </x-slot:primary>
    </atom:page.header>

    <atom:table.toolbar>
        <atom:action wire:click="a">Print</atom:action>

        <x-slot:actions>
            <atom:button>Import</atom:button> {{-- expect: action-cap --}}
            <atom:button>Export</atom:button>
        </x-slot:actions>

        <x-slot:end>
            <atom:action wire:click="b">Columns</atom:action>
        </x-slot:end>
    </atom:table.toolbar>

    <atom:table>
        <x-slot:checked>
            <atom:button>Assign</atom:button> {{-- expect: action-cap --}}
            <atom:button>Schedule</atom:button>
            <atom:action wire:click="a">Export selected</atom:action>
            <atom:action wire:click="b">Send reminders</atom:action>
        </x-slot:checked>
    </atom:table>

    <atom:table>
        <x-slot:checked>
            <atom:button>Assign</atom:button>
            <atom:button>Schedule</atom:button>
            <atom:action wire:click="a">Export selected</atom:action>
            <atom:action tone="delete" wire:click="b">Delete selected</atom:action>
        </x-slot:checked>
    </atom:table>

    <atom:card heading="Customer">
        <x-slot:actions>
            <atom:button variant="ghost">Edit</atom:button> {{-- expect: action-cap --}}
            <atom:action wire:click="a">Change</atom:action>
        </x-slot:actions>
    </atom:card>

    <atom:panel heading="Lines">
        <x-slot:actions>
            <atom:action wire:click="a">Add line</atom:action>
        </x-slot:actions>

        <x-slot:menu>
            <atom:action wire:click="b">Import</atom:action>
        </x-slot:menu>
    </atom:panel>
</atom:page>
