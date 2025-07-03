@props([
    'size' => 'default',
    'level' => null,
])

@php
$subheading = $attributes->has('data-atom-subheading');
$el = $level ? "h{$level}" : 'div';

if ($subheading) {
    $classes = Arr::toCssClasses([
        'text-zinc-500',
        'text-sm' => $size === 'sm',
        'text-lg' => $size === 'lg',
        'text-xl' => $size === 'xl',
        'text-base' => $size === 'default',
    ]);
}
else {
    $classes = Arr::toCssClasses([
        'dark:text-white',
        '[&:has(+[data-atom-subheading])]:mb-1.5 [[data-atom-subheading]+&]:mt-1.5',
        '[&:has([data-atom-heading-actions])]:flex [&:has([data-atom-heading-actions])]:flex-wrap',
        '[&:has([data-atom-heading-actions])]:items-center [&:has([data-atom-heading-actions])]:justify-between',
        'font-medium' => $size === 'default',
        'text-base' => $size === 'default',
        'text-lg' => $size === 'lg',
        'text-xl' => $size === 'xl',
    ]);
}

$styles = Arr::toCssStyles([
    'font-size: '.str($size)->finish('px') => !in_array($size, ['default', 'lg', 'xl', 'sm', 'xs']) && $size,
]);

$merges = [
    'style' => $styles,
    'data-atom-heading' => !$subheading,
];
@endphp

<{{ $el }} {{ $attributes->class($classes)->merge($merges) }}>
    {{ $slot }}

    @isset ($actions)
        <div class="flex items-center gap-2 flex-wrap" data-atom-heading-actions>
            {{ $actions }}
        </div>
    @endisset
</{{ $el }}>