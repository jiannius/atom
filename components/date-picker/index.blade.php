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
    'timezone' => null,
])

@php
$name ??= $attributes->wire('model')->value();
$error ??= $errors?->first($name);

// Derived, not minted per render: see Services\Attributes::fieldId().
$inputId = $label ? \Jiannius\Atom\Services\Attributes::fieldId($attributes, 'atom-date-picker', $name, $label, $variant) : null;

// 'self', 'inherited' from a disabled <atom:form>, or null; the picker's own `disabled` (`:disabled="false"` too) wins over the form's. The
// variants print the marker and pick the real `disabled` (its own) or `readonly` (a form's) from it, and their JS reads it at event time.
$disabledBy = \Jiannius\Atom\Services\Attributes::disabledBy($attributes);

$merges = [
    'required' => $required,
    'data-atom-disabled' => $disabledBy,
];

if ($inputId) {
    $merges['id'] = $inputId;
}

// Forwarded only when set, so a variant that has no use for it (calendar) is handed nothing.
if (filled($timezone)) {
    $merges['timezone'] = $timezone;
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
