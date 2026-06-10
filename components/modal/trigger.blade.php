@props([
    'name' => null,
    'slide' => null,
    'shortcut' => null,
])

@php
// Mirror the modal's own name default so a bare trigger pairs with a bare
// modal inside the same Livewire component. current() returns false (not
// null) when no component is on the stack.
$name ??= (app('livewire')->current() ?: null)?->getName();
@endphp

<div
{{ $attributes->class('contents') }}
x-data
@if ($slide)
x-on:click="$el.querySelector('button[disabled]') || atom.modal(@js($name)).slide(@js($slide))"
@else
x-on:click="$el.querySelector('button[disabled]') || atom.modal(@js($name)).show()"
@endif
@if ($shortcut && $slide)
x-on:keydown.{{ $shortcut }}.document="$event.preventDefault(); atom.modal(@js($name)).slide(@js($slide))"
@elseif ($shortcut)
x-on:keydown.{{ $shortcut }}.document="$event.preventDefault(); atom.modal(@js($name)).show()"
@endif
data-atom-modal-trigger>
    {{ $slot }}
</div>
