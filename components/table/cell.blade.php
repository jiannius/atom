@props([
    'align' => 'left',
    'checkbox' => null,
    'filler' => null,
])

@php
$classes = Arr::toCssClasses([
    'py-3 px-4 whitespace-nowrap',
    'text-zinc-800 dark:text-zinc-200',
    match ($align) {
        'left' => 'text-left justify-start',
        'center' => 'text-center justify-center',
        'right' => 'text-right justify-end',
    },
]);
@endphp

@if ($checkbox)
    <td x-on:click.stop valign="{{ $valign }}">
        <div {{ $attributes }}>
            <div
            x-on:click="checkboxes.toggle(@js($checkbox))"
            x-on:select="checkboxes.push(@js($checkbox))"
            x-bind:class="checkboxes.includes(@js($checkbox)) ? 'border-primary bg-primary' : 'border-zinc-300 bg-white'"
            x-bind:data-checked="checkboxes.includes(@js($checkbox))"
            data-atom-cell-checkbox
            class="w-6 h-6 rounded-md border flex items-center justify-center cursor-pointer">
                <atom:icon.check class="text-white size-4"/>
            </div>
        </div>
    </td>
@else
    <td {{ $attributes->class($classes) }}>
        {{ $slot->isEmpty() ? $filler : $slot }}
    </td>
@endif
