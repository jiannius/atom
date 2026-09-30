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

    // `native` renders a real <select>, so its label can point straight at it. `listbox`
    // is a composed widget whose combobox is a button or a search input, so it is named
    // the other way round — aria-labelledby back at the label. Both ids derive from the
    // field rather than being minted per render: see ComponentAttributeBag::fieldId().
    $fieldId = $label ? $attributes->fieldId('atom-select', $name, $label, $variant) : null;
    $labelId = $fieldId && $variant !== 'native' ? $fieldId.'-label' : null;

    $merges = [
        'name' => $name,
        'required' => $required,
        // Inherit a read-only state from an enclosing <atom:form disabled>.
        'disabled' => ($disabled ?? false) ?: null,
    ];

    if ($fieldId && $variant === 'native') {
        $merges['id'] = $fieldId;
    }

    if ($labelId && $variant !== 'native') {
        $merges['aria-labelledby'] = $labelId;
    }

    // A table-filter chip is named by the field's label — which this component
    // consumes as a prop, so the variant that emits the chip never sees it.
    if ($label && $attributes->has('table-filter')) {
        $merges['table-filter-label'] = $label;
    }
    @endphp

    @if ($label || $caption)
        <atom:input.field
        :label="$label"
        :caption="$caption"
        :required="$required"
        :inline="$inline"
        :error="$error"
        :for="$variant === 'native' ? $fieldId : null"
        :label-id="$labelId">
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
