{{-- Pattern: form page. A long form made of sections (a new invoice), with the save bar pinned to the bottom of the screen.
     Built from atom's components with invented data; copy the structure, not the data.

     The rules this page follows:
     - `<atom:page width="narrow">` (a 980px column) holds `<atom:page.header>` and ONE <atom:form>; the form is a stack of
       `<atom:form.section>`, each a heading, a line of help and its fields. The hairlines between them and the 24px around
       them come from the section and the form's own gap: do not add your own.
     - Two layouts, one prop: `aside` (the default) puts the heading in a 220px column beside the fields from 768px (below that,
       above them); `stacked` always puts it above, for a narrow place. Open this page as ?layout=stacked to see it. Fields are
       two across once their own box is wide enough; `class="@md:col-span-2"` makes one span the row.
     - The save bar is `<atom:form.actions bar>`, the LAST thing in the form. Its `start` slot is live totals (or "Unsaved changes");
       its buttons are Cancel (ghost), a secondary save, and the submit, which is the screen's ONE primary action.
     - Money is tabular and right-aligned, the currency named once in the heading or the column ("Amount (RM)"). Line items are
       EDITABLE: a frameless table whose cells hold inputs (the item, then the quantity and the unit price as right-aligned tabular
       inputs, a tax `<atom:select>` and a ghost remove x), with "Add line" and "Add from products" under it. The amount is read-only.
     - A date is `<atom:date-picker>` and a choice from a short list (payment terms) is `<atom:select>`; a text input is for text.
       "(optional)" at the end of a label is muted by the field itself.
     - Errors: with `<atom:form>` (not `:error-toast="false"`) a failed save shows one summary at the top of the form and the message
       under each field; this page turns the toast off only because it has no component to fail.
     - A section that saves on its own (a settings panel) is its own <atom:form> with a `footer` slot; do not mix that with a page save bar.

     This page has no Livewire component, so the form only prevents the browser's own submit. In a real page the form is
     `<atom:form wire:submit="save">`. --}}
@php
$styles = $styles ?? [];
// a host's own stylesheet when it has one (config atom.docs.vite), so the page is styled inside the host; none in the test rig
$vite = $vite ?? app(\Jiannius\Atom\Services\Docs::class)->vite();
$shell = ($shell ?? 'sidebar') === 'header' ? 'header' : 'sidebar';

$layout = request()->query('layout', 'aside');
$layout = $layout === 'stacked' ? 'stacked' : 'aside';

$lines = [
    ['Base module', 1, 1000.00],
    ['Bookings', 1, 2000.00],
    ['Additional hours', 12, 95.00],
];

$subtotal = array_sum(array_map(fn ($line) => $line[1] * $line[2], $lines));
$tax = round(12 * 95.00 * 0.08, 2);
$money = fn (float $amount) => number_format($amount, 2);
@endphp

