{{-- confirm-copy on `<atom:action>`: its `confirm` is read as a button's and an item's. A line marked `expect:` is a finding; every other line must stay clean. --}}
<atom:page.header title="Invoice">
    <x-slot:menu>
        <atom:action tone="warning" :confirm="['buttonConfirm' => 'Yes']" wire:click="void">Void invoice</atom:action> {{-- expect: confirm-copy --}}
        <atom:action tone="warning" :confirm="['heading' => 'Void invoice?', 'buttonConfirm' => t('Proceed')]" wire:click="void">Void invoice</atom:action> {{-- expect: confirm-copy --}}
        <atom:action tone="warning" :confirm="['heading' => 'Void THIS invoice?']" wire:click="void">Void invoice</atom:action> {{-- expect: confirm-copy --}}
        <atom:action tone="danger" :confirm="['message' => ['It leaves every list.', 'NEVER undo it.']]" wire:click="remove">Remove invoice</atom:action> {{-- expect: confirm-copy --}}
        <atom:action tone="warning" confirm="Void THIS invoice?" wire:click="void">Void invoice</atom:action> {{-- expect: confirm-copy --}}

        {{-- the look-alikes: none of these is flagged --}}
        <atom:action tone="warning" wire:click="void">Void invoice</atom:action>
        <atom:action tone="warning" confirm wire:click="void">Void invoice</atom:action>
        <atom:action tone="warning" :confirm="false" wire:click="void">Void invoice</atom:action>
        <atom:action tone="warning" :confirm="['heading' => 'Void invoice?', 'buttonConfirm' => 'Void']" wire:click="void">Void invoice</atom:action>
        <atom:action tone="warning" :confirm="['heading' => 'Void this PDF?']" wire:click="void">Void invoice</atom:action>
        <atom:action tone="warning" :confirm="$copy" wire:click="void">Void invoice</atom:action>
    </x-slot:menu>
</atom:page.header>
