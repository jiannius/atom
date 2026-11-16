{{-- extra-primary: a submit that names its form with `form="id"` belongs to that form: it pairs only with another for the same id. --}}
<atom:page>
    <atom:page.header title="Edit">
        <x-slot:actions>
            <atom:button type="submit" form="profile">Save</atom:button>
            <atom:button type="submit" form="billing">Save billing</atom:button>
            <atom:button type="submit" :form="$id">Save, bound</atom:button>
            <atom:button type="submit" :form="$other">Save other, bound</atom:button>
            <atom:button type="submit" form="profile"> {{-- expect: extra-primary --}}
                Save and close
            </atom:button>
        </x-slot:actions>
    </atom:page.header>

    <atom:form id="profile" wire:submit="save"><atom:input wire:model="name" /></atom:form>
    <atom:form id="billing" wire:submit="bill"><atom:input wire:model="plan" /></atom:form>
</atom:page>
