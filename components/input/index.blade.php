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
$merges = [
    'type' => $type,
    'required' => $required,
    'name' => $name,
];
@endphp

@if (in_array($type, ['text', 'password', 'number']))
    @if ($label || $caption)
        <atom:input.field
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
    @else
        <x-dynamic-component component="atom::input.general" :attributes="$attributes->merge($merges)">
            {{ $slot }}
            <x-slot:actions>{{ $actions ?? '' }}</x-slot:actions>
        </x-dynamic-component>
    @endif
@elseif (in_array($type, ['tel', 'color']))
    @if ($label || $caption)
        <atom:input.field
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
        :label="$label"
        :caption="$caption"
        :required="$required"
        :error="$error">
            @if ($attributes->get('options') || $attributes->get('multiple'))
                <atom:input.email :attributes="$attributes->merge($merges)">
                    {{ $slot }}
                </atom:input.email>
            @else
                <atom:input.general :attributes="$attributes->merge($merges)">
                    {{ $slot }}
                </atom:input.general>
            @endif
        </atom:input.field>
    @elseif ($attributes->has('options'))
        <atom:input.email :attributes="$attributes->merge($merges)">
            {{ $slot }}
        </atom:input.email>
    @else
        <atom:input.general :attributes="$attributes->merge($merges)">
            {{ $slot }}
        </atom:input.general>
    @endif
@elseif ($type === 'file')
    <atom:input.file :attributes="$attributes->merge($merges)">
        {{ $slot }}
    </atom:input.file>
@endif