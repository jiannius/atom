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
    {{ $attributes->class(['w-full', $height]) }}
    data-atom-chart
    data-atom-chart-type="{{ $type }}"
    x-data="{{ $factory }}({ data: @js($data), color: @js($color), max: @js($max), min: @js($min) })"
></div>
