<div x-data>
    <atom:menu class="w-56">
        <atom:menu.item icon="settings" x-on:click="atom.toast({ message: 'Opened.' })">Settings</atom:menu.item>

        <atom:menu.separator/>

        <atom:menu.item icon="warning" variant="warning" confirm="Cancel this job?" x-on:click="atom.toast({ message: 'Cancelled.', variant: 'success' })">Cancel job</atom:menu.item>
        <atom:menu.item variant="delete" x-on:click="atom.toast({ message: 'Deleted.', variant: 'success' })">Delete job</atom:menu.item>
    </atom:menu>
</div>
