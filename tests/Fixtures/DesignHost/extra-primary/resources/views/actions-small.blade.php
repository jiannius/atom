{{-- extra-primary: a `size="sm"` header has no primary. --}}
<atom:modal name="job">
    <atom:page.header title="Job" size="sm" level="2">
        <x-slot:actions>
            <atom:action priority="primary" wire:click="approve">Approve</atom:action> {{-- expect: extra-primary --}}
        </x-slot:actions>
    </atom:page.header>
</atom:modal>
