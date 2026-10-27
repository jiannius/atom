@php
// <atom:form.modal cols="1"> with a width class of the caller's, opened on load: ?class=fixed|max|both|none, ?tw=floor|latest, ?dark=1.
// ?actions=1 adds an `actions` slot (a second button in the footer); ?plain=1 draws a plain <atom:form.actions> in a page form instead of the modal.
$tw = in_array(request()->query('tw'), ['floor', 'latest'], true) ? request()->query('tw') : 'latest';
$class = match (request()->query('class')) {
    'max' => 'max-w-md',
    'both' => 'w-[28rem] max-w-lg',
    'none' => '',
    default => 'w-[28rem]',
};
@endphp

<atom:html title="E2E: form.modal width" :vite="[]" :dark="request()->query('dark') === '1'" :styles="['/atom/e2e/consumer-css/'.$tw]">
<div class="p-4">
    @if (request()->query('plain') === '1')
        <atom:form x-on:submit.prevent class="w-[28rem]" data-testid="plain-form">
            <atom:input label="Subject"/>
            <atom:form.actions />
        </atom:form>
    @else
        <atom:form.modal name="width" cols="1" class="{{ $class }}" data-testid="dialog">
            <atom:input label="Subject"/>
            @if (request()->query('actions') === '1')
                <x-slot:actions><atom:button type="button">Cancel</atom:button></x-slot:actions>
            @endif
        </atom:form.modal>
    @endif
</div>

@unless (request()->query('plain') === '1')
    <div x-data x-init="$nextTick(() => atom.modal('width').show())"></div>
@endunless

@livewireScripts
</atom:html>
