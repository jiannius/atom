@php
// ?tw=floor or ?tw=latest: the consumer's Tailwind, for the checks that need the wrapper's utilities.
$tw = in_array(request()->query('tw'), ['floor', 'latest'], true) ? request()->query('tw') : null;
@endphp

<atom:html title="E2E: Button tooltip" :vite="[]" :dark="false" :styles="$tw ? ['/atom/e2e/consumer-css/'.$tw] : []">
{{-- Plain-Blade page for the `tooltip` prop of <atom:button> (button-tooltip.spec.js). Each probe is one case: an icon-only
     button, a labelled one that already carries a description, a dropdown trigger, a button whose click toggles a `show` owned
     by an outer x-data, and a long block label. --}}
<div class="flex flex-col gap-6 p-6">
    <div data-probe="icon" style="margin-bottom: 24px">
        <atom:button icon="add" tooltip="Add a contact" data-testid="icon"/>
    </div>

    {{-- a hand-wrapped button named with the tooltip's own words, as <atom:table.trashed> is --}}
    <div data-probe="wrapped" style="margin-bottom: 24px">
        <atom:tooltip content="Show archived">
            <atom:button icon="archive" aria-label="Show archived" data-testid="wrapped"/>
        </atom:tooltip>
    </div>

    <div data-probe="described" style="margin-bottom: 24px">
        <span id="described-hint" hidden>Opens the report</span>
        <atom:button tooltip="Export as CSV" aria-describedby="described-hint" data-testid="described">Export</atom:button>
    </div>

    <div data-probe="menu" style="margin-bottom: 24px">
        <atom:dropdown>
            <atom:button icon="more" tooltip="More actions" data-testid="menu"/>

            <atom:menu popover>
                <atom:menu.item>Alpha</atom:menu.item>
                <atom:menu.item>Beta</atom:menu.item>
            </atom:menu>
        </atom:dropdown>
    </div>

    <div data-probe="scope" x-data="{ show: false }" style="margin-bottom: 24px">
        <atom:button tooltip="Toggle the panel" x-on:click="show = !show" data-testid="scope">Toggle</atom:button>
        <span data-testid="scope-value" x-text="show"></span>
    </div>

    <div data-probe="long" class="w-[228px] border border-dashed p-0" data-testid="column">
        <atom:button block variant="ghost" icon-suffix="dropdown" tooltip="Switch workspace" data-testid="long">Northwind Trading Company International Sdn. Bhd.</atom:button>
    </div>
</div>
@livewireScripts
</atom:html>
