{{-- Shown outside a table, so it takes `strip`. In a Livewire page it goes in the table's header slot instead (no `strip`):
     <x-slot:header><atom:table.toolbar> ... </atom:table.toolbar></x-slot:header> --}}
<div class="overflow-hidden rounded-panel border bg-surface">
    <atom:table.toolbar strip>
        <atom:table.search placeholder="Search number or customer"/>

        <x-slot:actions>
            <atom:button variant="ghost" icon="download">Export</atom:button>
        </x-slot:actions>

        <x-slot:menu>
            <atom:menu.item icon="archive">Archive all</atom:menu.item>
            <atom:menu.item icon="upload">Import</atom:menu.item>
        </x-slot:menu>
    </atom:table.toolbar>
</div>
