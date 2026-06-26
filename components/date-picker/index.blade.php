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
$merges = [
    'required' => $required,
    // Inherit a read-only state from an enclosing <atom:form disabled>.
    'disabled' => ($disabled ?? false) ?: null,
];
@endphp

@if ($label || $caption)
    <atom:input.field
    :label="$label"
    :caption="$caption"
    :inline="$inline"
    :required="$required"
    :error="$error">
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
