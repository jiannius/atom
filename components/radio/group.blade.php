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
@elseif ($attributes->get('variant') === 'card')
    <atom:card :subtle="$attributes->get('subtle')" inset>
        <div {{ $attributes->class(['flex flex-col divide-y dark:divide-zinc-700 [&>[data-atom-radio]]:py-3 [&>[data-atom-radio]]:px-5']) }}>
            {{ $slot }}
        </div>
    </atom:card>
@else
    <div {{ $attributes->class(['group/group flex flex-col gap-2 [&>[data-atom-heading]]:mb-1']) }}>
        {{ $slot }}
    </div>
@endif
