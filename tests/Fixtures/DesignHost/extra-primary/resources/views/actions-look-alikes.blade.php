{{-- extra-primary: none of these is flagged. --}}
<atom:page>
    <atom:page.header title="Branches, tones and values the check does not read">
        <x-slot:primary>
            @if ($ready)
                <atom:action wire:click="send">Send</atom:action>
            @else
                <atom:action wire:click="approve">Approve</atom:action>
            @endif
        </x-slot:primary>

        <x-slot:actions>
            <atom:action tone="warning" priority="primary" wire:click="void">Void</atom:action>
            <atom:action :priority="$level" wire:click="a">Bound</atom:action>
            <atom:action priority="normal" wire:click="b">Normal</atom:action>
            <atom:action priority="low" wire:click="c">Low</atom:action>
        </x-slot:actions>
    </atom:page.header>

    <atom:callout heading="Overdue">
        <atom:button wire:click="remind">Remind</atom:button>
    </atom:callout>
</atom:page>
