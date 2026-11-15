<div x-data class="flex flex-wrap items-center gap-3">
    <atom:button variant="warning" inverted confirm x-on:click="atom.toast({ message: 'Archived.', variant: 'success' })">Archive client</atom:button>
    <atom:button variant="warning" inverted :confirm="['heading' => 'Archive this client?', 'message' => 'It leaves every list; its records stay.', 'buttonConfirm' => 'Archive client']" x-on:click="atom.toast({ message: 'Archived.', variant: 'success' })">Archive with a message</atom:button>
    <atom:button type="delete" x-on:click="atom.toast({ message: 'Deleted.', variant: 'success' })">Delete with its own click</atom:button>
    <atom:button type="delete" :confirm="false" x-on:click="atom.toast({ message: 'Deleted, no question.', variant: 'success' })">Delete, no question</atom:button>
</div>
