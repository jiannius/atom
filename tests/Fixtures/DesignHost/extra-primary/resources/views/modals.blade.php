{{-- extra-primary: a modal and a modal form are screens of their own. --}}
<atom:page>
    <atom:page.header title="Tax rates">
        <x-slot:primary>
            <atom:button variant="primary" x-on:click="atom.modal('tax-rate').show()">Create tax rate</atom:button>
        </x-slot:primary>
    </atom:page.header>
</atom:page>

<atom:modal name="tax-rate" slide>
    <atom:form wire:submit="save">
        <atom:input label="Name" wire:model="name" />
        <atom:form.actions sticky>
            <atom:button type="submit">Save</atom:button>
        </atom:form.actions>
    </atom:form>
</atom:modal>

<atom:form.modal name="discount">
    <atom:input label="Name" wire:model="discount.name" />

    <x-slot:actions>
        <atom:button variant="primary">Save and add another</atom:button> {{-- expect: extra-primary --}}
        <atom:button>Reset</atom:button>
    </x-slot:actions>
</atom:form.modal>

{{-- a bare primary in another modal is that modal's, whatever the page holds --}}
<atom:modal name="note">
    <atom:button variant="primary" wire:click="addNote">Add note</atom:button>
</atom:modal>
