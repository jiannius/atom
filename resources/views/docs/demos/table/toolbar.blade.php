{{-- Shown outside a table, so it takes `strip`. In a Livewire page it goes in the table's header slot instead (no `strip`):
     <x-slot:header><atom:table.toolbar> ... </atom:table.toolbar></x-slot:header> --}}
<div class="overflow-hidden rounded-panel border bg-surface">
    <atom:table.toolbar strip>
        <atom:table.search placeholder="Search number or customer"/>

        <x-slot:end>
            <atom:button size="sm" variant="ghost" icon="download">Export</atom:button>
        </x-slot:end>
    </atom:table.toolbar>
</div>
