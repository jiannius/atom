@props([
    'name' => null,
    'label' => null,
    'caption' => null,
    'inline' => false,
    'required' => false,
    'error' => null,
])

@php
$name ??= $attributes->wire('model')->value();
$error ??= $errors?->first($name);
@endphp

@if ($label || $caption)
    <atom:input.field
    :label="$label"
    :caption="$caption"
    :inline="$inline"
    :required="$required"
    :error="$error">
        <atom:radio.group :attributes="$attributes->merge(['name' => $name])">
            {{ $slot }}
        </atom:radio.group>
    </atom:input.field>
@else
    <div x-data="{ groupName: @js($name) }" {{ $attributes->class(['space-y-2']) }}>
        {{ $slot }}
    </div>
@endif
