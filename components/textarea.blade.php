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

/* 'self' (its own `disabled`, `:disabled="false"` included, which wins), 'inherited' from a disabled <atom:form> (the textarea is then `readonly`, so the text stays
   selectable), or null. Printed as `data-atom-disabled`, which is also what the carry in helpers/morphed.js writes onto an autoresize textarea (the plugin makes it
   `wire:ignore`, so its class is never patched): the look below reads the marker from the element itself, never from a class the server picked per render. A marker
   border would outrank the focus one, so the focus variants are stacked after it. */
$disabledBy = \Jiannius\Atom\Services\Attributes::disabledBy($attributes);

// Same association as <atom:input>: without an id to point at, the label is decoration
// and a screen reader announces the field unnamed. Derived, never random — see
// Services\Attributes::fieldId() for why a per-render id breaks Livewire's morph.
$textareaId = $label ? \Jiannius\Atom\Services\Attributes::fieldId($attributes, 'atom-textarea', $name, $label) : null;

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
    'border border-control-border' => !$invalid && !$transparent,
    'border-0 bg-transparent focus-visible:bg-surface-sunken' => $transparent,
    'bg-surface focus-visible:outline-offset-0 disabled:border-border disabled:bg-surface-sunken disabled:text-fg-subtle' => !$transparent,
    'focus-visible:border-focus' => !$transparent && !$invalid,
    'focus-visible:border-danger-fg' => $invalid,
    '[&[data-atom-disabled]]:cursor-not-allowed [&[data-atom-disabled]]:text-fg-subtle',
    '[&[data-atom-disabled]]:border-border [&[data-atom-disabled]]:bg-surface-sunken' => !$transparent,
    '[&[data-atom-disabled]]:focus-visible:border-focus' => !$transparent && !$invalid,
    '[&[data-atom-disabled]]:focus-visible:border-danger-fg' => !$transparent && $invalid,
    'group-has-[[data-atom-error]]/field:border group-has-[[data-atom-error]]/field:border-danger-fg group-has-[[data-atom-error]]/field:focus-visible:border-danger-fg',
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
        'readonly' => $disabledBy === 'inherited' ? true : null,
        'data-atom-disabled' => $disabledBy,
    ]) }} data-atom-textarea>{{ $slot }}</textarea>
@endif
