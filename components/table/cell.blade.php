@props([
    'align' => 'left',
    'checkbox' => null,
    'filler' => null,
    'muted' => false,
])

@php
$classes = Arr::toCssClasses([
    'py-3 px-4 whitespace-nowrap',
    $muted ? 'text-muted dark:text-muted-foreground' :'text-zinc-800 dark:text-zinc-200',
    $checkbox ? 'w-10' : '',
    match ($align) {
        'left' => 'text-left justify-start',
        'center' => 'text-center justify-center',
        'right' => 'text-right justify-end',
    },
]);
@endphp

@if ($checkbox)
    <td x-on:click.stop {{ $attributes->class($classes) }}>
        <atom:table.checkbox
        data-checkbox-id="{{ $checkbox }}"
        x-on:click="
            if ($wire._table.select_all) $wire.set('_table.select_all', false)
            $wire._table.checkboxes.toggle({{ js($checkbox) }})
        "
        x-bind:data-checked="$wire._table.select_all || $wire._table.checkboxes.includes({{ js($checkbox) }})" />
    </td>
@else
    <td {{ $attributes->class($classes) }}>
        {{ $slot->isEmpty() ? $filler : $slot }}
    </td>
@endif
