@props([
    'status' => null,
    'size' => null,
    'icon' => null,
    'color' => null,
    'label' => null,
])

@php
$color ??= (is_enum($status) ? $status->color() : data_get($status, 'color'));
$label ??= (is_enum($status) ? $status->label() : data_get($status, 'label')) ?? $slot->toHTML();

$classes = Arr::toCssClasses([
    'inline-flex items-center justify-center font-medium whitespace-nowrap border max-w-xs',

    match ($size) {
        'xs' => 'text-xs px-2 py-0.5 rounded',
        'lg' => 'text-base px-3 py-1 rounded',
        default => 'text-sm px-2 py-0.5 rounded',
    },

    match ($color) {
        'red' => 'bg-red-100 text-red-500 border-red-300',
        'blue' => 'bg-sky-100 text-sky-500 border-sky-300',
        'yellow' => 'bg-yellow-100 text-yellow-500 border-yellow-300',
        'orange' => 'bg-orange-100 text-orange-500 border-orange-300',
        'green' => 'bg-green-100 text-green-500 border-green-300',
        'purple' => 'bg-purple-100 text-purple-500 border-purple-300',
        'black' => 'bg-black text-zinc-100 border-black',
        'gray' => 'bg-zinc-100 text-zinc-500 border-zinc-200',
        default => 'bg-zinc-100 text-zinc-500 border-zinc-200',
    },
]);
@endphp

<div {{ $attributes->class($classes) }} data-atom-badge>
    <div class="grow truncate">
        {{ t($label) }}
    </div>
</div>
