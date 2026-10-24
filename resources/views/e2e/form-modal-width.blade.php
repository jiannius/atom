@php
// <atom:form.modal cols="1"> with a width class of the caller's, opened on load: ?class=fixed|max|both|none, ?tw=floor|latest, ?dark=1
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
    <atom:form.modal name="width" cols="1" class="{{ $class }}" data-testid="dialog">
        <atom:input label="Subject"/>
    </atom:form.modal>
</div>

<div x-data x-init="$nextTick(() => atom.modal('width').show())"></div>

@livewireScripts
</atom:html>
