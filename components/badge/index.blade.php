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
        'lg' => 'text-base px-3 py-1 rounded-lg',
        'xs' => 'text-xs px-2 py-0.5 rounded-sm',
        default => 'text-sm px-2 py-0.5 rounded-md',
    },

    match ($color) {
        'red' => 'bg-red-100 text-red-500 border-red-300 dark:bg-red-100/30 dark:border-red-500 dark:text-red-300',
        'blue' => 'bg-sky-100 text-sky-500 border-sky-300 dark:bg-sky-100/30 dark:border-sky-500 dark:text-sky-300',
        'yellow' => 'bg-yellow-100 text-yellow-500 border-yellow-300 dark:bg-yellow-100/30 dark:border-yellow-500 dark:text-yellow-300',
        'orange' => 'bg-orange-100 text-orange-500 border-orange-300 dark:bg-orange-100/30 dark:border-orange-500 dark:text-orange-300',
        'green' => 'bg-green-100 text-green-500 border-green-300 dark:bg-green-100/30 dark:border-green-500 dark:text-green-300',
        'purple' => 'bg-purple-100 text-purple-500 border-purple-300 dark:bg-purple-100/30 dark:border-purple-500 dark:text-purple-300',
        'black' => 'bg-black text-zinc-100 border-black dark:bg-white dark:border-white dark:text-zinc-800',
        default => 'bg-zinc-100 text-zinc-500 border-zinc-200 dark:bg-zinc-100/30 dark:border-zinc-500 dark:text-zinc-300',
    },
]);
@endphp

<div {{ $attributes->class($classes) }} data-atom-badge>
    <div class="grow truncate">
        {{ t($label) }}
    </div>
</div>
