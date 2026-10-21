@props([
    'icon' => null,
    'align' => null,
])

@php
$classes = Arr::toCssClasses([
    'flex items-center gap-2 select-none text-sm font-medium leading-5 text-fg',

    match ($align) {
        'right' => 'justify-end',
        'center' => 'justify-center',
        default => 'justify-start',
    },
]);
@endphp

@if (isset($actions) && $actions->isNotEmpty())
    <div class="group flex items-center gap-2 justify-between">
        <atom:label :attributes="$attributes">
            {{ $slot }}
        </atom:label>

        <div class="shrink-0">
            {{ $actions }}
        </div>
    </div>
@else
    <label {{ $attributes->class($classes) }} data-atom-label>
        @if ($icon)
            <x-dynamic-component :component="'atom::icon.'.$icon" class="size-4 shrink-0"/>
        @endif

        {{ $slot }}
    </label>
@endisset
