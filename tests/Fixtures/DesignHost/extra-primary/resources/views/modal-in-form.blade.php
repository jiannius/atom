{{-- extra-primary: a modal inside a form is a screen of its own: its primary is not a second one in the form. --}}
<atom:page>
    <atom:page.header title="Job" />

    <atom:form wire:submit="save">
        <atom:button type="submit">Save</atom:button>

        <atom:modal name="confirm-save">
            <atom:button variant="primary">Yes, save</atom:button>
        </atom:modal>
    </atom:form>
</atom:page>
