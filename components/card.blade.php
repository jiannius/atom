@props([
    'inset' => false,
    'subtle' => false,
    'divided' => false,
    'variant' => null,
    'heading' => null,
    'data' => null,
    'indicator' => null,
    'trend' => null,
    'type' => null,
    'color' => null,
    'max' => null,
    'min' => null,
])

@php
/* A panel: a fill and a hairline (the base border colour), no shadow. `subtle` is the sunken fill. */
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

        {{ $slot }}
    @endif
</div>