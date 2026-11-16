{{-- Tone and confirm, built to the rules: a warning that asks, a method that asks, a dismiss, a reset of the screen, the tint. --}}
<atom:menu.item variant="warning" confirm>Archive invoice</atom:menu.item>
<atom:menu.item variant="warning" wire:click="suspend">Suspend billing</atom:menu.item>
<atom:menu.item variant="delete">Delete invoice</atom:menu.item>

<atom:confirm.trigger variant="warning" heading="Void invoice?" button-confirm="Void invoice" x-on:confirmed="$wire.void()">
    <atom:menu.item variant="warning">Void invoice</atom:menu.item>
</atom:confirm.trigger>

<atom:button variant="ghost">Cancel</atom:button>
<atom:button variant="ghost">Reset filters</atom:button>
<atom:button variant="danger" inverted wire:click="purge" confirm>Purge</atom:button>
<atom:button type="delete">Delete</atom:button>
