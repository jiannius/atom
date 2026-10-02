<atom:html title="E2E: Table Layout" :vite="[]" :dark="false" class="min-h-screen bg-white">
{{-- Real <atom:table> markup in a fixed 624px box, so the e2e can measure what
     the column/cell classes do to layout (tests/e2e/table-layout.spec.js).

     The rig serves no Tailwind, so every utility the layout rests on would be
     inert here and a broken layout would pass. These are the classes the table
     components carry, written out as Tailwind v4 compiles them. It is a hand-
     maintained mirror, NOT Preflight, apart from the one Preflight rule layout
     depends on (border-box), without which every width below is off by padding.

     The cascade is the point of the first rule: Tailwind compiles
     `[:where(&)]:whitespace-nowrap` to `:where(.cls) { ... }`, specificity 0, so a
     plain `.whitespace-normal` from the caller beats it. --}}
<style>
    *, ::before, ::after { box-sizing: border-box; }
    body { margin: 0; font: 14px/20px sans-serif; }
    table { border-collapse: collapse; text-indent: 0; border-color: inherit; }
    :where(.\[\:where\(\&\)\]\:whitespace-nowrap) { white-space: nowrap; }
    .whitespace-normal { white-space: normal; }
    /* Tailwind sorts same-property utilities by name, so a plain whitespace-nowrap
       lands after whitespace-normal and wins the tie: the bug the :where() form fixes. */
    .whitespace-nowrap { white-space: nowrap; }
    .relative { position: relative; }
    .overflow-hidden { overflow: hidden; }
    .overflow-x-auto { overflow-x: auto; }
    .rounded-lg { border-radius: 0.5rem; }
    .border { border: 1px solid #e4e4e7; }
    .min-w-full { min-width: 100%; }
    .py-3 { padding-block: 0.75rem; }
    .px-4 { padding-inline: 1rem; }
    .p-1 { padding: 0.25rem; }
    .py-1\.5 { padding-block: 0.375rem; }
    .px-3 { padding-inline: 0.75rem; }
    .inline-flex { display: inline-flex; }
    .items-center { align-items: center; }
    .gap-2 { gap: 0.5rem; }
    .grow { flex-grow: 1; }
    .shrink-0 { flex-shrink: 0; }
    .flex { display: flex; }
    .justify-center { justify-content: center; }
    .w-10 { width: 2.5rem; }
    .w-full { width: 100%; }
    .max-w-0 { max-width: 0; }
    .truncate { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .text-left { text-align: left; }
    .uppercase { text-transform: uppercase; }
    .size-5 { width: 1.25rem; height: 1.25rem; }
    .size-3 { width: 0.75rem; height: 0.75rem; }
    .rounded-md { border-radius: 0.375rem; }
    .space-y-4 > * + * { margin-top: 1rem; }
</style>

<div class="box" id="box-checkbox" style="width: 624px; margin: 16px">
    <atom:table :empty="false">
        <x-slot:columns>
            <atom:table.column checkbox />
            <atom:table.column>Name</atom:table.column>
            <atom:table.column>Email</atom:table.column>
        </x-slot:columns>
        <x-slot:rows>
            <atom:table.row>
                <atom:table.cell :checkbox="1" />
                <atom:table.cell>Jane Tan</atom:table.cell>
                <atom:table.cell>jane@example.com</atom:table.cell>
            </atom:table.row>
        </x-slot:rows>
    </atom:table>
</div>

{{-- A 300px box: too narrow for the long text on one line, so a cell or header
     that is allowed to wrap shows it as a taller box. --}}
<div id="box-wrap" style="width: 300px; margin: 16px">
    <atom:table :empty="false">
        <x-slot:columns>
            <atom:table.column id="head-nowrap">Contact person in charge of the account</atom:table.column>
            <atom:table.column id="head-wrap" class="whitespace-normal">Contact person in charge of the account</atom:table.column>
        </x-slot:columns>
        <x-slot:rows>
            <atom:table.row>
                <atom:table.cell id="cell-nowrap">Level 12, Menara Example, Jalan Sultan Ismail, Kuala Lumpur</atom:table.cell>
                <atom:table.cell id="cell-wrap" class="whitespace-normal">Level 12, Menara Example, Jalan Sultan Ismail, Kuala Lumpur</atom:table.cell>
            </atom:table.row>
        </x-slot:rows>
    </atom:table>
</div>
</atom:html>
