<div class="flex flex-col items-start gap-3">
    <output data-testid="log">{{ json_encode($log) }}</output>
    <output data-testid="value">{{ $value }}</output>
    <output data-testid="renders">{{ $renders }}</output>

    {{-- the header's ⋯ menu: an item that confirms in warning --}}
    <atom:dropdown data-testid="more">
        <atom:button icon="ellipsis" tooltip="More" data-testid="more-trigger"/>

        <atom:menu popover>
            <atom:menu.item variant="warning" wire:click="record('cancel-job')" confirm="Cancel this job?" data-testid="item-warning">Cancel job</atom:menu.item>
            <atom:menu.item wire:click="record('plain-item')" data-testid="item-plain">Plain item</atom:menu.item>
            <atom:menu.item variant="delete" wire:click="record('item-delete')" data-testid="item-delete">Delete invoice</atom:menu.item>
        </atom:menu>
    </atom:dropdown>

    <atom:button wire:click="record('bare')" confirm data-testid="bare">Archive invoice</atom:button>
    <atom:button wire:click="$set('value', 'set')" confirm="Set it?" data-testid="set">Set value</atom:button>
    <atom:button wire:click="record('array')" variant="warning" inverted :confirm="['heading' => 'Archive this client?', 'message' => 'It leaves every list; its records stay.', 'buttonConfirm' => 'Archive client', 'buttonCancel' => 'Keep it']" data-testid="array">Archive</atom:button>
    <atom:button wire:click="record('pass')" :confirm="['heading' => 'Remove the domain?', 'passphrase' => 'example.com']" data-testid="passphrase">Remove domain</atom:button>
    <atom:button wire:click="record('reason')" :confirm="['heading' => 'Void the invoice?', 'reason' => true]" x-on:confirmed="window.__confirmed = $event.detail" data-testid="reason">Void invoice</atom:button>
    <atom:button x-on:click="window.__clicks = (window.__clicks ?? 0) + 1" confirm data-testid="alpine">Count a click</atom:button>
    <atom:button href="/atom/e2e/confirm-prop/landing" confirm data-testid="link">Go to the landing page</atom:button>
    <atom:button href="/atom/e2e/confirm-prop/landing" newtab confirm data-testid="newtab">Open in a tab</atom:button>
    <atom:button wire:click="slow" confirm data-testid="slow">Slow action</atom:button>
    <atom:button wire:click="record('disabled')" confirm disabled data-testid="disabled">Disabled action</atom:button>
    <atom:button wire:click="record('tooltip')" confirm tooltip="Does a thing" data-testid="tooltip">With a tooltip</atom:button>

    <atom:button wire:click="record('delete-handler')" type="delete" data-testid="delete-handler">Delete record</atom:button>
    <atom:button wire:click="record('delete-optout')" type="delete" :confirm="false" data-testid="delete-optout">Delete without asking</atom:button>
    <atom:button type="delete" data-testid="delete-bare">Delete (no handler)</atom:button>
    <atom:button type="delete" confirm="Really delete it?" data-testid="delete-copy">Delete (copy)</atom:button>
    <atom:button type="delete" :confirm="false" data-testid="delete-bare-optout">Delete (no handler, no dialog)</atom:button>

    <form wire:submit="save" class="flex items-center gap-2">
        <input type="text" name="field" data-testid="field" aria-label="Field">
        <atom:button type="submit" confirm="Save the form?" data-testid="submit">Save</atom:button>
    </form>

    <button type="button" wire:click="bump" data-testid="bump">Re-render</button>
</div>
