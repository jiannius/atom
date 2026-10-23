{{-- Pattern: slide-over form. A create or edit form that opens over a list, so the list stays in view: stacked sections in a
     slide-over, with the save bar pinned at its foot. Built from atom's components with invented data; copy the structure,
     not the data.

     The rules this page follows:
     - A form is a slide-over (`atom.modal('name').slide()`), a whole record is a page: never put a record page in one.
     - Inside, the heading is `<atom:page.header level="2" size="sm">`, the sections are `<atom:form.section layout="stacked">`
       (the label above its fields: a slide-over is too narrow for the aside layout) and the foot is the pinned save bar. The slide-over
       scrolls, the bar does not.
     - `<atom:form.modal cols="1">` is this composition: a modal, a form, a one-column grid and the pinned bar, with the
       `actions` slot (live totals, a count, "Unsaved changes") beside the submit. Its width comes from `cols` (`cols="1"` is
       `max-w-xl`, 576px, and a width class of your own does not beat it), so its fields are two across. This page is the 448px
       slide-over, spelled out with a plain modal and form (which also lets it run without a Livewire component): there the
       fields are one column. In a real page write `<atom:form.modal name="new-customer" cols="1">` with the sections inside; the
       modal's own form calls the component's `submit()` method (there is no `wire:submit` to pass). For the 448px width, use
       the plain composition shown here.
     - The pinned bar is the page save bar's shape: the status text ("Unsaved changes", "Saved") at the start (the `start` slot) and the
       submit at the end. The submit is the one primary action of the slide-over; there is no Cancel button, the close button and
       Escape do that.

     Open it as ?open=1 to have the slide-over open on load. --}}
@php
$styles = $styles ?? [];
$shell = ($shell ?? 'sidebar') === 'header' ? 'header' : 'sidebar';

$customers = [
    ['Customer A', 'accounts@customer-a.example', 'Klang', 'Active'],
    ['Customer B', 'hello@customer-b.example', 'Shah Alam', 'Active'],
    ['Customer C', 'office@customer-c.example', 'Penang', 'Paused'],
    ['Customer D', 'finance@customer-d.example', 'Johor Bahru', 'Active'],
];

$open = request()->query('open') === '1';
@endphp

<x-dynamic-component :component="'atom::layouts.'.$shell" title="Slide-over form" :vite="[]" :styles="$styles" :dark="$dark ?? true">
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

    <atom:page data-testid="slide-over-page">
        <atom:page.header title="Customers" :facts="[['value' => '4 customers']]">
            <x-slot:primary>
                <atom:button variant="primary" icon="add" x-on:click="atom.modal('new-customer').slide()" data-testid="open-slide-over">New customer</atom:button>
            </x-slot:primary>
        </atom:page.header>

        <atom:table :empty="false">
            <x-slot:columns>
                <atom:table.column>Customer</atom:table.column>
                <atom:table.column>City</atom:table.column>
                <atom:table.column>Status</atom:table.column>
            </x-slot:columns>

            <x-slot:rows>
                @foreach ($customers as [$name, $email, $city, $state])
                    <atom:table.row>
                        <atom:table.cell>
                            <div class="font-medium">{{ $name }}</div>
                            <div class="mt-0.5 text-xs text-fg-subtle">{{ $email }}</div>
                        </atom:table.cell>
                        <atom:table.cell :muted="true">{{ $city }}</atom:table.cell>
                        <atom:table.cell><atom:status :tone="$state === 'Active' ? 'success' : 'neutral'" :label="$state"/></atom:table.cell>
                    </atom:table.row>
                @endforeach
            </x-slot:rows>
        </atom:table>

        <atom:modal name="new-customer" class="w-[28rem]" data-testid="slide-over">
            <atom:form x-data="{ saved: false, changed: false }" x-on:submit.prevent="saved = true" x-on:input="changed = true" :error-toast="false">
                <atom:page.header title="New customer" level="2" size="sm"/>

                {{-- the sections in a box of their own, so the first one is a first child (no hairline under the title) --}}
                <div class="grid gap-6">
                <atom:form.section layout="stacked" level="3" heading="Contact" subheading="Who we write to about invoices.">
                    <atom:input label="Name"/>
                    <atom:input label="Email" type="email"/>
                    <atom:input label="Phone"/>
                    <atom:input label="Role"/>
                </atom:form.section>

                <atom:form.section layout="stacked" level="3" heading="Billing" subheading="Printed on every invoice.">
                    <atom:input label="Company"/>
                    <atom:input label="Tax ID"/>
                    <div class="@md:col-span-2"><atom:input label="Address"/></div>
                    <atom:input label="City"/>
                    <atom:input label="Postcode"/>
                </atom:form.section>

                <atom:form.section layout="stacked" level="3" heading="Notes" subheading="Only your team sees these.">
                    <div class="@md:col-span-2"><atom:textarea label="Notes" rows="3"/></div>
                </atom:form.section>

                </div>

                <atom:form.actions sticky>
                    <x-slot:start>
                        <span class="text-xs text-fg-subtle" x-show="changed && !saved" x-cloak>Unsaved changes</span>
                        <span class="text-xs text-success-fg" x-show="saved" x-cloak data-testid="slide-over-saved">Saved</span>
                    </x-slot:start>

                    <atom:button type="submit" data-testid="slide-over-submit">Save customer</atom:button>
                </atom:form.actions>
            </atom:form>
        </atom:modal>

        @if ($open)
            <div x-data x-init="$nextTick(() => atom.modal('new-customer').slide())"></div>
        @endif
    </atom:page>

    @livewireScripts
</x-dynamic-component>
