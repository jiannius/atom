@props([
    'inset' => false,
    'subtle' => false,
    'divided' => false,
    'variant' => null,
    'heading' => null,
    'level' => 2,
    'data' => null,
    'indicator' => null,
    'trend' => null,
    'type' => null,
    'color' => null,
    'max' => null,
    'min' => null,
])

{{-- A card: a fill and a hairline (the base border colour), no shadow. `subtle` is the sunken fill. On the default variant `heading` (a
     string through t(), or an `<x-slot:heading>` for markup) draws the box's title, a 12px muted one, at `level` 2, 3 or 4 (default 2),
     with an `actions` slot (one link or ghost button, 12px) and a `menu` slot (`<atom:menu.item>`s behind a ghost ⋯) at the end of the
     row. The row is drawn only with one of the three. It is the first row of a `divided` card and keeps the card's 16px of padding on an
     `inset` one. The `stats` and `chart` variants print `heading` as their own label and take none of this. A full-bleed box (a list)
     is `<atom:panel>`. --}}
@php
$classes = Arr::toCssClasses([
    '[:where(&)]:relative rounded-panel border overflow-auto',
    $divided
        ? 'divide-y divide-border '.($inset ? '' : '[&>div]:p-4')
        : ($inset ? '' : 'p-4'),

    $subtle ? 'bg-surface-sunken' : 'bg-surface',

    match ($variant) {
        'stats' => 'h-32 overflow-hidden transition-colors hover:bg-surface-sunken',
        'chart' => 'h-[350px]',
        default => '',
    },
]);

/* the heading row's own edge: the card's padding on an inset card, 12px to the body otherwise (a divided card pads and rules its child
   divs). The 12px is only there when the card does not space its children itself: a `grid` or `flex` card (its own `gap-*` is then the
   space) or a card with a `space-y-*` class (any variant of it, `md:space-y-4` too) gets its own spacing and not that plus 12px. The
   margin is a variant of the row, scoped to a parent without those classes, because a margin at no specificity ties with a space-y rule
   and wins by order, so the host's `space-y-6` would read 12px. */
$headingClasses = Arr::toCssClasses([
    'px-4 pt-4 [:not(.grid,.flex,[class*=space-y-])>&]:pb-3' => $inset && ! $divided,
    'p-4' => $inset && $divided,
    '[:not(.grid,.flex,[class*=space-y-])>&]:mb-3' => ! $inset && ! $divided,
]);

$merges = [
    'data-atom-card' => true,
    'data-atom-card-inset' => $inset ? true : null,
];
@endphp

<div {{ $attributes->class($classes)->merge($merges) }}>
    @if ($variant === 'stats')
        <div class="absolute inset-0 p-4" style="z-index: 2">
            <atom:subheading>{{ t($heading) }}</atom:subheading>

            <div class="text-2xl font-semibold tabular-nums">
                {{ $data }}
            </div>

            @if ($indicator)
                <div class="flex items-center gap-1 {{ $indicator > 0 ? 'text-success-fg' : 'text-danger-fg' }}">
                    <x-dynamic-component :component="'atom::icon.'.($indicator > 0 ? 'arrow-up' : 'arrow-down')" class="size-4" />
                    <div class="font-medium tabular-nums">{{ abs($indicator).'%' }}</div>
                </div>
            @endif
        </div>

        @if ($trend)
            <div class="absolute left-0 right-0 bottom-0 h-1/2" style="z-index: 1">
                <div x-data="chartTrend({
                    data: @js($trend),
                    color: @js(match (true) {
                        $indicator > 0 => 'success',
                        $indicator < 0 => 'danger',
                        default => 'neutral',
                    }),
                })"></div>
            </div>
        @endif
    @elseif ($variant === 'chart')
        <div class="space-y-4 flex flex-col w-full h-full">
            <atom:subheading>{{ t($heading) }}</atom:subheading>

            <div class="grow">
                <atom:chart :type="$type ?? 'bar'" :data="$data" :color="$color" :max="$max" :min="$min" class="h-full"/>
            </div>
        </div>
    @else
        @isset($cover)
            <figure {{ $cover->attributes->class([
                'first:rounded-t-panel last:rounded-b-panel overflow-hidden',
                '[&>*:not(video)]:transistion-transform [&>*:not(video)]:duration-200 [&>*:not(video):hover]:scale-105',
                $subtle ? 'bg-neutral-bg' : 'bg-surface-sunken',
                $inset ? '' : '-mx-4 -mt-4 mb-4',
            ]) }}>
                {{ $cover }}
            </figure>
        @endisset

        <atom:_heading-row
            variant="card"
            :level="$level"
            :title="$heading"
            :actions="$actions ?? null"
            :menu="$menu ?? null"
            :class="$headingClasses"
        />

        {{ $slot }}
    @endif
</div>