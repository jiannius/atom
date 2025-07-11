@props([
    'name' => null,
    'variant' => 'native',
    'label' => null,
    'inline' => null,
    'caption' => null,
    'required' => null,
    'error' => null,
    'prefix' => null,
    'suffix' => null,
])

@php
$name ??= $attributes->wire('model')->value();
$error ??= $errors?->first($name);
$merges = ['required' => $required, 'error' => $error];
@endphp

@if ($variant === 'native')
    @if ($label || $caption)
        <atom:input.field
        :label="$label"
        :caption="$caption"
        :required="$required"
        :inline="$inline"
        :error="$error">
            <atom:select.native :attributes="$attributes->merge($merges)">
                {{ $slot }}
            </atom:select.native>
        </atom:input.field>
    @elseif ($prefix || $suffix)
        <atom:input.prefix :prefix="$prefix" :suffix="$suffix">
            <atom:select.native :attributes="$attributes->merge($merges)">
                {{ $slot }}
            </atom:select.native>
        </atom:input.prefix>
    @else
        <atom:select.native :attributes="$attributes->merge($merges)">
            {{ $slot }}
        </atom:select.native>
    @endif
@elseif ($variant === 'listbox')
    <atom:select.listbox :attributes="$attributes->merge($merges)">
        {{ $slot }}

        @isset ($addButton)
            <x-slot:add-button>
                {{ $addButton }}
            </x-slot:add-button>
        @endisset

        @isset ($actions)
            <x-slot:actions>
                {{ $actions }}
            </x-slot:actions>
        @endisset
    </atom:select.listbox>
@endif
