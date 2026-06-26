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

$classes = Arr::toCssClasses([
    'w-full text-zinc-700 dark:text-zinc-200 outline-offset-1',
    'py-2 px-3 rounded-lg',
    'hover:outline-1 hover:outline-zinc-100/50',
    'disabled:resize-none read-only:resize-none',
    'resize-none' => $autoresize,
    'border border-red-400' => $invalid,
    'border border-zinc-200 dark:border-white/10' => !$invalid,
    'border-0 bg-transparent dark:bg-transparent' => $transparent,
    'focus:outline-none focus:bg-zinc-50 dark:focus:bg-zinc-700/50' => $transparent,
    'shadow-sm bg-white dark:bg-white/10 focus:outline-1 focus:outline-zinc-200 dark:focus:outline-2' => !$transparent,
    'group-has-[[data-atom-error]]/field:border group-has-[[data-atom-error]]/field:border-red-400',
]);
@endphp

@if ($label || $caption)
    <atom:input.field
    :label="$label"
    :caption="$caption"
    :required="$required"
    :error="$error">
        <atom:textarea :attributes="$attributes->merge(compact('rows', 'variant', 'autoresize', 'placeholder', 'required', 'invalid'))">
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
