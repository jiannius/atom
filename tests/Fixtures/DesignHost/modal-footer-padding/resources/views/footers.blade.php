{{-- modal-footer-padding: a form footer given its own padding. A line marked `expect:` is a finding; every other line must stay clean. --}}
<atom:form wire:submit="save">
    <atom:form.actions class="p-6"> {{-- expect: modal-footer-padding --}}
        <atom:button type="submit">Save</atom:button>
    </atom:form.actions>

    <atom:form.actions class="mt-2 px-4"> {{-- expect: modal-footer-padding --}}
        <atom:button type="submit">Save</atom:button>
    </atom:form.actions>

    <atom:form.actions class="md:pt-2 flex" sticky> {{-- expect: modal-footer-padding --}}
        <atom:button type="submit">Save</atom:button>
    </atom:form.actions>

    <atom:form.actions class="!pb-3" > {{-- expect: modal-footer-padding --}}
        <atom:button type="submit">Save</atom:button>
    </atom:form.actions>

    <atom:form.actions :class="['p-[10px]' => $tight]"> {{-- expect: modal-footer-padding --}}
        <atom:button type="submit">Save</atom:button>
    </atom:form.actions>

    <atom:form.actions sticky>
        <atom:button type="submit">Save</atom:button>
    </atom:form.actions>

    <atom:form.actions bar class="mt-4 gap-2 justify-between">
        <atom:button type="submit">Save</atom:button>
    </atom:form.actions>

    <atom:form.actions class="p-0">
        <atom:button type="submit">Save</atom:button>
    </atom:form.actions>

    <atom:form.actions class="px-{{ $gap }} top-4 grid-cols-2 max-w-md">
        <atom:button type="submit">Save</atom:button>
    </atom:form.actions>

    <atom:form.section class="p-4">
        <atom:input label="Name" />
    </atom:form.section>

    <div class="p-6">
        <atom:button type="submit">Not the footer</atom:button>
    </div>
</atom:form>
