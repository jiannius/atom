@props([
    'name' => null,
    'slide' => null,
    'shortcut' => null,
])

@php
// Mirror the modal's own name default so a bare trigger pairs with a bare
// modal inside the same Livewire component.
$name ??= app('atom')->currentComponentName();
@endphp

<div
{{ $attributes->class('contents') }}
x-data
@if ($slide)
x-on:click="{{ \Jiannius\Atom\Atom::TRIGGER_DISABLED_GUARD }} atom.modal(@js($name)).slide(@js($slide))"
@else
x-on:click="{{ \Jiannius\Atom\Atom::TRIGGER_DISABLED_GUARD }} atom.modal(@js($name)).show()"
@endif
@if ($shortcut && $slide)
x-on:keydown.{{ $shortcut }}.document="$event.preventDefault(); atom.modal(@js($name)).slide(@js($slide))"
@elseif ($shortcut)
x-on:keydown.{{ $shortcut }}.document="$event.preventDefault(); atom.modal(@js($name)).show()"
@endif
data-atom-modal-trigger>
    {{ $slot }}
</div>
