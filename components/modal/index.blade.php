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

// A dialog is content-sized, so a caller's max-w-* only ever capped a width
// nothing had set — <atom:modal class="max-w-screen-lg"> rendered at the
// min-w-sm floor (336px), with no error to notice. A max-w-* is a statement of
// intended width, so give it one to cap.
$hasMaxWidth = str($attributes->get('class'))->contains('max-w-');

$classes = Arr::toCssClasses([
    'group/modal',
    '[:where(&)]:max-w-full min-w-sm shadow-pop rounded-modal',
    'w-full' => $hasMaxWidth,
    'bg-surface-raised text-fg border border-border-strong transition-transform',
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

    /* An open slide-over is a full-height column, so a form in it takes the whole height and its pinned bar (form.actions
       `sticky`, `mt-auto`) sits at the foot of the panel whatever the content's height, and still sticks when the content scrolls. */
    '[&[data-atom-modal-slide][open]]:flex',
    '[&[data-atom-modal-slide][open]]:flex-col',
    '[&[data-atom-modal-slide][open]>form]:flex-1',
    '[&[data-atom-modal-slide-left][open]]:flex',
    '[&[data-atom-modal-slide-left][open]]:flex-col',
    '[&[data-atom-modal-slide-left][open]>form]:flex-1',
    '[&[data-atom-modal-slide-bottom][open]]:flex',
    '[&[data-atom-modal-slide-bottom][open]]:flex-col',
    '[&[data-atom-modal-slide-bottom][open]>form]:flex-1',
    '[&[data-atom-modal-slide-bottom]]:border-x-0',
    '[&[data-atom-modal-slide-bottom]]:border-b-0',
    '[&[data-atom-modal-slide-bottom]]:overflow-auto',
    '[&[data-atom-modal-slide-bottom]]:translate-y-full',
    '[&[data-atom-modal-slide-bottom][data-open]]:translate-y-0',
]);
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
    {{ $slot }}

    {{-- After the content, so showModal() puts the focus on the first field or action rather than on this button (a ring on
         the close button at every open); it is placed by `absolute`, so the order is the keyboard's alone. --}}
    @if ($closeable)
        <div class="absolute top-3 end-3">
            <button
            type="button"
            aria-label="{{ t('Close') }}"
            class="flex h-control aspect-square items-center justify-center rounded-control text-fg-subtle transition-colors hover:bg-fg/[0.06] hover:text-fg"
            x-on:click="closeModal">
                <atom:icon.close class="size-4" />
            </button>
        </div>
    @endif
</dialog>
