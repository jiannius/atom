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
    'group/command m-auto mt-[10vh] w-full max-w-xl overflow-hidden rounded-xl p-0 shadow-lg',
    'bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700',
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
    <div class="flex items-center gap-2 border-b border-zinc-200 px-4 dark:border-zinc-700">
        <atom:icon.search class="size-5 shrink-0 text-zinc-400"/>

        <input
        type="text"
        role="combobox"
        aria-expanded="true"
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
        class="w-full border-0 bg-transparent py-4 text-base focus:outline-none focus:ring-0"/>
    </div>

    <div id="{{ $name }}-command-list" role="listbox" data-atom-command-list class="max-h-[60vh] overflow-y-auto p-2">
        {{ $slot }}

        <div data-atom-command-empty hidden class="px-3 py-8 text-center text-sm text-zinc-500">
            {{ $empty ?? t('No results.') }}
        </div>
    </div>
</dialog>
