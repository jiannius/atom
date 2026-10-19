@props([
    'src' => null,
    'name' => null,
    'initial' => null,
    'square' => true,
    'size' => null,
])

@php
$initial ??= $name ? str($name)->initials()->take(match ($size) {
    'sm' => 1,
    'xs' => 1,
    default => 2,
})->toString() : null;

/* A tinted square (or circle) with a hairline: initials on the neutral tone, or the photo. 24, 28, 34, 48 and 64px. */
$classes = [
    'flex shrink-0 items-center justify-center',
    'bg-neutral-bg overflow-hidden border',
    'text-neutral-fg font-semibold leading-none',

    $square ? 'aspect-square' : '',

    $square ? match ($size) {
        'xl' => 'rounded-panel',
        'lg' => 'rounded-panel',
        default => 'rounded-control',
    } : 'rounded-full',

    match ($size) {
        'xl' => 'size-16',
        'lg' => 'size-12',
        'sm' => 'size-7',
        'xs' => 'size-6',
        default => 'size-[34px]',
    },
];
@endphp

<figure {{ $attributes->class([...$classes, match ($size) {
    'xl' => 'text-xl',
    'lg' => 'text-base',
    'sm', 'xs' => 'text-xs',
    default => 'text-sm',
}]) }} data-atom-avatar>
    <atom:tooltip :content="$name" class="w-full h-full">
        @if ($src)
            <img src="{{ $src }}" alt="{{ $name }}" class="w-full h-full object-cover">
        @else
            <div class="w-full h-full flex items-center justify-center cursor-default">
                {{ $initial }}
            </div>
        @endif
    </atom:tooltip>
</figure>
