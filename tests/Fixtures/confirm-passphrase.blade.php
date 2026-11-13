<div class="flex flex-col items-start gap-2">
    <button type="button" wire:click="ask">Ask from PHP</button>
    <atom:menu class="w-56">
        <atom:menu.item variant="delete" phrase="remove-me">Delete with phrase</atom:menu.item>
        <atom:menu.item variant="delete" passphrase="it's ok">Delete with passphrase</atom:menu.item>
    </atom:menu>
    <atom:button type="delete" passphrase="btn-pass">Delete button</atom:button>
    <output data-testid="received">{{ $received }}</output>
</div>
