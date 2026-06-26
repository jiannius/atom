@aware(['disabled' => false])

@if ($attributes->get('variant') === 'filter')
    <atom:select.filter :attributes="$attributes">
        {{ $slot }}
    </atom:select.filter>
@else
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
    $merges = [
        'name' => $name,
        'required' => $required,
        // Inherit a read-only state from an enclosing <atom:form disabled>.
        'disabled' => ($disabled ?? false) ?: null,
    ];
    @endphp

    @if ($label || $caption)
        <atom:input.field
        :label="$label"
        :caption="$caption"
        :required="$required"
        :inline="$inline"
        :error="$error">
            <x-dynamic-component :component="'atom::select.'.$variant" :attributes="$attributes->merge($merges)">
                {{ $slot }}
                <x-slot:add-button>{{ $addButton ?? '' }}</x-slot:add-button>
                <x-slot:actions>{{ $actions ?? '' }}</x-slot:actions>
            </x-dynamic-component>
        </atom:input.field>
    @elseif ($prefix || $suffix)
        <atom:input.prefix :prefix="$prefix" :suffix="$suffix">
            <x-dynamic-component :component="'atom::select.'.$variant" :attributes="$attributes->merge($merges)">
                {{ $slot }}
                <x-slot:add-button>{{ $addButton ?? '' }}</x-slot:add-button>
                <x-slot:actions>{{ $actions ?? '' }}</x-slot:actions>
            </x-dynamic-component>
        </atom:input.prefix>
    @else
        <x-dynamic-component :component="'atom::select.'.$variant" :attributes="$attributes->merge($merges)">
            {{ $slot }}
            <x-slot:add-button>{{ $addButton ?? '' }}</x-slot:add-button>
            <x-slot:actions>{{ $actions ?? '' }}</x-slot:actions>
        </x-dynamic-component>
    @endif
@endif
