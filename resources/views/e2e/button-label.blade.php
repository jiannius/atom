@php
// ?tw=floor or ?tw=latest: the consumer's Tailwind. A 228px column is the sidebar's context slot.
$tw = in_array(request()->query('tw'), ['floor', 'latest'], true) ? request()->query('tw') : null;
@endphp

<atom:html title="E2E: Button label" :vite="[]" :dark="false" :styles="$tw ? ['/atom/e2e/consumer-css/'.$tw] : []">
<div class="flex flex-col gap-3 p-4">
    <div class="w-[228px] border border-dashed p-0" data-testid="column">
        {{-- the long label of a workspace switcher: truncates, the suffix icon stays in view --}}
        <atom:button block variant="ghost" icon-suffix="dropdown" data-testid="long">Northwind Trading Company International Sdn. Bhd.</atom:button>
    </div>

    <div class="w-[228px] border border-dashed p-0" data-testid="column-between">
        <atom:button block variant="ghost" icon-suffix="dropdown" class="justify-between!" data-testid="between">Northwind Trading</atom:button>
    </div>

    <div class="w-[228px] border border-dashed p-0" data-testid="column-between-long">
        <atom:button block variant="ghost" icon-suffix="dropdown" class="justify-between!" data-testid="between-long">Northwind Trading Company International Sdn. Bhd.</atom:button>
    </div>

    <div class="w-[228px] border border-dashed p-0" data-testid="column-short">
        <atom:button block variant="ghost" icon-suffix="dropdown" data-testid="short">Acme</atom:button>
    </div>

    {{-- a non-block button in a narrow column shrinks to it --}}
    <div class="w-[228px] border border-dashed p-0" data-testid="column-inline">
        <atom:button icon-suffix="dropdown" data-testid="inline-long">Northwind Trading Company International Sdn. Bhd.</atom:button>
    </div>

    <div class="w-[228px] border border-dashed p-0" data-testid="column-inline-short">
        <atom:button data-testid="inline-short">Save</atom:button>
    </div>

    {{-- a link button underlines its label on hover: the label's overflow clip must leave room for it, at every size --}}
    <div class="flex flex-col items-start gap-3 p-2" data-testid="links">
        <atom:button variant="link" size="xs" data-testid="link-xs">Open the record</atom:button>
        <atom:button variant="link" size="sm" data-testid="link-sm">Open the record</atom:button>
        <atom:button variant="link" data-testid="link-base">Open the record</atom:button>
        <atom:button variant="link" size="lg" data-testid="link-lg">Open the record</atom:button>
        <atom:button size="xs" data-testid="plain-xs">Open the record</atom:button>
        <atom:button size="sm" data-testid="plain-sm">Open the record</atom:button>
        <atom:button data-testid="plain-base">Open the record</atom:button>
        <atom:button size="lg" data-testid="plain-lg">Open the record</atom:button>
    </div>

    {{-- shapes the wrapper must not change --}}
    <div class="flex w-[228px] items-center gap-2 border border-dashed p-0" data-testid="shapes">
        <atom:button icon="add" data-testid="icon-only"/>
        <atom:button icon="add" data-testid="icon-label">New</atom:button>
        <atom:button data-testid="rich"><atom:badge>3</atom:badge> Inbox</atom:button>
    </div>
</div>
@livewireScripts
</atom:html>
