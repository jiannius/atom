{{-- extra-primary: a primary action beside a `primary` slot the host fills is drawn as a plain action. --}}
<atom:page>
    <atom:page.header title="A primary action beside the host's own primary slot">
        <x-slot:actions>
            <atom:action priority="primary" wire:click="approve">Approve</atom:action> {{-- expect: extra-primary --}}
        </x-slot:actions>

        <x-slot:primary>
            <atom:button variant="primary">Send</atom:button>
        </x-slot:primary>
    </atom:page.header>
</atom:page>