<x-dynamic-component :component="'atom::layouts.'.$shell" title="Form page" :vite="$vite" :styles="$styles" :dark="$dark ?? true">
    <x-slot:brand>
        <a href="?page=dashboard" @class([
            'flex items-center gap-[9px] px-2 pb-3 pt-1.5' => $shell === 'sidebar',
            'flex shrink-0 items-center gap-2 text-sm font-semibold tracking-tight' => $shell === 'header',
        ])>
            <div class="flex size-[26px] items-center justify-center rounded-control bg-accent text-accent-foreground">
                <atom:icon.blocks class="size-4"/>
            </div>
            <span class="truncate text-sm font-semibold">Fieldwork</span>
        </a>
    </x-slot>

    <x-slot:nav>
        @include('atom::docs.patterns.nav')
    </x-slot>

    <x-slot:dropdown>
        <atom:menu.item icon="settings">Account settings</atom:menu.item>
        <atom:menu.item icon="logout">Sign out</atom:menu.item>
    </x-slot>

    <atom:page width="narrow" data-testid="form-page">
        <atom:page.header title="New invoice" :crumbs="[['label' => 'Invoices', 'href' => 'list-page'], ['label' => 'New invoice']]" :facts="[['label' => 'Number', 'value' => 'INV-0413', 'mono' => true]]">
            <x-slot:status>
                <atom:status label="Draft · not saved"/>
            </x-slot:status>
        </atom:page.header>

        <atom:form x-data="{ saved: false, issued: '2026-10-15', due: '2026-11-14', terms: 'net-30' }" x-on:submit.prevent="saved = true" :error-toast="false">
            <atom:form.section :layout="$layout" heading="Customer" subheading="Who you are billing. Their tax ID goes on the invoice.">
                <div class="flex items-center gap-3 rounded-lg border bg-surface px-3.5 py-3 @md:col-span-2">
                    <div class="flex size-[34px] shrink-0 items-center justify-center rounded-lg bg-warning-bg text-sm font-semibold text-warning-fg">CA</div>
                    <div class="grid min-w-0 gap-px">
                        <b class="truncate font-medium">Customer A Sdn Bhd</b>
                        <span class="font-id text-fg-subtle">TIN C0000000000</span>
                        <span class="truncate text-xs text-fg-subtle">accounts@customer-a.example</span>
                    </div>
                    <atom:button size="sm" class="ms-auto">Change</atom:button>
                </div>
            </atom:form.section>

            <atom:form.section :layout="$layout" heading="Details" subheading="Dates and terms printed on the invoice.">
                <atom:date-picker label="Issue date" x-model="issued"/>
                <atom:select label="Payment terms" x-model="terms" :options="[
                    ['value' => 'net-14', 'label' => 'Net 14'],
                    ['value' => 'net-30', 'label' => 'Net 30'],
                    ['value' => 'net-60', 'label' => 'Net 60'],
                ]"/>
                <atom:date-picker label="Due date" x-model="due" caption="30 days after the issue date"/>
                <atom:input label="Reference (optional)" value="Quote Q-0231, phase 2"/>
            </atom:form.section>

            <atom:form.section :layout="$layout" heading="Items" subheading="Amounts in Malaysian Ringgit. Service tax is set per line.">
                <div class="min-w-0 @md:col-span-2">
                    <atom:table frameless :empty="false" data-testid="form-lines">
                        <x-slot:columns>
                            <atom:table.column>Item</atom:table.column>
                            <atom:table.column numeric>Qty</atom:table.column>
                            <atom:table.column numeric>Unit price</atom:table.column>
                            <atom:table.column>Tax</atom:table.column>
                            <atom:table.column numeric>Amount (RM)</atom:table.column>
                            <atom:table.column class="w-10"><span class="sr-only">Remove</span></atom:table.column>
                        </x-slot:columns>

                        <x-slot:rows>
                            @foreach ($lines as $index => [$name, $quantity, $price])
                                <atom:table.row>
                                    <atom:table.cell class="min-w-48"><atom:input :value="$name" aria-label="Item"/></atom:table.cell>
                                    <atom:table.cell numeric class="w-24"><atom:input type="number" :value="$quantity" class="text-right tabular-nums" aria-label="Quantity"/></atom:table.cell>
                                    <atom:table.cell numeric class="w-36"><atom:input :value="$money($price)" class="text-right tabular-nums" aria-label="Unit price"/></atom:table.cell>
                                    <atom:table.cell class="w-44" x-data="{ tax: '{{ $index === 2 ? 'sst-8' : 'none' }}' }">
                                        <atom:select aria-label="Tax" x-model="tax" :clearable="false" :options="[
                                            ['value' => 'none', 'label' => 'No tax'],
                                            ['value' => 'sst-8', 'label' => 'Tax 8%'],
                                        ]"/>
                                    </atom:table.cell>
                                    <atom:table.cell numeric class="font-medium">{{ $money($quantity * $price) }}</atom:table.cell>
                                    <atom:table.cell><atom:button variant="ghost" size="sm" icon="close" type="button" aria-label="Remove line"/></atom:table.cell>
                                </atom:table.row>
                            @endforeach
                        </x-slot:rows>
                    </atom:table>

                    <div class="mt-3 flex flex-wrap gap-2">
                        <atom:button size="sm" icon="add" type="button">Add line</atom:button>
                        <atom:button size="sm" icon="add" type="button">Add from products</atom:button>
                    </div>
                </div>
            </atom:form.section>

            <atom:form.section :layout="$layout" heading="Notes" subheading="Shown at the bottom of the invoice.">
                <div class="@md:col-span-2">
                    <atom:textarea label="Note to customer (optional)" rows="3">Phase 2 of 2 as agreed in quote Q-0231. Please quote the invoice number with your transfer.</atom:textarea>
                </div>
            </atom:form.section>

            <atom:form.actions bar>
                <x-slot:start>
                    <span class="text-fg-subtle">Subtotal <b class="ms-1.5 font-medium text-fg">{{ $money($subtotal) }}</b></span>
                    <span class="text-fg-subtle">Service tax <b class="ms-1.5 font-medium text-fg">{{ $money($tax) }}</b></span>
                    <span class="text-[17px] font-semibold tracking-[-0.01em] text-fg" data-testid="grand-total">RM {{ $money($subtotal + $tax) }}</span>
                </x-slot:start>

                <atom:button type="button" variant="ghost">Cancel</atom:button>
                <atom:button type="button">Save draft</atom:button>
                <atom:button type="submit" data-testid="form-primary">Save and send</atom:button>
            </atom:form.actions>
        </atom:form>
    </atom:page>

    @livewireScripts
</x-dynamic-component>
