{{-- A slide-over form built to the design rules: a small header, the form, the sticky actions. The page behind it has its own primary. --}}
<atom:page>
    <atom:page.header title="Tax rates">
        <x-slot:primary>
            <atom:button variant="primary" x-on:click="$dispatch('atom-modal-show', 'tax-rate')">Create tax rate</atom:button>
        </x-slot:primary>
    </atom:page.header>
</atom:page>

<atom:modal name="tax-rate" slide>
    <atom:form wire:submit="save">
        <atom:page.header title="Create tax rate" level="2" size="sm" />

        <atom:input label="Name" wire:model="form.name" required />

        <atom:form.actions sticky>
            <atom:button type="submit">Save</atom:button>
            <atom:button variant="ghost" x-on:click="$dispatch('atom-modal-close')">Cancel</atom:button>
        </atom:form.actions>
    </atom:form>
</atom:modal>
