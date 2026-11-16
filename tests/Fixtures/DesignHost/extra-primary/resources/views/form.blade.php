{{-- extra-primary: a second primary in one form. --}}
<atom:page width="narrow">
    <atom:page.header title="Edit job" />

    <atom:form wire:submit="save">
        <atom:input label="Name" wire:model="name" />

        <atom:form.actions bar>
            <atom:button type="submit">Save</atom:button>
            <atom:button type="submit"> {{-- expect: extra-primary --}}
                Save and send
            </atom:button>
            <atom:button variant="primary">Save and close</atom:button> {{-- expect: extra-primary --}}
            <atom:button type="submit" variant="default">Save as draft</atom:button>
            <atom:button variant="ghost" href="/jobs">Cancel</atom:button>
        </atom:form.actions>
    </atom:form>

    {{-- a second form is a second submit: it is the one primary of its own form, but the screen has two --}}
    <atom:form wire:submit="comment">
        <atom:textarea wire:model="body" />
        <atom:button type="submit">Post comment</atom:button> {{-- expect: extra-primary --}}
    </atom:form>
</atom:page>
