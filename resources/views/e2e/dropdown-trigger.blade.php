<atom:html title="E2E: Dropdown Trigger" :vite="[]" :dark="false" class="min-h-screen bg-white">
{{-- Plain-Blade page for the dropdown trigger spec (issue #77). Each probe is a dropdown
     whose trigger is found a different way: a link (not a button) with button menu items,
     a plain button, and an explicit data-atom-dropdown-trigger that has a decoy button
     BEFORE it in the root. --}}
<div style="padding: 24px">
    <div data-probe="link" style="margin-bottom: 24px">
        <atom:dropdown>
            <atom:link>Select</atom:link>

            <atom:menu popover>
                <atom:menu.item>Alpha</atom:menu.item>
                <atom:menu.item>Beta</atom:menu.item>
            </atom:menu>
        </atom:dropdown>
    </div>

    <div data-probe="button" style="margin-bottom: 24px">
        <atom:dropdown>
            <atom:button>Pick</atom:button>

            <atom:menu popover>
                <atom:menu.item>Alpha</atom:menu.item>
                <atom:menu.item>Beta</atom:menu.item>
            </atom:menu>
        </atom:dropdown>
    </div>

    <div data-probe="explicit">
        <atom:dropdown>
            <button type="button">Decoy</button>
            <span data-atom-dropdown-trigger>Explicit</span>

            <atom:menu popover>
                <atom:menu.item>Alpha</atom:menu.item>
                <atom:menu.item>Beta</atom:menu.item>
            </atom:menu>
        </atom:dropdown>
    </div>
</div>

{{-- atom.js needs Livewire's bundled Alpine (alpine:init) to start --}}
@livewireScripts
</atom:html>
