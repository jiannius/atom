<div class="space-y-8">
    <div>Saves: <span data-saves>{{ $saves }}</span></div>

    <button type="button" wire:click="touch" data-touch>Touch</button>

    <atom:form wire:submit="save" data-form="main" :error-toast="$errorToast" :disabled="$disabled" :recaptcha="$recaptcha">
        <atom:input label="Name" wire:model="name" data-probe="name" />
        <atom:input label="Nickname" wire:model="nickname" data-probe="nickname" />
        <atom:input label="Email" wire:model="email" data-probe="email" />
        <atom:button type="submit" data-submit="main">Save</atom:button>
    </atom:form>

    <atom:form wire:submit="saveOther" data-form="other" :error-toast="$errorToast">
        <atom:input label="Other" wire:model="other" data-probe="other" />
        <atom:button type="submit" data-submit="other">Save other</atom:button>
    </atom:form>

    {{-- shares saveOther with the form above, for a call that names no origin --}}
    <atom:form wire:submit="saveOther" data-form="twin">
        <atom:input label="Twin" wire:model="twin" data-probe="twin" />
        <atom:button type="submit" data-submit="twin">Save twin</atom:button>
    </atom:form>

    <atom:form wire:submit="savePlain" data-form="plain">
        <atom:input label="Plain" wire:model="plain" data-probe="plain" />
        <atom:button type="submit" data-submit="plain">Save plain</atom:button>
    </atom:form>

    @unless ($gone)
        <atom:form wire:submit="saveGone" data-form="gone">
            <atom:input label="Gone field" wire:model="goneField" data-probe="gone-field" />
            <atom:button type="submit" data-submit="gone">Save gone</atom:button>
        </atom:form>
    @endunless

    <atom:form wire:submit="saveMany" data-form="many">
        @foreach (array_keys($rows) as $key)
            <atom:input :label="'Row '.$key" wire:model="rows.{{ $key }}" />
        @endforeach
        <atom:button type="submit" data-submit="many">Save many</atom:button>
    </atom:form>

    <atom:form.modal name="fixture-modal">
        <atom:input label="Modal field" wire:model="modalField" data-probe="modal-field" />
    </atom:form.modal>
</div>
