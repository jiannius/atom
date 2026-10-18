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
     - Money is tabular and right-aligned, the currency named once in the heading or the column ("Amount (RM)").
     - A section that saves on its own (a settings panel) is its own <atom:form> with a `footer` slot; do not mix that with a page save bar.

     This page has no Livewire component, so the form only prevents the browser's own submit. In a real page the form is
     `<atom:form wire:submit="save">`. --}}
@php
$styles = $styles ?? [];
$shell = ($shell ?? 'sidebar') === 'header' ? 'header' : 'sidebar';

$layout = request()->query('layout', 'aside');
$layout = $layout === 'stacked' ? 'stacked' : 'aside';

$lines = [
    ['Base module', 'Users, roles, branches and an audit trail', 1, 1000.00],
    ['Bookings', 'Appointments and bay scheduling', 1, 2000.00],
    ['Additional hours', 'Data migration from the old system', 12, 95.00],
];

$subtotal = array_sum(array_map(fn ($line) => $line[2] * $line[3], $lines));
$tax = round(12 * 95.00 * 0.08, 2);
$money = fn (float $amount) => number_format($amount, 2);
@endphp

<x-dynamic-component :component="'atom::layouts.'.$shell" title="Form page" :vite="[]" :styles="$styles" :dark="$dark ?? true">
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

        <atom:form x-data="{ saved: false }" x-on:submit.prevent="saved = true" :error-toast="false">
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
                <atom:input label="Issue date" value="15 Oct 2026"/>
                <atom:input label="Payment terms" value="Net 30"/>
                <atom:input label="Due date" value="14 Nov 2026" caption="30 days after the issue date"/>
                <atom:input label="Reference (optional)" value="Quote Q-0231, phase 2"/>
            </atom:form.section>

            <atom:form.section :layout="$layout" heading="Items" subheading="Amounts in Malaysian Ringgit. Service tax is set per line.">
                <div class="min-w-0 @md:col-span-2">
                    <atom:table frameless :empty="false">
                        <x-slot:columns>
                            <atom:table.column>Item</atom:table.column>
                            <atom:table.column numeric>Qty</atom:table.column>
                            <atom:table.column numeric>Unit price</atom:table.column>
                            <atom:table.column numeric>Amount (RM)</atom:table.column>
                        </x-slot:columns>

                        <x-slot:rows>
                            @foreach ($lines as [$name, $note, $quantity, $price])
                                <atom:table.row>
                                    <atom:table.cell>
                                        <div class="font-medium">{{ $name }}</div>
                                        <div class="mt-0.5 text-xs text-fg-subtle">{{ $note }}</div>
                                    </atom:table.cell>
                                    <atom:table.cell numeric>{{ $quantity }}</atom:table.cell>
                                    <atom:table.cell numeric>{{ $money($price) }}</atom:table.cell>
                                    <atom:table.cell numeric class="font-medium">{{ $money($quantity * $price) }}</atom:table.cell>
                                </atom:table.row>
                            @endforeach
                        </x-slot:rows>
                    </atom:table>

                    <div class="mt-3 flex gap-2">
                        <atom:button size="sm" icon="add" type="button">Add line</atom:button>
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
