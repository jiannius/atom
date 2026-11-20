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
// is derived, never random: see Services\Attributes::fieldId(). Only minted where
// there is a label to carry it, so an unlabelled field keeps the markup it had.
$fieldId = $label ? \Jiannius\Atom\Services\Attributes::fieldId($attributes, 'atom-input', $name, $label, $type) : null;

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

// A disabled control prints why (`self`, or `inherited` from a disabled form): the variants and their JS read the
// marker. An inherited field is read-only, so the value stays selectable and copyable but cannot be edited; its own `disabled`
// (`:disabled="false"` included) wins over the form, and a self-disabled field already carries the attribute. `color` keeps its own
// handling, and `file` takes no `readonly` (it means nothing on a file input).
$disabledBy = \Jiannius\Atom\Services\Attributes::disabledBy($attributes);

/* the types the chain below draws: any other prints nothing, not an empty field around it (the captured markup cannot tell, a Livewire view
   prints its morph markers even for a branch nothing takes) */
$drawn = in_array($type, ['text', 'password', 'number', 'tel', 'color', 'email', 'file'], true);

if ($type !== 'color') {
    $merges['data-atom-disabled'] = $disabledBy;
}

if ($disabledBy === 'inherited' && $type !== 'file') {
    $merges['readonly'] = true;
}
@endphp

{{-- A tag cannot be opened and closed under two separate @if directives, so the control is captured once and printed either bare or inside
     the field (the label and caption), instead of once per type under each of the two. The same goes for the affix box around a text
     control. Every @if and @endif below sits on a line of its own: a directive glued to the word before it is not seen by
     Livewire's morph markers (#263). A bare control ends with the line break and indent it always printed, so a page that
     pins its bytes (the table toolbar's golden) reads the same. --}}
@php ob_start(); @endphp
@if (in_array($type, ['text', 'password', 'number']))
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
@elseif (in_array($type, ['tel', 'color']))
    <x-dynamic-component :component="'atom::input.'.$type" :attributes="$attributes->merge($merges)">
        {{ $slot }}
        <x-slot:actions>{{ $actions ?? '' }}</x-slot:actions>
    </x-dynamic-component>
@elseif ($type === 'email')
    @if ($attributes->get('multiple') || $attributes->has('options'))
        <atom:input.email :attributes="$attributes->merge($merges)">
            {{ $slot }}
        </atom:input.email>
    @else
        <atom:input.general :attributes="$attributes->merge($merges)">
            {{ $slot }}
        </atom:input.general>
    @endif
@elseif ($type === 'file')
    {{-- passed on the tag rather than merged into the bag: a kebab-cased prop
         resolves from a literal attribute but not from a bag handed to an
         <atom:...> tag, and it would silently be null --}}
    <atom:uploader :aria-labelledby="$labelId" :attributes="$attributes->merge($merges)">
        {{ $slot }}

        @if (isset($end) && $end->hasActualContent())
            <x-slot:end :attributes="$end->attributes">{{ $end }}</x-slot:end>
        @endif
    </atom:uploader>
@endif
@php $control = rtrim(ob_get_clean())."\n    "; @endphp

@if (! $drawn)
    {{-- a type nothing draws prints nothing --}}
@elseif ($label || $caption)
    <atom:input.field
    :for="$inputId"
    :label-id="$labelId"
    :label="$label"
    :caption="$caption"
    :required="$required"
    :error="$error">
        {!! $control !!}
    </atom:input.field>
@else
    {!! $control !!}@endif