<div x-data class="flex flex-wrap items-center gap-3">
    <atom:button x-on:click="atom.toast({ message: 'Bottom (default).' })">Bottom</atom:button>
    <atom:button x-on:click="atom.toast({ message: 'Center.', position: 'center' })">Center</atom:button>
</div>
