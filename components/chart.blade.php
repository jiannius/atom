@props([
    'type' => 'bar',
    'data' => [],
    'color' => null,
    'max' => null,
    'min' => null,
])

@php
$factory = match ($type) {
    'area' => 'chartArea',
    'trend' => 'chartTrend',
    default => 'chartBar',
};

$height = str($attributes->get('class'))->is('*h-*')
    ? ''
    : ($type === 'trend' ? 'h-16' : 'h-64');
@endphp

<div
    {{ $attributes->class(['[:where(&)]:w-full', $height]) }}
    data-atom-chart
    data-atom-chart-type="{{ $type }}"
    x-data="{{ $factory }}({ data: @js($data), color: @js($color), max: @js($max), min: @js($min) })"
>
    {{-- ApexCharts draws into this node, and sizes itself from its PARENT (the chart's own sized box), not from the box that holds the chart.
         wire:ignore: the SVG is added by script, and a Livewire morph of the component would otherwise empty the node. --}}
    <div data-atom-chart-canvas wire:ignore></div>
</div>
