{{-- A form page built to the design rules: sections, one submit, the actions bar. --}}
<atom:page width="narrow">
    <atom:page.header title="Edit invoice" />

    <atom:form wire:submit="save" marker="optional">
        <atom:form.section heading="Customer">
            <atom:form.grid>
                <atom:input label="Reference" wire:model="form.reference" required />
                <atom:input label="Notes" wire:model="form.notes" />
            </atom:form.grid>
        </atom:form.section>

        <atom:form.actions bar>
            <atom:button type="submit">Save</atom:button>
            <atom:button variant="ghost" href="{{ route('invoices.index') }}">Cancel</atom:button>
        </atom:form.actions>
    </atom:form>
</atom:page>
