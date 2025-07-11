@props([
    'src' => null,
    'name' => null,
    'square' => true,
    'size' => null,
])

@php
$initials = $name ? str($name)->initials(match ($size) {
    'sm' => 1,
    'xs' => 1,
    default => 2,
}) : null;

$classes = Arr::toCssClasses([
    'bg-zinc-200 dark:bg-zinc-700 overflow-hidden shadow-sm',
    'text-zinc-400 font-bold leading-none',
    $square ? 'aspect-square' : '',
    match ($size) {
        'xl' => 'size-16 rounded-xl text-xl',
        'lg' => 'size-12 rounded-xl text-lg',
        'sm' => 'size-8 rounded-lg',
        'xs' => 'size-6 rounded-md text-xs',
        default => 'size-10 text-base rounded-lg',
    },
]);
@endphp

<figure {{ $attributes->class($classes) }}>
    @if ($src)
        <img src="{{ $src }}" class="w-full h-full object-cover">
    @else
        <div class="w-full h-full flex items-center justify-center">
            {{ $initials }}
        </div>
    @endif
</figure>