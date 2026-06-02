<div x-data class="flex flex-wrap items-center gap-3">
    <atom:button
    x-on:click="atom.alert({
        heading: 'Heads up',
        message: 'Your subscription expires tomorrow.',
        variant: 'warning',
        button: 'Got it',
    }).then(() => atom.toast({ message: 'Dismissed.' }))">
        Show alert
    </atom:button>
</div>
