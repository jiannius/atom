@props([
    'name' => null,
    'cols' => 'auto',
    'submit' => 'Save',
    'dismissible' => true,
    'closeable' => true,
])

@php
$width = match ((string) $cols) {
    'auto', '2' => 'max-w-2xl',
    '3' => 'max-w-4xl',
    default => 'max-w-xl',
};
@endphp

<atom:modal :name="$name" :dismissible="$dismissible" :closeable="$closeable" {{ $attributes->class($width) }}>
    <atom:form>
        <atom:form.grid :cols="$cols">
            {{ $slot }}
        </atom:form.grid>

        <atom:form.actions sticky>
            <atom:button type="submit">{{ t($submit) }}</atom:button>
            @isset($delete)
                {{ $delete }}
            @endisset
        </atom:form.actions>
    </atom:form>
</atom:modal>
