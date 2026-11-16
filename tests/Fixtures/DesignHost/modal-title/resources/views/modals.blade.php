{{-- modal-title: a modal form titled by a hand heading. A line marked `expect:` is a finding; every other line must stay clean. --}}
<atom:modal name="edit-tax">
    <atom:heading size="xl">Edit tax</atom:heading> {{-- expect: modal-title --}}
    <atom:form wire:submit="save">
        <atom:input label="Name" />
    </atom:form>
</atom:modal>

<atom:modal name="edit-tag" slide>
    <atom:form wire:submit="save">
        <h3 class="mb-4">Edit tag</h3> {{-- expect: modal-title --}}
        <atom:input label="Name" />
    </atom:form>
</atom:modal>

<atom:modal name="edit-term" slide>
    <atom:form wire:submit="save">
        <atom:page.header title="Edit payment term" level="2" size="sm" />
        <atom:heading level="3">Terms</atom:heading>
        <atom:input label="Name" />
    </atom:form>
</atom:modal>

<atom:modal name="confirm-delete">
    <atom:heading size="lg">Delete this?</atom:heading>
    <atom:button wire:click="delete">Delete</atom:button>
</atom:modal>

<atom:modal name="preview">
    <h2>Preview</h2>
    <div>No form here</div>
</atom:modal>

<atom:modal name="untitled">
    <atom:form wire:submit="save">
        <atom:input label="Name" />
    </atom:form>
</atom:modal>

<atom:modal name="outer">
    <atom:page.header title="Outer" level="2" size="sm" />
    <atom:modal name="inner">
        <atom:heading>Inner</atom:heading>
    </atom:modal>
    <atom:form wire:submit="save">
        <atom:input label="Name" />
    </atom:form>
</atom:modal>

<atom:modal name="around">
    <atom:modal name="within">
        <atom:heading>Inside</atom:heading>
    </atom:modal>
    <atom:form wire:submit="save">
        <atom:input label="Name" />
    </atom:form>
</atom:modal>

<atom:modal name="warning">
    <atom:heading>Warning</atom:heading>
    <atom:modal name="below">
        <atom:form wire:submit="save">
            <atom:page.header title="Below" level="2" size="sm" />
        </atom:form>
    </atom:modal>
</atom:modal>

<atom:form wire:submit="save">
    <atom:heading size="xl">A page form, not a modal</atom:heading>
</atom:form>

<atom:form.modal name="quick">
    <atom:input label="Name" />
</atom:form.modal>
