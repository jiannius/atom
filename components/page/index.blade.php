@props([
    'width' => null,
])

{{-- The gutter is a pair of variables (--atom-gutter-s and --atom-gutter-e) as well as the padding, and the bottom room is one
     (--atom-page-pb, 120px, which is also the page's bottom padding: set the variable on the page, `class="[--atom-page-pb:80px]"`, and padding and bar follow; the default is at zero specificity, so yours wins), so a save bar inside the page (form.actions with `bar`) can take the bottom room as its own and end the page 0.75rem above the bottom edge.
     The page body: the only thing that pads a page (a shell's <main> is unpadded). A 1240px column, centred in the space its shell gives
     it (`mx-auto`; `full` has no cap, so nothing to centre; the 1240px is the variable --atom-page-max (its fallback), which the top-nav shell sets to its own
     1248px in atom.css's `@layer base`, so a centred page there starts where the bar and the crumbs do, and a utility of yours on the page,
     `class="[--atom-page-max:1100px]"`, wins), 26px above, 120px below (room for a sticky save bar), a 22px gap between its
     blocks, and a 28px gutter from lg (16px below; the top-nav shell sets 24px in atom.css so the page lines up with the bar). `width`:
     `narrow` is a 980px form column, `full` has no cap. Put <atom:page.header> first, then stats, tabs and content as siblings. --}}
@php
$width = in_array($width, ['narrow', 'full'], true) ? $width : null;

$classes = Arr::toCssClasses([
    'grid w-full grid-cols-[minmax(0,1fr)] content-start gap-[22px] pt-[26px] pb-(--atom-page-pb)',
    '[--atom-gutter-s:max(1rem,env(safe-area-inset-left,0px))] [--atom-gutter-e:max(1rem,env(safe-area-inset-right,0px))]',
    'lg:[--atom-gutter-s:max(1.75rem,env(safe-area-inset-left,0px))] lg:[--atom-gutter-e:max(1.75rem,env(safe-area-inset-right,0px))]',
    'ps-(--atom-gutter-s) pe-(--atom-gutter-e) [:where(&)]:[--atom-page-pb:120px]',

    match ($width) {
        'narrow' => 'mx-auto max-w-[980px]',
        'full' => '',
        default => 'mx-auto max-w-[var(--atom-page-max,1240px)]',
    },
]);
@endphp

<div {{ $attributes->class($classes) }} data-atom-page>
    {{ $slot }}
</div>
