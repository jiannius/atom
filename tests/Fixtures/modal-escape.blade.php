<div class="p-4">
    <atom:modal.trigger name="esc">
        <button type="button" data-open-modal>Open modal</button>
    </atom:modal.trigger>

    <atom:modal name="esc" :escapable="$escapable" data-modal="esc">
        <p>A modal</p>

        <button type="button" data-plain>Plain</button>

        {{-- each takes the focus with it when the key that activates it is pressed --}}
        <button type="button" x-data x-on:click="$el.disabled = true" data-disable>Disable me</button>
        <button type="button" x-data x-on:click="$el.hidden = true" data-hide>Hide me</button>

        {{-- a Livewire re-render that removes the button the focus is on --}}
        @if ($loaded)
            <p data-loaded>Loaded</p>
        @else
            <button type="button" wire:click="load" data-load>Load</button>
        @endif

        <div data-select-wrap><atom:select variant="listbox" :options="[['value' => 'a', 'label' => 'Draft'], ['value' => 'b', 'label' => 'Sent']]" /></div>

        <atom:dropdown>
            <button type="button" data-open-dropdown>Open dropdown</button>

            <atom:menu popover>
                <atom:menu.item href="#one">Item one</atom:menu.item>
            </atom:menu>
        </atom:dropdown>

        {{-- what a press of Escape has to close before the modal (#177) --}}
        <atom:context-menu>
            <div data-context-area>Right-click here</div>

            <x-slot:menu>
                <atom:menu.item>Context one</atom:menu.item>
            </x-slot:menu>
        </atom:context-menu>

        <atom:tooltip content="A tip" data-tip>
            <button type="button" data-tip-trigger>Tip</button>
        </atom:tooltip>

        <form method="dialog"><button data-submit-dialog>Submit</button></form>
    </atom:modal>

    <atom:command name="palette" shortcut="" data-palette>
        <atom:dropdown>
            <button type="button" data-open-palette-dropdown>Open dropdown</button>

            <atom:menu popover>
                <atom:menu.item href="#palette">Palette item</atom:menu.item>
            </atom:menu>
        </atom:dropdown>
    </atom:command>

    <atom:modal name="outer" :escapable="$outerEscapable" data-modal="outer">
        <p>Outer</p>
        <button type="button" x-data x-on:click="atom.modal('inner').show()" data-open-other>Open inner</button>
    </atom:modal>

    <atom:modal name="inner" :escapable="$innerEscapable" data-modal="inner">
        <p>Inner</p>
        <button type="button" x-data x-on:click="atom.modal('outer').show()" data-open-other>Open outer</button>
    </atom:modal>
</div>
