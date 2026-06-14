@props([
    'inset' => false,
    'cols' => null,
])

@php
$submit = $attributes->wire('submit')->value() ?: 'submit';
@endphp

<form {{ $attributes->class([
    'group/form relative',
    'flex flex-col gap-6' => !$inset,
])->merge(['wire:submit' => $submit]) }}
data-atom-form>
    @if ($cols)
        <atom:form.grid :cols="$cols">{{ $slot }}</atom:form.grid>
    @else
        {{ $slot }}
    @endif

    <div
    wire:loading.flex
    wire:target="{{ $submit }}"
    role="status"
    class="pointer-events-none absolute inset-0 z-1 flex justify-end p-4 text-zinc-500 dark:text-white">
        <span class="sr-only">{{ t('Saving') }}</span>
        <atom:icon.loading class="size-5" />
    </div>
</form>
