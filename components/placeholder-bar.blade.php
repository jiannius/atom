@props([
    'size' => null,
])

@php
$split = str($size)->split('/x/')->filter();
$width = $split->first() ?? '100%';
$height = $split->count() > 1 ? $split->last() : null;

if ($width && ! str($width)->is('*%')) $width = $width.'px';
if ($height && ! str($height)->is('*%')) $height = $height.'px';
@endphp

<div 
style="width: {{ $width }}; height: {{ $height ?? '10px' }};"
{{ $attributes->class([
    'rounded-xl',
    $attributes->get('class', 'bg-zinc-300'),
])->except('size') }}
></div>
