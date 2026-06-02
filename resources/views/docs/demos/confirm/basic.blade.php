<div x-data class="flex flex-wrap items-center gap-3">
    <atom:button
    variant="danger"
    x-on:click="atom.confirm({
        variant: 'danger',
        heading: 'Delete customer?',
        message: 'This cannot be undone.',
    }).then(() => atom.toast({ message: 'Confirmed.', variant: 'success' })).catch(() => {})">
        Delete
    </atom:button>
</div>
