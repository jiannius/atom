@props([
    'label' => null,
])

@php
$classes = 'p-2 rounded-md flex items-center justify-center hover:bg-zinc-100 dark:hover:bg-zinc-700';
@endphp

@if ($label)
    <atom:tooltip :content="$label">
        <button type="button" {{ $attributes->class($classes) }}>
            {{ $slot }}
        </button>
    </atom:tooltip>
@else
    <button type="button" {{ $attributes->class($classes) }}>
        {{ $slot }}
    </button>
@endif
