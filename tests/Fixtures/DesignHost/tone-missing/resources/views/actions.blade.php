{{-- tone-missing: a warning or danger verb with no tone. A line marked `expect:` is a finding; every other line must stay clean. --}}
<atom:page>
    <atom:page.header title="Order">
        <x-slot:menu>
            <atom:menu.item>Trash</atom:menu.item> {{-- expect: tone-missing --}}
            <atom:menu.item icon="delete">Remove member</atom:menu.item> {{-- expect: tone-missing --}}
            <atom:menu.item icon="delete">{{ $label }}</atom:menu.item> {{-- expect: tone-missing --}}
            <atom:menu.item>Delete</atom:menu.item> {{-- expect: tone-missing --}}
            <atom:menu.item>Archive</atom:menu.item> {{-- expect: tone-missing --}}
            <atom:menu.item variant="default">Void invoice</atom:menu.item> {{-- expect: tone-missing --}}
            <atom:menu.item>{{ t('Revoke access') }}</atom:menu.item> {{-- expect: tone-missing --}}
        </x-slot:menu>

        <x-slot:actions>
            <atom:button>Cancel order</atom:button> {{-- expect: tone-missing --}}
            <atom:button variant="ghost">Delete draft</atom:button> {{-- expect: tone-missing --}}
            <atom:button variant="link">Suspend account</atom:button> {{-- expect: tone-missing --}}
            <atom:button variant="ghost" color="primary">Reject quote</atom:button> {{-- expect: tone-missing --}}
            <atom:button icon="trash">Discard draft</atom:button> {{-- expect: tone-missing --}}
            {{-- expect: tone-missing --}}
            <atom:button>
                Decline
            </atom:button>
            <atom:button variant="delete">Delete</atom:button> {{-- expect: tone-missing --}}
            <atom:button variant="remove" icon="delete">Eliminate</atom:button> {{-- expect: tone-missing --}}
        </x-slot:actions>
    </atom:page.header>

    {{-- the look-alikes: none of these is flagged --}}
    <atom:button variant="ghost">Cancel</atom:button>
    <atom:button>Close</atom:button>
    <atom:button>Cancel edit</atom:button>
    <atom:button>Cancel changes</atom:button>
    <atom:button variant="ghost">Reset filters</atom:button>
    <atom:button>Reset password</atom:button>
    <atom:button>Reset view</atom:button>
    <atom:button>Remove filter</atom:button>
    <atom:button>Cancel upload</atom:button>
    <atom:button>Cancel add line</atom:button>
    <atom:button>Cancel and go back</atom:button>
    <atom:button>Cancel & close</atom:button>
    <atom:button>Reset all filters</atom:button>
    <atom:button>Reset to defaults</atom:button>
    <atom:button>Reset your password</atom:button>
    <atom:menu.item>Reset to default</atom:menu.item>
    <atom:menu.item href="{{ route('orders.archive') }}">Archive</atom:menu.item>
    <atom:button wire:click="$set('tab', 'archive')">Archive</atom:button>
    <atom:button x-on:click="$dispatch('open-trash')">Trash</atom:button>
    <atom:button>Discard changes</atom:button>
    <atom:menu.item>Cancellation policy</atom:menu.item>
    <atom:menu.item>Archived invoices</atom:menu.item>
    <atom:menu.item>Deleted items</atom:menu.item>
    <atom:menu.item>Download the archive</atom:menu.item>
    <atom:menu.item icon="eye">View</atom:menu.item>
    <atom:menu.item>{{ $label }}</atom:menu.item>
    <atom:menu.item>Delete {{ $name }}</atom:menu.item>
    <atom:menu.item :variant="$tone">Void</atom:menu.item>
    <atom:button :variant="$tone">Void</atom:button>
    <atom:button :type="$type">Delete</atom:button>

    {{-- already toned, or confirming themselves --}}
    <atom:menu.item variant="warning" confirm>Archive</atom:menu.item>
    <atom:menu.item variant="delete">Delete</atom:menu.item>
    <atom:menu.item variant="remove">Remove</atom:menu.item>
    <atom:button variant="warning" inverted confirm>Cancel order</atom:button>
    <atom:button variant="ghost" color="danger">Delete</atom:button>
    <atom:button variant="danger" inverted>Purge</atom:button>
    <atom:button type="delete">Delete</atom:button>
    <atom:button type="delete">Archive</atom:button>

    {{-- a choice that is not a tone, a submit and a link that is not a button --}}
    <atom:button type="submit">Reject</atom:button>
    <atom:button variant="primary">Reject</atom:button>
    <atom:link href="#">Delete</atom:link>

    {{-- a line of a form --}}
    <div class="flex gap-2">
        <atom:input label="Part" wire:model="line.part" />
        <atom:button variant="ghost" icon="x">Remove</atom:button>
    </div>

    {{-- a line of a form, whatever the markup around it --}}
    <atom:table.row>
        <atom:table.cell><atom:input wire:model="lines.0.name" /></atom:table.cell>
        <atom:table.cell><atom:button variant="ghost" icon="x" wire:click="removeLine(0)">Remove</atom:button></atom:table.cell>
    </atom:table.row>
    <tr>
        <td><input wire:model="lines.0.qty"></td>
        <td><atom:button variant="ghost" icon="x" wire:click="removeLine(0)">Remove</atom:button></td>
    </tr>
    <div class="grid grid-cols-3">
        <div><atom:select.native wire:model="lines.0.unit" /></div>
        <div><atom:button variant="ghost" icon="x" wire:click="removeLine(0)">Remove</atom:button></div>
    </div>
    <atom:card>
        <atom:input wire:model="lines.0.note" />
        <atom:button variant="ghost" wire:click="removeLine(0)">Remove</atom:button>
    </atom:card>

    {{-- a Remove with no control around it is the record's --}}
    <atom:table.row>
        <atom:table.cell>Part A</atom:table.cell>
        <atom:table.cell><atom:button variant="ghost" icon="x" wire:click="removePart">Remove</atom:button></atom:table.cell> {{-- expect: tone-missing --}}
    </atom:table.row>

    {{-- a control that is not the line's own: a search box, a toolbar's select, a selection checkbox, one past a card or in the header --}}
    <div class="flex">
        <atom:input type="search" wire:model="search" />
        <atom:button variant="ghost" icon="x" wire:click="removeFilterSet">Remove</atom:button> {{-- expect: tone-missing --}}
    </div>
    <atom:table.toolbar>
        <atom:select wire:model="filters.status" />
        <atom:button variant="ghost" icon="x" wire:click="removeView">Remove</atom:button> {{-- expect: tone-missing --}}
    </atom:table.toolbar>
    <atom:table.row>
        <atom:table.cell><atom:checkbox wire:model="checked" /></atom:table.cell>
        <atom:table.cell><atom:button variant="ghost" icon="x" wire:click="removePart">Remove</atom:button></atom:table.cell> {{-- expect: tone-missing --}}
    </atom:table.row>
    <div>
        <atom:select wire:model="unit" />
        <atom:card><div><atom:button variant="ghost" wire:click="removePart">Remove</atom:button></div></atom:card> {{-- expect: tone-missing --}}
    </div>
    <atom:page.header title="Part">
        <atom:input wire:model="name" />
        <atom:button variant="ghost" wire:click="removePart">Remove</atom:button> {{-- expect: tone-missing --}}
    </atom:page.header>
    <atom:button>Cancel add-on</atom:button> {{-- expect: tone-missing --}}

    <atom:form wire:submit="save">
        <atom:button variant="ghost">Reset</atom:button>
        <atom:button variant="ghost">Discard</atom:button>
    </atom:form>

    <atom:form.modal name="x"><atom:button variant="ghost">Discard</atom:button></atom:form.modal>

    <!-- <atom:button>Delete</atom:button> -->
    {{-- <atom:menu.item>Archive</atom:menu.item> --}}
</atom:page>
