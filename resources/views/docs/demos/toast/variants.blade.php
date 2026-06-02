<div x-data class="flex flex-wrap items-center gap-3">
    <atom:button x-on:click="atom.toast({ message: 'Saved successfully.', variant: 'success' })">Success</atom:button>
    <atom:button x-on:click="atom.toast({ message: 'Check your input.', variant: 'warning' })">Warning</atom:button>
    <atom:button x-on:click="atom.toast({ message: 'Something went wrong.', variant: 'danger' })">Danger</atom:button>
</div>
