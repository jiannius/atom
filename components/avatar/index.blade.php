@props([
    'src' => null,
    'name' => null,
    'initial' => null,
    'square' => true,
    'size' => null,
])

@php
$initial ??= $name ? str($name)->initials(match ($size) {
    'sm' => 1,
    'xs' => 1,
    default => 2,
}) : null;

$classes = [
    'flex items-center justify-center',
    'bg-zinc-200 dark:bg-zinc-700 overflow-hidden shadow-sm',
    'border dark:border-zinc-600',
    'text-zinc-400 font-bold leading-none',

    $square ? 'aspect-square' : '',

    $square ? match ($size) {
        'xl' => 'rounded-xl',
        'lg' => 'rounded-xl',
        'sm' => 'rounded-lg',
        'xs' => 'rounded-md',
        default => 'rounded-lg',
    } : 'rounded-full',

    match ($size) {
        'xl' => 'size-16',
        'lg' => 'size-12',
        'sm' => 'size-8',
        'xs' => 'size-6.5',
        default => 'size-10',
    },
];
@endphp

<figure @class([...$classes, match ($size) {
    'xl' => 'text-xl',
    'lg' => 'text-lg',
    'xs' => 'text-xs',
    default => 'text-base',
}]) data-atom-avatar>
    <atom:tooltip :content="$name" class="w-full h-full">
        @if ($src)
            <img src="{{ $src }}" class="w-full h-full object-cover">
        @else
            <div class="w-full h-full flex items-center justify-center cursor-default">
                {{ $initial }}
            </div>
        @endif
    </atom:tooltip>
</figure>
