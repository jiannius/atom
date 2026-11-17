{{-- tone-missing on `<atom:action>`: a warning or danger verb with no `tone`. A line marked `expect:` is a finding; every other line must stay clean. --}}
<atom:page>
    <atom:page.header title="Order">
        <x-slot:menu>
            <atom:action wire:click="trash">Trash</atom:action> {{-- expect: tone-missing --}}
            <atom:action icon="delete" wire:click="remove">Remove member</atom:action> {{-- expect: tone-missing --}}
            <atom:action icon="delete" wire:click="remove">{{ $label }}</atom:action> {{-- expect: tone-missing --}}
            <atom:action wire:click="destroy">Delete</atom:action> {{-- expect: tone-missing --}}
            <atom:action wire:click="archive">Archive</atom:action> {{-- expect: tone-missing --}}
            <atom:action label="Void invoice" wire:click="void" /> {{-- expect: tone-missing --}}
            <atom:action wire:click="revoke">{{ t('Revoke access') }}</atom:action> {{-- expect: tone-missing --}}
            <atom:action priority="low" wire:click="cancel">Cancel order</atom:action> {{-- expect: tone-missing --}}

            {{-- the look-alikes: none of these is flagged --}}
            <atom:action tone="warning" wire:click="void">Void invoice</atom:action>
            <atom:action tone="delete" wire:click="destroy">Delete</atom:action>
            <atom:action :tone="$tone" wire:click="destroy">Delete</atom:action>
            <atom:action wire:click="close">Cancel</atom:action>
            <atom:action wire:click="cancel">Cancel edit</atom:action>
            <atom:action wire:click="reset">Reset filters</atom:action>
            <atom:action :href="route('orders.archive')">Archive</atom:action>
            <atom:action wire:click="$set('tab', 'archive')">Archive</atom:action>
            <atom:action wire:click="a">{{ $x }}</atom:action>
            <atom:action wire:click="a">Duplicate</atom:action>
        </x-slot:menu>
    </atom:page.header>
</atom:page>
