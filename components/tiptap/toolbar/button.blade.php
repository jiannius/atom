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
        'h-control aspect-square rounded-nav flex items-center justify-center text-fg-muted',
        'hover:bg-fg/5 hover:text-fg',
        '[&[data-active=true]]:bg-fg/10 [&[data-active=true]]:text-fg',
    ]) }}>
        {{ $slot }}
    </button>
</atom:tooltip>
