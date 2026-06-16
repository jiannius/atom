@props([
    'label' => null,
    'active' => null,   // Alpine expression, e.g. "isActive('bold')" — drives aria-pressed + active styling
])

<atom:tooltip :content="$label">
    <button
    type="button"
    @if ($label) aria-label="{{ t($label) }}" @endif
    @if ($active) x-bind:aria-pressed="{{ $active }}" x-bind:data-active="{{ $active }}" @endif
    {{ $attributes->class([
        'size-8 rounded-md flex items-center justify-center',
        'hover:bg-zinc-100 dark:hover:bg-zinc-700',
        '[&[data-active=true]]:bg-zinc-100 dark:[&[data-active=true]]:bg-zinc-700',
    ]) }}>
        {{ $slot }}
    </button>
</atom:tooltip>
