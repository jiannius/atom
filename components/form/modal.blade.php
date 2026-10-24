@props([
    'name' => null,
    'cols' => 'auto',
    'submit' => 'Save',
    'dismissible' => true,
    'escapable' => true,
    'closeable' => true,
    'method' => 'submit',
])

{{-- A width the caller sets (`w-*` or `max-w-*`, under any variant, not `min-w-*`) replaces the one `cols` derives: a width class that
     sits beside `max-w-xl` loses to it in Tailwind's rule order. `[&_svg]:w-4` and `*:w-4` style a child, so they are not a width for the dialog.
     `submit` is the Save button's LABEL; `method` names the component method the form calls (default `submit`), and is also the
     method whose run the button's loading state follows. A name that is not a plain method name falls back to `submit`. --}}
@php
$method = is_string($method) && preg_match('/^[A-Za-z_][A-Za-z0-9_]*\z/', $method) ? $method : 'submit';

$hasOwnWidth = collect(preg_split('/\s+/', trim((string) $attributes->get('class'))))
    ->contains(fn ($token) => preg_match('/^(?:(?![\[*])(?:[^\s:\[\]]|\[[^\]\s]*\])+:)*!?(?:max-)?w-/', $token));

$width = $hasOwnWidth ? '' : match ((string) $cols) {
    'auto', '2' => 'max-w-2xl',
    '3' => 'max-w-4xl',
    default => 'max-w-xl',
};
@endphp

<atom:modal :name="$name" :dismissible="$dismissible" :escapable="$escapable" :closeable="$closeable" {{ $attributes->class($width) }}>
    <atom:form wire:submit="{{ $method }}">
        <atom:form.grid :cols="$cols">
            {{ $slot }}
        </atom:form.grid>

        <atom:form.actions sticky>
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
        </atom:form.actions>
    </atom:form>
</atom:modal>
