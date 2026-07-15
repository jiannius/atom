@props([
    'name' => null,
])

@php
// Mirror the palette's own name default so a bare trigger pairs with a bare
// command inside the same Livewire component.
$name ??= (app('livewire')->current() ?: null)?->getName();
@endphp

<button
type="button"
x-data
x-on:click="atom.command(@js($name)).show()"
data-atom-command-trigger
{{ $attributes }}>
    {{ $slot }}
</button>
