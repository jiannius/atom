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
        <atom:radio.group :attributes="$attributes">
            {{ $slot }}
        </atom:radio.group>
    </atom:input.field>
@else
    <div {{ $attributes->class(['space-y-2'])->only('class') }}>
        {{ $slot }}
    </div>
@endif
