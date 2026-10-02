<div class="space-y-8">
    <div>Saves: <span data-saves>{{ $saves }}</span></div>

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

    <atom:form.modal name="fixture-modal">
        <atom:input label="Modal field" wire:model="modalField" data-probe="modal-field" />
    </atom:form.modal>
</div>
