<atom:html title="E2E: Safe URL" :vite="[]" :dark="false" class="min-h-screen bg-white">
{{-- Every client-side navigation sink that takes a host-supplied URL: a table row,
     a command-palette item, a toast and a lightbox download. The hostile URLs set
     window.__pwned, so a click that follows one is visible to the spec. --}}
<div class="p-4">
    {{-- a table row's x-on:click needs an Alpine scope; in a host it is the Livewire root --}}
    <table x-data>
        <tbody>
            <atom:table.row href="javascript:window.__pwned = 1" data-row="hostile"><td>Hostile row</td></atom:table.row>
            <atom:table.row wire:navigate href="javascript:window.__pwned = 1" data-row="hostile-spa"><td>Hostile SPA row</td></atom:table.row>
            <atom:table.row href="#row-legit" data-row="legit"><td>Legit row</td></atom:table.row>
            <atom:table.row wire:navigate href="/atom/e2e/safe-url?spa=1" data-row="legit-spa"><td>Legit SPA row</td></atom:table.row>
        </tbody>
    </table>

    <atom:command name="palette">
        <atom:command.group heading="Links">
            <atom:command.item href="javascript:window.__pwned = 1" data-item="hostile">Hostile item</atom:command.item>
            <atom:command.item href="#item-legit" data-item="legit">Legit item</atom:command.item>
            <atom:command.item href="#item-swapped" data-item="swapped">Swapped item</atom:command.item>
        </atom:command.group>
    </atom:command>

    <atom:toast />

    <div data-lightbox>
        <button
        type="button"
        data-testid="lightbox-open"
        data-lightbox-id="1"
        data-lightbox-url="javascript:window.__pwned = 1"
        data-lightbox-name="Hostile file"
        x-data
        x-on:click="$dispatch('lightbox')">Open lightbox</button>
    </div>

    <atom:lightbox />
</div>

@livewireScripts
</atom:html>
