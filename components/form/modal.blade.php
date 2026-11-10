@props([
    'name' => null,
    'cols' => 'auto',
    'submit' => 'Save',
    'dismissible' => true,
    'escapable' => true,
    'closeable' => true,
    'method' => 'submit',
    'disabled' => false,
])

{{-- A width the caller sets (`w-*` or `max-w-*`, under any variant, not `min-w-*`; see Attributes::hasWidth()) replaces the one `cols`
     derives: a width class that sits beside `max-w-xl` loses to it in Tailwind's rule order. A width that only applies under a variant
     (`md:w-96`) replaces it at every size, so give the base width too.
     `submit` is the Save button's LABEL; `method` names the component method the form calls (default `submit`), and is also the
     method whose run the button's loading state follows. A name that is not a plain method name falls back to `submit`. --}}
@php
$method = is_string($method) && preg_match('/^[A-Za-z_][A-Za-z0-9_]*\z/', $method) ? $method : 'submit';

$hasOwnWidth = \Jiannius\Atom\Services\Attributes::hasWidth($attributes);

/* `start` is content at the footer's start (a status line, a Delete button) and moves Save to the end of the bar, last in the end group.
   Without it the order is the one it always was: Save first, then `actions`, then `delete`. A slot of only false @if blocks is not content. */
$hasStart = isset($start) && $start->hasActualContent();

$width = $hasOwnWidth ? '' : match ((string) $cols) {
    'auto', '2' => 'max-w-2xl',
    '3' => 'max-w-4xl',
    default => 'max-w-xl',
};
@endphp

<atom:modal :name="$name" :dismissible="$dismissible" :escapable="$escapable" :closeable="$closeable" {{ $attributes->class($width) }}>
    <atom:form wire:submit="{{ $method }}" :disabled="$disabled">
        <atom:form.grid :cols="$cols">
            {{ $slot }}
        </atom:form.grid>

        <atom:form.actions sticky>
            @if ($hasStart)
                <x-slot:start :attributes="$start->attributes">{{ $start }}</x-slot:start>

                <div class="ms-auto flex min-w-0 flex-wrap items-center justify-end gap-3" data-atom-form-modal-actions>
                    @isset($actions)
                        {{ $actions }}
                    @endisset
                    @isset($delete)
                        {{ $delete }}
                    @endisset

                    <atom:button type="submit">{{ t($submit) }}</atom:button>
                </div>
            @else
                <atom:button type="submit">{{ t($submit) }}</atom:button>
                @isset($actions)
                    <div class="flex min-w-0 flex-wrap items-center justify-end gap-3" data-atom-form-modal-actions>
                        {{ $actions }}
                        @isset($delete)
                            {{ $delete }}
                        @endisset
                    </div>
                @elseif (isset($delete))
                    {{ $delete }}
                @endisset
            @endif
        </atom:form.actions>
    </atom:form>
</atom:modal>
