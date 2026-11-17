{{-- Actions in a card's and a panel's heading row: one visible (a link for an href, else a ghost button), the rest in the ⋯, which is named for the box. --}}
<div class="grid gap-4 sm:grid-cols-2">
    <atom:card heading="Parts used">
        <x-slot:actions>
            <atom:action href="#">View all</atom:action>
            <atom:action icon="download" x-on:click="atom.toast({ message: 'Exported.', variant: 'success' })">Export</atom:action>
            <atom:action priority="low" x-on:click="atom.toast({ message: 'Printing.', variant: 'success' })">Print</atom:action>
            <atom:action tone="warning" x-on:click="atom.toast({ message: 'Archived.', variant: 'success' })">Archive all</atom:action>
        </x-slot:actions>

        <p>3 parts, RM 420.00</p>
    </atom:card>

    <atom:panel heading="Lines" :count="3">
        <x-slot:actions>
            <atom:action icon="plus" x-on:click="atom.toast({ message: 'Line added.', variant: 'success' })">Add line</atom:action>
            <atom:action x-on:click="atom.toast({ message: 'Synced.', variant: 'success' })">Sync</atom:action>
            <atom:action tone="delete" x-on:click="atom.toast({ message: 'Cleared.', variant: 'success' })">Clear lines</atom:action>
        </x-slot:actions>

        <p class="px-row py-3 text-fg-muted">Three lines.</p>
    </atom:panel>
</div>
