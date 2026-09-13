@aware(['disabled' => false])

@props([
    'name' => null,
    'type' => 'text',
    'label' => null,
    'caption' => null,
    'prefix' => null,
    'suffix' => null,
    'required' => false,
    'error' => null,
])

@php
$name ??= $attributes->wire('model')->value();
$error ??= $errors?->first($name);

// The label needs something to point at. Without it a screen reader announces every
// field as an unnamed "edit text" — the visible caption is presentational only. The id
// is derived, never random: see ComponentAttributeBag::fieldId(). Only minted where
// there is a label to carry it, so an unlabelled field keeps the markup it had.
$fieldId = $label ? $attributes->fieldId('atom-input', $name, $label, $type) : null;

// `file` is named the other way round. The uploader's own <input type="file"> is hidden,
// so it is out of the accessibility tree and a <label for> computes no name from it — the
// visible control is the uploader's trigger button, which points back at the label.
$inputId = $type === 'file' ? null : $fieldId;
$labelId = $type === 'file' && $fieldId ? $fieldId.'-label' : null;

$merges = [
    'type' => $type,
    'required' => $required,
    'name' => $name,
];

if ($inputId) {
    $merges['id'] = $inputId;
}

// Inherit a read-only state from an enclosing <atom:form disabled> so the value
// stays selectable/copyable (unlike a disabled field), but can't be edited.
// `?? false` keeps it safe where @aware has no parent to read (e.g. isolated
// component renders).
if ($disabled ?? false) {
    $merges['readonly'] = true;
}
@endphp

@if (in_array($type, ['text', 'password', 'number']))
    @if ($label || $caption)
        <atom:input.field
        :for="$inputId"
        :label="$label"
        :caption="$caption"
        :required="$required"
        :error="$error">
            @if ($prefix || $suffix)
                <atom:input.prefix :prefix="$prefix" :suffix="$suffix">
                    <x-dynamic-component component="atom::input.general" :attributes="$attributes->merge($merges)">
                        {{ $slot }}
                        <x-slot:actions>{{ $actions ?? '' }}</x-slot:actions>
                    </x-dynamic-component>
                </atom:input.prefix>    
            @else
                <x-dynamic-component component="atom::input.general" :attributes="$attributes->merge($merges)">
                    {{ $slot }}
                    <x-slot:actions>{{ $actions ?? '' }}</x-slot:actions>
                </x-dynamic-component>
            @endif
        </atom:input.field>
    @elseif ($prefix || $suffix)
        <atom:input.prefix :prefix="$prefix" :suffix="$suffix">
            <x-dynamic-component component="atom::input.general" :attributes="$attributes->merge($merges)">
                {{ $slot }}
                <x-slot:actions>{{ $actions ?? '' }}</x-slot:actions>
            </x-dynamic-component>
        </atom:input.prefix>
    @else
        <x-dynamic-component component="atom::input.general" :attributes="$attributes->merge($merges)">
            {{ $slot }}
            <x-slot:actions>{{ $actions ?? '' }}</x-slot:actions>
        </x-dynamic-component>
    @endif
@elseif (in_array($type, ['tel', 'color']))
    @if ($label || $caption)
        <atom:input.field
        :for="$inputId"
        :label="$label"
        :caption="$caption"
        :required="$required"
        :error="$error">
            <x-dynamic-component :component="'atom::input.'.$type" :attributes="$attributes->merge($merges)">
                {{ $slot }}
                <x-slot:actions>{{ $actions ?? '' }}</x-slot:actions>
            </x-dynamic-component>
        </atom:input.field>
    @else
        <x-dynamic-component :component="'atom::input.'.$type" :attributes="$attributes->merge($merges)">
            {{ $slot }}
            <x-slot:actions>{{ $actions ?? '' }}</x-slot:actions>
        </x-dynamic-component>
    @endif
@elseif ($type === 'email')
    @if ($label || $caption)
        <atom:input.field
        :for="$inputId"
        :label="$label"
        :caption="$caption"
        :required="$required"
        :error="$error">
            @if ($attributes->get('multiple') || $attributes->has('options'))
                <atom:input.email :attributes="$attributes->merge($merges)">
                    {{ $slot }}
                </atom:input.email>
            @else
                <atom:input.general :attributes="$attributes->merge($merges)">
                    {{ $slot }}
                </atom:input.general>
            @endif
        </atom:input.field>
    @elseif ($attributes->get('multiple') || $attributes->has('options'))
        <atom:input.email :attributes="$attributes->merge($merges)">
            {{ $slot }}
        </atom:input.email>
    @else
        <atom:input.general :attributes="$attributes->merge($merges)">
            {{ $slot }}
        </atom:input.general>
    @endif
@elseif ($type === 'file')
    @if ($label || $caption)
        <atom:input.field
        :for="$inputId"
        :label-id="$labelId"
        :label="$label"
        :caption="$caption"
        :required="$required"
        :error="$error">
            {{-- passed on the tag rather than merged into the bag: a kebab-cased prop
                 resolves from a literal attribute but not from a bag handed to an
                 <atom:...> tag, and it would silently be null --}}
            <atom:uploader :aria-labelledby="$labelId" :attributes="$attributes->merge($merges)">
                {{ $slot }}
            </atom:uploader>
        </atom:input.field>
    @else
        <atom:uploader :attributes="$attributes->merge($merges)">
            {{ $slot }}
        </atom:uploader>
    @endif
@endif