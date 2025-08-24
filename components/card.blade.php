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
$classes = Arr::toCssClasses([
    'relative rounded-xl border shadow-xs overflow-auto',
    $divided
        ? 'divide-y divide-zinc-200 dark:divide-zinc-700 '.($inset ? '' : '[&>div]:p-6')
        : ($inset ? '' : 'p-6'),

    $subtle ? 'bg-zinc-100 dark:bg-zinc-700/30 border-transparent' : 'bg-white dark:bg-zinc-800 border-zinc-200 dark:border-zinc-700',

    match ($variant) {
        'stats' => 'h-36 overflow-hidden',
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
        <div class="absolute inset-0 p-6" style="z-index: 2">
            <atom:subheading>{{ t($heading) }}</atom:subheading>

            <div class="text-3xl font-bold">
                {{ $data }}
            </div>

            @if ($indicator)
                <div class="flex items-center gap-2 {{ $indicator > 0 ? 'text-green-500' : 'text-red-500' }}">
                    <x-dynamic-component :component="'atom::icon.'.($indicator > 0 ? 'arrow-up' : 'arrow-down')" class="size-5" />
                    <div class="font-medium">{{ abs($indicator).'%' }}</div>
                </div>
            @endif
        </div>

        @if ($trend)
            <div class="absolute left-0 right-0 bottom-0 h-1/2" style="z-index: 1">
                <div x-data x-chart="{
                    type: 'trend',
                    data: @js($trend),
                    color: @js($indicator > 0 ? 'green' : ($indicator < 0 ? 'red' : null)),
                }"></div>
            </div>
        @endif
    @elseif ($variant === 'chart')
        <div class="space-y-4 flex flex-col w-full h-full">
            <atom:subheading>{{ t($heading) }}</atom:subheading>

            <div class="grow">
                <div x-data x-chart="{
                    type: @js($type),
                    data: @js($data),
                    color: @js($color),
                    max: @js($max),
                    min: @js($min),
                }"></div>
            </div>
        </div>
    @else
        @isset($cover)
            <figure {{ $cover->attributes->class([
                'first:rounded-t-lg last:rounded-b-lg overflow-hidden',
                '[&>*:not(video)]:transistion-transform [&>*:not(video)]:duration-200 [&>*:not(video):hover]:scale-105',
                $subtle ? 'bg-zinc-200 dark:bg-zinc-700/60' : 'bg-zinc-100 dark:bg-zinc-700/30',
                $inset ? '' : '-mx-6 -mt-6 mb-6',
            ]) }}>
                {{ $cover }}
            </figure>
        @endisset

        {{ $slot }}
    @endif
</div>