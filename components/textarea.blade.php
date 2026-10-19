@aware(['disabled' => false])

@props([
    'name' => null,
    'label' => null,
    'caption' => null,
    'invalid' => null,
    'autoresize' => false,
    'placeholder' => null,
    'required' => false,
    'error' => null,
    'variant' => null,
    'rows' => 3,
])

@php
$name ??= $attributes->wire('model')->value();
$error ??= $errors?->first($name);
$transparent = $variant === 'transparent';

// Same association as <atom:input>: without an id to point at, the label is decoration
// and a screen reader announces the field unnamed. Derived, never random — see
// ComponentAttributeBag::fieldId() for why a per-render id breaks Livewire's morph.
$textareaId = $label ? $attributes->fieldId('atom-textarea', $name, $label) : null;

$merges = compact('rows', 'variant', 'autoresize', 'placeholder', 'required', 'invalid');

if ($textareaId) {
    $merges['id'] = $textareaId;
}

/* The field recipe (see input/general): a strong hairline on the surface, the base :focus-visible ring hugging it. The
   transparent variant has no border or fill, and tints on focus. Taller than a control by its rows, never shorter. */
$classes = Arr::toCssClasses([
    'w-full py-1.5 px-2.5 rounded-control text-sm text-fg placeholder:text-fg-subtle',
    'disabled:resize-none disabled:cursor-not-allowed read-only:resize-none',
    'resize-none' => $autoresize,
    'border border-danger-fg' => $invalid,
    'border border-border-strong' => !$invalid && !$transparent,
    'border-0 bg-transparent focus-visible:bg-surface-sunken' => $transparent,
    'bg-surface focus-visible:border-focus focus-visible:outline-offset-0 disabled:bg-surface-sunken disabled:text-fg-subtle' => !$transparent,
    'group-has-[[data-atom-error]]/field:border group-has-[[data-atom-error]]/field:border-danger-fg',
]);
@endphp

@if ($label || $caption)
    <atom:input.field
    :for="$textareaId"
    :label="$label"
    :caption="$caption"
    :required="$required"
    :error="$error">
        <atom:textarea :attributes="$attributes->merge($merges)">
            {{ $slot }}
        </atom:textarea>
    </atom:input.field>
@else
    <textarea {{ $attributes->class($classes)->merge([
        'rows' => $rows,
        'x-autosize' => $autoresize,
        'x-init' => $autoresize ? '$autosize()' : null,
        'required' => $required,
        'placeholder' => t($placeholder),
        'readonly' => ($disabled ?? false) ?: null,
    ]) }} data-atom-textarea>{{ $slot }}</textarea>
@endif
