<div class="p-4">
    <atom:button x-on:click="atom.modal('new-customer').slide()" data-testid="open">New customer</atom:button>
    <span data-testid="saves">{{ $saves }}</span>

    <atom:form.modal name="new-customer" cols="1" submit="Save customer">
        <atom:form.section layout="stacked" level="3" heading="Contact" subheading="Who we write to.">
            <atom:input wire:model="name" label="Name" />
            <atom:input label="Email" type="email" />
            <atom:input label="Phone" />
            <atom:input label="Role" />
        </atom:form.section>

        @foreach (['Billing', 'Delivery', 'Preferences', 'Notes'] as $heading)
            <atom:form.section layout="stacked" level="3" :heading="$heading" subheading="A section tall enough to make the slide-over scroll.">
                <atom:input label="{{ $heading }} one" />
                <atom:input label="{{ $heading }} two" />
                <atom:input label="{{ $heading }} three" />
                <atom:input label="{{ $heading }} four" />
            </atom:form.section>
        @endforeach

        <x-slot:actions>
            <span class="text-xs text-fg-subtle" data-testid="actions-slot">5 sections</span>
        </x-slot:actions>
    </atom:form.modal>
</div>
