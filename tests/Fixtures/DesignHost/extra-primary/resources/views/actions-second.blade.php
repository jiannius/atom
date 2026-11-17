{{-- extra-primary with `<atom:action>`s: the header draws the first untoned `priority="primary"` action as its primary (it counts toward the screen's one), and any other is a plain action. A line marked `expect:` is a finding; every other line must stay clean. --}}
<atom:page>
    <atom:page.header title="A second primary action in one header">
        <x-slot:primary>
            <atom:action wire:click="send">Send</atom:action>
        </x-slot:primary>

        <x-slot:actions>
            <atom:action priority="primary" wire:click="approve">Approve</atom:action> {{-- expect: extra-primary --}}
            <atom:action wire:click="edit">Edit</atom:action>
        </x-slot:actions>
    </atom:page.header>

    <atom:callout heading="Overdue">
        <atom:button variant="primary">Remind</atom:button> {{-- expect: extra-primary --}}
    </atom:callout>

    <atom:callout heading="A plain button is fine">
        <atom:button wire:click="remind">Remind</atom:button>
    </atom:callout>
</atom:page>
