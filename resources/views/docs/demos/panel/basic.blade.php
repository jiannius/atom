{{-- A panel is the box a list lives in: edge to edge, clipped, never `overflow-hidden`. The heading row is drawn only with a heading,
     actions or a menu; `count` is printed as given; `padded` pads the body for text. --}}
<div class="grid gap-4 sm:grid-cols-2">
    <atom:panel heading="Invoices" :count="151">
        <x-slot:actions>
            <atom:link href="#">View all</atom:link>
        </x-slot:actions>

        <x-slot:menu>
            <atom:menu.item icon="download">Export</atom:menu.item>
        </x-slot:menu>

        <atom:table.views label="Invoice views" current="unpaid" :counts="['all' => 151, 'unpaid' => 12]">
            <atom:table.views.item value="all" label="All"/>
            <atom:table.views.item value="unpaid" label="Unpaid"/>
        </atom:table.views>
    </atom:panel>

    <atom:panel heading="Notes" padded>
        <p class="text-fg-muted">Text goes in a padded panel: the body is padded the same 12px as a table cell.</p>
    </atom:panel>
</div>
