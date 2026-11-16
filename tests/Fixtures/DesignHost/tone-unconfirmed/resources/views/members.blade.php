{{-- tone-unconfirmed: a warning or danger action that acts without asking. A line marked `expect:` is a finding; every other line must stay clean. --}}
<atom:page>
    <atom:page.header title="Member">
        <x-slot:menu>
            <atom:menu.item variant="warning" wire:click="archive">Archive</atom:menu.item> {{-- expect: tone-unconfirmed --}}
            <atom:menu.item variant="danger" wire:click="expel">Expel</atom:menu.item> {{-- expect: tone-unconfirmed --}}
            <atom:menu.item variant="remove" wire:click="removeLine({{ $id }})">Remove line</atom:menu.item> {{-- expect: tone-unconfirmed --}}
            <atom:menu.item variant="warning" wire:click.prevent="resume">Resume</atom:menu.item> {{-- expect: tone-unconfirmed --}}
            <atom:menu.item variant="warning" wire:click="chain">Chain</atom:menu.item> {{-- expect: tone-unconfirmed --}}
            <atom:menu.item variant="warning" wire:click="outer">Outer</atom:menu.item> {{-- expect: tone-unconfirmed --}}
            <atom:menu.item variant="warning" wire:click="SUSPENDED">Upper</atom:menu.item> {{-- expect: tone-unconfirmed --}}
        </x-slot:menu>

        <x-slot:actions>
            <atom:button variant="danger" wire:click="purge">Purge</atom:button> {{-- expect: tone-unconfirmed --}}
            <atom:button variant="error" wire:click="purge">Purge</atom:button> {{-- expect: tone-unconfirmed --}}
            <atom:button variant="warning" inverted x-on:click="$wire.void()">Void</atom:button> {{-- expect: tone-unconfirmed --}}
            <atom:button variant="ghost" color="danger" wire:click="purge">Purge</atom:button> {{-- expect: tone-unconfirmed --}}
            {{-- expect: tone-unconfirmed --}}
            <atom:button
                variant="warning"
                wire:click="release"
            >Release</atom:button>
        </x-slot:actions>
    </atom:page.header>

    {{-- the look-alikes: none of these is flagged --}}

    {{-- a delete confirms itself --}}
    <atom:menu.item variant="delete" wire:click="destroy">Delete</atom:menu.item>
    <atom:button type="delete" wire:click="destroy">Delete</atom:button>
    <atom:button type="delete" variant="danger" wire:click="destroy">Delete</atom:button>

    {{-- asks, in any way --}}
    <atom:menu.item variant="warning" confirm wire:click="archive">Archive</atom:menu.item>
    <atom:menu.item variant="warning" confirm="Archive this member?" wire:click="archive">Archive</atom:menu.item>
    <atom:menu.item variant="warning" :confirm="['heading' => 'Archive?']" wire:click="archive">Archive</atom:menu.item>
    <atom:menu.item variant="warning" :confirm="false" wire:click="archive">Archive</atom:menu.item>
    <atom:button variant="danger" wire:click="purge" wire:confirm="Purge it?">Purge</atom:button>
    <atom:button variant="danger" wire:click="purge" x-on:click="atom.confirm({ heading: 'Purge?' })">Purge</atom:button>
    <atom:button variant="danger" wire:click="purge" x-on:click="$dispatch('atom-confirm-show', { heading: 'Purge?' })">Purge</atom:button>
    <atom:button variant="danger" wire:click="purge" x-on:click="$dispatch('atom-modal-show', 'purge')">Purge</atom:button>
    <atom:button variant="danger" wire:click="purge" x-on:click="atom.modal('purge').show()">Purge</atom:button>
    <atom:button variant="danger" x-on:click="window.confirm('Sure?') && $wire.purge()">Purge</atom:button>

    {{-- a wrapper or a dialog of its own --}}
    <atom:confirm.trigger variant="warning" heading="Archive?" button-confirm="Archive" x-on:confirmed="$wire.archive()">
        <atom:menu.item variant="warning" wire:click="archive">Archive</atom:menu.item>
    </atom:confirm.trigger>
    <atom:modal name="purge">
        <atom:button variant="danger" wire:click="purge">Purge</atom:button>
    </atom:modal>

    {{-- the method asks (app/Livewire/Members.php) --}}
    <atom:menu.item variant="warning" wire:click="suspend">Suspend</atom:menu.item>
    <atom:menu.item variant="warning" wire:click="suspend({{ $id }})">Suspend</atom:menu.item>
    <atom:button variant="danger" x-on:click="$wire.suspend()">Suspend</atom:button>
    <atom:button variant="danger" wire:click="$parent.suspend">Suspend</atom:button>

    {{-- the method hands over to one that asks, opens a modal or names the dialog event --}}
    <atom:menu.item variant="warning" wire:click="hand">Hand</atom:menu.item>
    <atom:menu.item variant="warning" wire:click="dialog">Dialog</atom:menu.item>
    <atom:menu.item variant="warning" wire:click="viaEvent">Event</atom:menu.item>
    <atom:menu.item variant="warning" wire:click="SUSPEND">Suspend</atom:menu.item>

    {{-- a method that only opens a dialog --}}
    <atom:button variant="danger" wire:click="confirmPurge">Purge</atom:button>
    <atom:button variant="danger" wire:click="askToPurge">Purge</atom:button>
    <atom:button variant="danger" wire:click="openPurge">Purge</atom:button>
    <atom:button variant="danger" onclick="return confirm('Purge?')" wire:click="purge">Purge</atom:button>

    {{-- plumbing, not an action of the host's --}}
    <atom:button variant="danger" x-on:click="$wire.set('open', true)">Purge</atom:button>
    <atom:button variant="danger" x-on:click="$wire.$set('open', true)">Purge</atom:button>
    <atom:button variant="danger" x-on:click="$wire.dispatch('purge')">Purge</atom:button>
    <atom:button variant="danger" wire:click="$set('open', true)">Purge</atom:button>
    <atom:button variant="danger" wire:click="$toggle('open')">Purge</atom:button>

    {{-- a submit, a link, an Alpine-only handler, no handler, or a value that cannot be read --}}
    <atom:button variant="danger" type="submit">Close account</atom:button>
    <atom:button variant="warning" href="{{ route('orders.cancel', $order) }}">Cancel order</atom:button>
    <atom:menu.item variant="warning" href="{{ route('orders.show', $order) }}" wire:click="track">Track</atom:menu.item>
    <atom:button variant="danger" x-on:click="open = false">Close</atom:button>
    <atom:button variant="danger">Purge</atom:button>
    <atom:menu.item variant="warning">Archive</atom:menu.item>
    <atom:button variant="danger" wire:click="{{ $method }}">Purge</atom:button>
    <atom:button :variant="$tone" wire:click="purge">Purge</atom:button>
    <atom:button variant="danger" :type="$type" wire:click="purge">Purge</atom:button>

    {{-- not a tone --}}
    <atom:menu.item wire:click="archive">Archive</atom:menu.item>
    <atom:button variant="primary" wire:click="send">Send</atom:button>
    <atom:button variant="ghost" wire:click="send">Send</atom:button>

    <!-- <atom:button variant="danger" wire:click="purge">Purge</atom:button> -->
</atom:page>
