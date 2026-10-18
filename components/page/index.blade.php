@props([
    'width' => null,
])

{{-- The page body: the only thing that pads a page (a shell's <main> is unpadded). A 1240px column, 26px above, 120px
     below (room for a sticky save bar), a 22px gap between its blocks, and a 28px gutter from lg (16px below; the top-nav
     shell sets 24px in atom.css so the page lines up with the bar). `width`: `narrow` is a 980px form column, `full` has
     no cap. Put <atom:page.header> first, then stats, tabs and content as siblings. --}}
@php
$width = in_array($width, ['narrow', 'full'], true) ? $width : null;

$classes = Arr::toCssClasses([
    'grid w-full grid-cols-[minmax(0,1fr)] content-start gap-[22px] pt-[26px] pb-[120px]',
    'ps-[max(1rem,env(safe-area-inset-left,0px))] pe-[max(1rem,env(safe-area-inset-right,0px))]',
    'lg:ps-[max(1.75rem,env(safe-area-inset-left,0px))] lg:pe-[max(1.75rem,env(safe-area-inset-right,0px))]',

    match ($width) {
        'narrow' => 'max-w-[980px]',
        'full' => '',
        default => 'max-w-[1240px]',
    },
]);
@endphp

<div {{ $attributes->class($classes) }} data-atom-page>
    {{ $slot }}
</div>
