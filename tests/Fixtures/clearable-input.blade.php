<div class="space-y-4">
    {{-- one clearable field per way of binding it; the server values are printed so the spec can tell the model from the DOM --}}
    <atom:input label="Plain" clearable wire:model="plain" data-probe="plain" />
    <atom:input label="Live" clearable wire:model.live="live" data-probe="live" />
    <atom:input label="Blur" clearable wire:model.blur="blur" data-probe="blur" />
    <atom:input label="Lazy" clearable wire:model.lazy="lazy" data-probe="lazy" />

    {{-- an ancestor that stops the input event: the clear must still reach the model, which listens on the input itself --}}
    <div x-on:input.stop>
        <atom:input label="Stopped" clearable wire:model.live="stopped" data-probe="stopped" />
    </div>

    <div x-data="{ name: '' }">
        <atom:input label="Alpine" clearable x-model="name" data-probe="alpine" />
        <span data-alpine-model x-text="name"></span>
    </div>

    <atom:button type="button" data-elsewhere>Elsewhere</atom:button>

    <div>
        @foreach (['plain', 'live', 'blur', 'lazy', 'stopped'] as $field)
            <span data-server="{{ $field }}">{{ $this->{$field} }}</span>
        @endforeach
    </div>
</div>
