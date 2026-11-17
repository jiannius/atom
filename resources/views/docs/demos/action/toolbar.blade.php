{{-- Shown outside a table, so it takes `strip`. In a Livewire page it goes in the table's header slot instead (no `strip`):
     <x-slot:header><atom:table.toolbar> ... </atom:table.toolbar></x-slot:header>. The table's bulk bar reads `checked` and `checked-menu` the same way:
     <x-slot:checked><atom:action wire:click="assign">Assign</atom:action><atom:action tone="delete">Delete</atom:action></x-slot:checked> draws the buttons
     and keeps the delete visible, last, asking with the count; it cannot open without Livewire, so see the "Checked" demo of the table for its look. --}}
<atom:panel>
    <atom:table.toolbar strip>
        <atom:table.search placeholder="Search number or customer"/>

        <x-slot:actions>
            <atom:action icon="download" x-on:click="atom.toast({ message: 'Exported.', variant: 'success' })">Export</atom:action>
            <atom:action icon="upload" x-on:click="atom.toast({ message: 'Imported.', variant: 'success' })">Import</atom:action>
            <atom:action x-on:click="atom.toast({ message: 'Archived.', variant: 'success' })">Archive old</atom:action>
        </x-slot:actions>

        <x-slot:menu>
            <atom:action icon="copy" priority="low" x-on:click="atom.toast({ message: 'Copied.', variant: 'success' })">Copy list</atom:action>
            <atom:action tone="warning" x-on:click="atom.toast({ message: 'Voided.', variant: 'success' })">Void all</atom:action>
        </x-slot:menu>
    </atom:table.toolbar>
</atom:panel>
