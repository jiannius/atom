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

// Each option is already named by its own <label> wrapper, but nothing named the set, so
// a screen reader read the choices with no idea what they were choosing. A radiogroup
// cannot take a <label for> — it is not one control — so it is named by pointing back at
// the field's label. Derived, not minted per render: see ComponentAttributeBag::fieldId().
$labelId = $label ? $attributes->fieldId('atom-radio-group', $name, $label).'-label' : null;
@endphp

@if ($label || $caption)
    <atom:input.field
    :label="$label"
    :caption="$caption"
    :inline="$inline"
    :required="$required"
    :error="$error"
    :label-id="$labelId">
        <atom:radio.group role="radiogroup" :aria-labelledby="$labelId" :attributes="$attributes">
            {{ $slot }}
        </atom:radio.group>
    </atom:input.field>
@elseif ($attributes->get('variant') === 'card')
    <atom:card :subtle="$attributes->get('subtle')" inset>
        <div {{ $attributes->class(['flex flex-col divide-y divide-zinc-200 dark:divide-zinc-700 [&>[data-atom-radio]]:py-3 [&>[data-atom-radio]]:px-5']) }}>
            {{ $slot }}
        </div>
    </atom:card>
@else
    <div {{ $attributes->class(['group/group flex flex-col gap-2 [&>[data-atom-heading]]:mb-1']) }}>
        {{ $slot }}
    </div>
@endif
