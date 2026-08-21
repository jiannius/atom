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
        'text-xs' => $size === 'xs',
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
        // Every size needs both a size and a weight. lg/xl used to set only a
        // size, so they inherited body weight 400, and xs/sm set neither —
        // a size="sm" heading rendered pixel-identical to the paragraph under
        // it. The bag still wins, so a call site can override either half.
        'text-xs font-medium' => $size === 'xs',
        'text-sm font-medium' => $size === 'sm',
        'text-base font-medium' => $size === 'default',
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
        <div class="flex items-center gap-2 flex-wrap text-base" data-atom-heading-actions>
            {{ $actions }}
        </div>
    @endisset
</{{ $el }}>