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

    <atom:form wire:submit="save">
        <atom:button variant="ghost">Reset</atom:button>
        <atom:button variant="ghost">Discard</atom:button>
    </atom:form>

    <!-- <atom:button>Delete</atom:button> -->
    {{-- <atom:menu.item>Archive</atom:menu.item> --}}
</atom:page>
