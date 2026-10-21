<atom:context-menu locked>
    <div class="flex h-40 items-center justify-center rounded-panel border border-dashed border-control-border text-fg-muted select-none">
        Right-click here &mdash; the menu stays open on item click
    </div>

    <x-slot:menu>
        <atom:menu.item icon="eye">Clicking an item</atom:menu.item>
        <atom:menu.item icon="eye">does not close the menu</atom:menu.item>

        <atom:separator class="my-1"/>

        <atom:menu.item icon="close">Press Esc or click away</atom:menu.item>
    </x-slot:menu>
</atom:context-menu>
