@props([
    'label' => null,
])

@php
$classes = 'h-control aspect-square rounded-nav flex items-center justify-center text-fg-muted hover:bg-fg/5 hover:text-fg';
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
