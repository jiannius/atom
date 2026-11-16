{{-- Copy and form look-alikes built to the rules: state as a status, a tier as a badge, empty values left empty, sentence case with proper nouns, one titled slide-over, one danger section last. --}}
<atom:page width="narrow">
    <atom:page.header title="Customer details" :facts="[['label' => 'Tier', 'value' => 'Gold'], ['label' => 'Region', 'value' => 'Kuala Lumpur']]">
        <x-slot:status>
            <atom:status :value="$customer->status" />
        </x-slot:status>

        <x-slot:primary>
            <atom:button variant="primary" icon="add">Create customer</atom:button>
        </x-slot:primary>
    </atom:page.header>

    <atom:badge :status="$customer->tier" />
    <atom:badge color="neutral">3</atom:badge>

    <atom:dd.group>
        <atom:dd label="Site">{{ $customer->site }}</atom:dd>
        <atom:dd label="Due" filler="n/a">{{ $customer->due }}</atom:dd>
        <atom:dd label="Tax ID">{{ $customer->tax_id }}</atom:dd>
    </atom:dd.group>

    <p>{{ $customer->status->label() }} and {{ str($customer->name)->title() }}</p>
    <p>{{ $from }}–{{ $to }}</p>

    <atom:button>Pay with Stripe</atom:button>
    <atom:menu.item>Save as template</atom:menu.item>
    <atom:button wire:click="addLine">Add line</atom:button>

    <atom:modal name="edit-tag" slide>
        <atom:form wire:submit="saveTag">
            <atom:page.header title="Edit tag" level="2" size="sm" />

            <atom:input label="Name" required />

            <atom:form.actions sticky>
                <atom:button type="submit">Save</atom:button>
            </atom:form.actions>
        </atom:form>
    </atom:modal>

    <atom:form wire:submit="saveCustomer" marker="optional">
        <atom:form.section heading="Customer">
            <atom:input label="Name" required />
            <atom:input label="Notes" />
        </atom:form.section>

        <atom:form.section heading="Archive" variant="danger">
            <atom:button variant="warning" inverted confirm>Archive customer</atom:button>
        </atom:form.section>
    </atom:form>
</atom:page>
