{{-- A heading, actions and a menu: the title is 12px muted and the row draws only when there is something to put in it. Actions: one link
     or ghost button. Menu: the items behind the ⋯ (anything beyond the one action goes here). --}}
<div class="grid gap-4 sm:grid-cols-2">
    <atom:card heading="Customer">
        <p>Customer A</p>
        <p class="text-fg-muted">Monthly support plan</p>
    </atom:card>

    <atom:card heading="Parts used">
        <x-slot:actions>
            <atom:link href="#">View all</atom:link>
        </x-slot:actions>

        <x-slot:menu>
            <atom:menu.item icon="download">Export</atom:menu.item>
            <atom:menu.item icon="archive">Archive all</atom:menu.item>
        </x-slot:menu>

        <p>3 parts, RM 420.00</p>
    </atom:card>

    <atom:card heading="Notes" :level="3" divided>
        <div>Called on Monday.</div>
        <div>Quote sent.</div>
    </atom:card>

    <atom:card inset heading="Site photos">
        <x-slot:menu>
            <atom:menu.item>Download all</atom:menu.item>
        </x-slot:menu>

        <div class="h-16 bg-surface-sunken"></div>
    </atom:card>
</div>
