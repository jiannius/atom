@aware(['disabled' => false])

@props([
    'name' => null,
    'variant' => 'date',
    'label' => null,
    'caption' => null,
    'inline' => false,
    'required' => false,
    'error' => null,
    'prefix' => null,
    'suffix' => null,
])

@php
$name ??= $attributes->wire('model')->value();
$error ??= $errors?->first($name);

// Derived, not minted per render: see ComponentAttributeBag::fieldId().
$inputId = $label ? $attributes->fieldId('atom-date-picker', $name, $label, $variant) : null;

$merges = [
    'required' => $required,
    // Inherit a read-only state from an enclosing <atom:form disabled>.
    'disabled' => ($disabled ?? false) ?: null,
];

if ($inputId) {
    $merges['id'] = $inputId;
}
@endphp

@if ($label || $caption)
    <atom:input.field
    :label="$label"
    :caption="$caption"
    :inline="$inline"
    :required="$required"
    :error="$error"
    :for="$inputId">
        <x-dynamic-component :component="'atom::date-picker.'.$variant" :attributes="$attributes->merge($merges)">
            {{ $slot }}
        </x-dynamic-component>
    </atom:input.field>
@elseif ($prefix || $suffix)
    <atom:input.prefix :prefix="$prefix" :suffix="$suffix">
        <x-dynamic-component :component="'atom::date-picker.'.$variant" :attributes="$attributes->merge($merges)">
            {{ $slot }}
        </x-dynamic-component>
    </atom:input.prefix>
@else
    <x-dynamic-component :component="'atom::date-picker.'.$variant" :attributes="$attributes->merge($merges)">
        {{ $slot }}
    </x-dynamic-component>
@endif
