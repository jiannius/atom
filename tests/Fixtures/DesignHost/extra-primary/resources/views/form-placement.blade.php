{{-- extra-primary: a primary flagged for where it sits is not the form's one, so the form's submit is not a second. --}}
<atom:form wire:submit="save">
    <atom:table.toolbar>
        <x-slot:actions>
            <atom:button variant="primary">Export</atom:button> {{-- expect: extra-primary --}}
        </x-slot:actions>
    </atom:table.toolbar>

    <atom:button type="submit">Save</atom:button>
</atom:form>
