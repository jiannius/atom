<div x-data class="flex flex-wrap items-center gap-3">
    <atom:button
    x-on:click="atom.confirm({
        heading: 'Transfer ownership?',
        message: 'Re-enter your password to continue.',
        password: true,
    }).then(({ password }) => atom.toast({ message: 'Confirmed.', variant: 'success' })).catch(() => {})">
        Transfer
    </atom:button>
</div>
