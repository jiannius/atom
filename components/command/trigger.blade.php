@props([
    'name' => null,
])

@php
// Mirror the palette's own name default so a bare trigger pairs with a bare
// command inside the same Livewire component.
$name ??= (app('livewire')->current() ?: null)?->getName();
@endphp

<div
{{ $attributes->class('contents') }}
x-data
x-on:click="atom.command(@js($name)).show()"
data-atom-command-trigger>
    {{ $slot }}
</div>
