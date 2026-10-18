@props([
    'width' => null,
])

{{-- The gutter is a pair of variables (--atom-gutter-s and --atom-gutter-e) as well as the padding, and the bottom room is one
     (--atom-page-pb, the same 120px), so a save bar inside the page (form.actions with `bar`) can bleed to exactly these edges and end the page.
     The page body: the only thing that pads a page (a shell's <main> is unpadded). A 1240px column, 26px above, 120px
     below (room for a sticky save bar), a 22px gap between its blocks, and a 28px gutter from lg (16px below; the top-nav
     shell sets 24px in atom.css so the page lines up with the bar). `width`: `narrow` is a 980px form column, `full` has
     no cap. Put <atom:page.header> first, then stats, tabs and content as siblings. --}}
@php
$width = in_array($width, ['narrow', 'full'], true) ? $width : null;

$classes = Arr::toCssClasses([
    'grid w-full grid-cols-[minmax(0,1fr)] content-start gap-[22px] pt-[26px] pb-[120px]',
    '[--atom-gutter-s:max(1rem,env(safe-area-inset-left,0px))] [--atom-gutter-e:max(1rem,env(safe-area-inset-right,0px))]',
    'lg:[--atom-gutter-s:max(1.75rem,env(safe-area-inset-left,0px))] lg:[--atom-gutter-e:max(1.75rem,env(safe-area-inset-right,0px))]',
    'ps-(--atom-gutter-s) pe-(--atom-gutter-e) [--atom-page-pb:120px]',

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
