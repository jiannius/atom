{{-- extra-primary: two submits in one form are two, wherever its sections are; a section outside any form is its own. --}}
<atom:form wire:submit="save">
    <atom:form.section heading="Profile">
        <atom:button type="submit">Save</atom:button>
    </atom:form.section>

    <atom:form.section heading="Billing">
        <atom:button type="submit">Save and bill</atom:button> {{-- expect: extra-primary --}}
    </atom:form.section>
</atom:form>

<atom:form.section heading="Tax">
    <atom:button type="submit" wire:click="a">Save</atom:button>
    <atom:button type="submit" wire:click="b">Save and close</atom:button> {{-- expect: extra-primary --}}
</atom:form.section>
