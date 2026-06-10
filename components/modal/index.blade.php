@props([
    'name' => null,
    'inset' => false,
    'dismissible' => true, // backdrop click closes
    'escapable' => true,   // ESC closes
    'closeable' => true,   // close (X) button shown
])

@php
// current() returns false (not null) when no component is on the stack,
// so the nullsafe operator alone isn't enough.
$name ??= (app('livewire')->current() ?: null)?->getName();
$classes = Arr::toCssClasses([
    'group/modal',
    '[:where(&)]:max-w-full min-w-sm shadow-lg rounded-xl',
    'bg-white dark:bg-zinc-900 border border-transparent dark:border-zinc-700 transition-transform',
    $inset ? 'p-0' : 'p-6',

    '[&[data-atom-modal]]:m-auto',
    '[&[data-atom-modal]]:scale-75',
    '[&[data-atom-modal][data-open]]:scale-100',

    '[&[data-atom-modal-slide]]:fixed',
    '[&[data-atom-modal-slide]]:ml-auto',
    '[&[data-atom-modal-slide]]:rounded-none',
    '[&[data-atom-modal-slide]]:max-h-dvh',
    '[&[data-atom-modal-slide]]:min-h-dvh',
    '[&[data-atom-modal-slide]]:border-y-0',
    '[&[data-atom-modal-slide]]:border-r-0',
    '[&[data-atom-modal-slide]]:overflow-auto',
    '[&[data-atom-modal-slide]]:rtl:ml-0',
    '[&[data-atom-modal-slide]]:rtl:mr-auto',
    '[&[data-atom-modal-slide]]:translate-x-full',
    '[&[data-atom-modal-slide][data-open]]:translate-x-0',

    '[&[data-atom-modal-slide-left]]:fixed',
    '[&[data-atom-modal-slide-left]]:mr-auto',
    '[&[data-atom-modal-slide-left]]:rounded-none',
    '[&[data-atom-modal-slide-left]]:max-h-dvh',
    '[&[data-atom-modal-slide-left]]:min-h-dvh',
    '[&[data-atom-modal-slide-left]]:border-y-0',
    '[&[data-atom-modal-slide-left]]:border-l-0',
    '[&[data-atom-modal-slide-left]]:overflow-auto',
    '[&[data-atom-modal-slide-left]]:rtl:mr-0',
    '[&[data-atom-modal-slide-left]]:rtl:ml-auto',
    '[&[data-atom-modal-slide-left]]:-translate-x-full',
    '[&[data-atom-modal-slide-left][data-open]]:translate-x-0',

    '[&[data-atom-modal-slide-bottom]]:fixed',
    '[&[data-atom-modal-slide-bottom]]:mt-auto',
    '[&[data-atom-modal-slide-bottom]]:rounded-none',
    '[&[data-atom-modal-slide-bottom]]:max-w-full',
    '[&[data-atom-modal-slide-bottom]]:min-w-full',
    '[&[data-atom-modal-slide-bottom]]:min-h-100',
    '[&[data-atom-modal-slide-bottom]]:border-t',
    '[&[data-atom-modal-slide-bottom]]:border-x-0',
    '[&[data-atom-modal-slide-bottom]]:border-b-0',
    '[&[data-atom-modal-slide-bottom]]:overflow-auto',
    '[&[data-atom-modal-slide-bottom]]:translate-y-full',
    '[&[data-atom-modal-slide-bottom][data-open]]:translate-y-0',
]);

// Flux::classes()
//     ->add(match ($variant) {
//         default => 'p-6 [:where(&)]:max-w-xl shadow-lg rounded-xl',
//         'flyout' => match($position) {
//             'bottom' => 'fixed m-0 p-8 min-w-[100vw] overflow-y-auto mt-auto [--fx-flyout-translate:translateY(50px)] border-t',
//             'left' => 'fixed m-0 p-8 max-h-dvh min-h-dvh md:[:where(&)]:min-w-[25rem] overflow-y-auto mr-auto [--fx-flyout-translate:translateX(-50px)] border-e rtl:mr-0 rtl:ml-auto rtl:[--fx-flyout-translate:translateX(50px)]',
//             default => 'fixed m-0 p-8 max-h-dvh min-h-dvh md:[:where(&)]:min-w-[25rem] overflow-y-auto ml-auto [--fx-flyout-translate:translateX(50px)] border-s rtl:ml-0 rtl:mr-auto rtl:[--fx-flyout-translate:translateX(-50px)]',
//         },
//         'bare' => '',
//     })
//     ->add(match ($variant) {
//         default => 'bg-white dark:bg-zinc-800 border border-transparent dark:border-zinc-700',
//         'flyout' => 'bg-white dark:bg-zinc-800 border-transparent dark:border-zinc-700',
//         'bare' => 'bg-transparent',
//     });

// // Support adding the .self modifier to the wire:model directive...
// if (($wireModel = $attributes->wire('model')) && $wireModel->directive && ! $wireModel->hasModifier('self')) {
//     unset($attributes[$wireModel->directive]);

//     $wireModel->directive .= '.self';

//     $attributes = $attributes->merge([$wireModel->directive => $wireModel->value]);
// }

// [ $styleAttributes, $attributes ] = Flux::splitAttributes($attributes, ['autofocus', 'class', 'style', 'wire:close', 'x-on:close', 'wire:cancel', 'x-on:cancel']);
@endphp

<dialog
wire:ignore.self {{-- This needs to be here because the dialog element adds a "close" attribute that isn't durable... --}}
x-data="modal({
    name: @js($name),
    escapable: @js($escapable),
})"
x-on:atom-modal-show.window="showModal"
x-on:atom-modal-close.window="closeModal"
x-on:keydown.escape.stop.prevent="escapeClose"
@if ($dismissible) x-on:click="backdropClick" @endif
{{ $attributes->class($classes) }}>
    @if ($closeable)
        <div class="absolute top-0 end-0 mt-4 me-4">
            <button
            type="button"
            aria-label="{{ t('Close') }}"
            class="flex items-center justify-center text-zinc-400! hover:text-zinc-800! dark:hover:text-white! focus:outline-none"
            x-on:click="closeModal">
                <atom:icon.close />
            </button>
        </div>
    @endif

    {{ $slot }}
</dialog>
