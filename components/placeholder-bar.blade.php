@props([
    'size' => null,
])

@php
$split = str($size)->split('/x/')->filter();
$width = $split->first() ?? '100%';
$height = $split->count() > 1 ? $split->last() : null;

if ($width && ! str($width)->is('*%')) $width = $width.'px';
if ($height && ! str($height)->is('*%')) $height = $height.'px';

// a caller `style` is appended last so it wins
$style = Arr::toCssStyles(array_filter([
    'width: '.$width,
    'height: '.($height ?? '10px'),
    $attributes->get('style'),
]));
@endphp

<div
style="{{ $style }}"
{{ $attributes->class([
    'rounded-pill',
    $attributes->get('class', 'bg-border-strong'),
])->except(['size', 'style']) }}
></div>
