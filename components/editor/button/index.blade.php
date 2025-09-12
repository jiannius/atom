@props([
    'label' => null,
    'icon' => null,
])

<atom:tooltip :content="$label">
    <button type="button" {{ $attributes->class([
        'size-8 rounded-md',
        'hover:border hover:border-zinc-200 hover:shadow-sm dark:hover:border-zinc-700/50',
        'hover:bg-zinc-100 dark:hover:bg-zinc-700',
        'flex items-center justify-center',
    ]) }}>
        {{ $slot }}
    </button>
</atom:tooltip>
