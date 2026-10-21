@props([
    'size' => 'default',
    'level' => null,
])

@php
$subheading = $attributes->has('data-atom-subheading');
$el = $level ? "h{$level}" : 'div';

if ($subheading) {
    $classes = Arr::toCssClasses([
        'text-fg-muted',
        'text-xs' => $size === 'xs',
        'text-sm' => $size === 'sm',
        'text-lg' => $size === 'lg',
        'text-xl' => $size === 'xl',
        'text-base' => $size === 'default',
    ]);
}
else {
    $classes = Arr::toCssClasses([
        'text-fg',
        '[&:has(+[data-atom-subheading])]:mb-1.5 [[data-atom-subheading]+&]:mt-1.5',
        '[&:has([data-atom-heading-actions])]:flex [&:has([data-atom-heading-actions])]:flex-wrap',
        '[&:has([data-atom-heading-actions])]:items-center [&:has([data-atom-heading-actions])]:justify-between',
        /* Every size needs both a size and a weight, or a size="sm" heading renders pixel-identical to the paragraph under it.
           The sizes are the type scale (12, 13, 14, 16 and 18px compact) and every one is weight 590. The bag still wins. */
        'text-xs font-semibold' => $size === 'xs',
        'text-sm font-semibold' => $size === 'sm',
        'text-base font-semibold' => $size === 'default',
        'text-lg font-semibold' => $size === 'lg',
        'text-xl font-semibold' => $size === 'xl',
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
        <div class="flex items-center gap-2 flex-wrap text-sm font-normal" data-atom-heading-actions>
            {{ $actions }}
        </div>
    @endisset
</{{ $el }}>