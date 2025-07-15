@props([
    'name' => null,
    'label' => null,
    'caption' => null,
    'invalid' => null,
    'autoresize' => false,
    'placeholder' => null,
    'required' => false,
    'error' => null,
    'rows' => 3,
])

@php
$name ??= $attributes->wire('model')->value();
$error ??= $errors?->first($name);

$classes = Arr::toCssClasses([
    'w-full text-zinc-700 dark:text-zinc-200 shadow-sm outline-offset-1',
    'py-2 px-3 rounded-lg shadow-sm bg-white dark:bg-white/10',
    'focus:outline-1 focus:outline-zinc-200 dark:focus:outline-2 hover:outline-1 hover:outline-zinc-100/50',
    'disabled:resize-none read-only:resize-none',
    $autoresize ? 'resize-none' : null,
    $invalid ? 'border border-red-400' : 'border border-zinc-200 dark:border-white/10',
    'group-has-[[data-atom-error]]/field:border group-has-[[data-atom-error]]/field:border-red-400',
]);
@endphp

@if ($label || $caption)
    <atom:input.field
    :label="$label"
    :caption="$caption"
    :required="$required"
    :error="$error">
        <atom:textarea :attributes="$attributes->merge(compact('rows', 'autoresize', 'placeholder', 'required', 'invalid'))" />
    </atom:input.field>
@else
    <textarea {{ $attributes->class($classes)->merge([
        'rows' => $rows,
        'x-autosize' => $autoresize,
        'x-init' => $autoresize ? '$autosize()' : null,
        'required' => $required,
        'placeholder' => t($placeholder),
    ]) }} data-atom-textarea></textarea>
@endif
