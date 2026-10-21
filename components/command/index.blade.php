@props([
    'name' => null,
    'shortcut' => 'meta.k',
    'placeholder' => null,
])

@php
// current() returns false (not null) when no component is on the stack, so the
// nullsafe operator alone is not enough — mirror the modal's name default.
$name ??= (app('livewire')->current() ?: null)?->getName();
$placeholder ??= t('Search...');
$classes = Arr::toCssClasses([
    'group/command m-auto mt-[10vh] w-full max-w-xl overflow-hidden rounded-modal p-0 shadow-pop',
    'bg-surface-raised text-fg border border-border-strong',
    '[&[data-open]]:flex [&[data-open]]:flex-col',
]);
@endphp

<dialog
wire:ignore.self
x-data="command({ name: @js($name) })"
x-on:atom-command-show.window="showCommand"
x-on:atom-command-close.window="closeCommand"
x-on:keydown.escape.stop.prevent="closeCommand"
@if ($shortcut) x-on:keydown.{{ $shortcut }}.window.prevent="toggle" @endif
x-on:click="backdropClick"
data-atom-command
{{ $attributes->class($classes) }}>
    <div class="flex items-center gap-2 border-b border-border px-4">
        <atom:icon.search class="size-4 shrink-0 text-fg-subtle"/>

        <input
        type="text"
        role="combobox"
        x-bind:aria-expanded="open"
        aria-controls="{{ $name }}-command-list"
        autocomplete="off"
        data-atom-command-search
        x-model="text"
        x-on:keydown.down.prevent="keyDown"
        x-on:keydown.up.prevent="keyUp"
        x-on:keydown.enter.prevent="enterKey"
        x-on:keydown.home.prevent="home"
        x-on:keydown.end.prevent="end"
        placeholder="{{ $placeholder }}"
        class="h-12 w-full border-0 bg-transparent text-base text-fg placeholder:text-fg-subtle focus-visible:outline-none"/>
    </div>

    <div id="{{ $name }}-command-list" role="listbox" aria-label="{{ t('Commands') }}" data-atom-command-list class="max-h-[60vh] overflow-y-auto p-2">
        {{ $slot }}

        <div data-atom-command-empty hidden class="px-3 py-8 text-center text-sm text-fg-muted">
            {{ $empty ?? t('No results.') }}
        </div>
    </div>
</dialog>
