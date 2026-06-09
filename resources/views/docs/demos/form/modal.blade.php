<div x-data>
    <atom:button x-on:click="atom.modal('demo-form-modal').show()">Edit contact</atom:button>

    <atom:form.modal name="demo-form-modal">
        <atom:input label="First name"/>
        <atom:input label="Last name"/>
        <atom:input type="email" label="Email"/>
        <atom:input label="Phone"/>

        <x-slot:delete>
            <atom:button type="delete" variant="ghost" color="danger" x-on:click.prevent>Delete</atom:button>
        </x-slot:delete>
    </atom:form.modal>
</div>
