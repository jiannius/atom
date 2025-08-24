@props([
    'src' => null,
    'optimized' => null,
    'icon' => null,
    'file' => null,
])

@php
$src ??= $file?->endpoint;
$optimized ??= $file?->endpoint_o;
$icon ??= 'file';

$url = parse_url($src);
$urlpath = $url['path'];
$type = Arr::pick([
    'image' => str($urlpath)->endsWith(['.jpg', '.jpeg', '.png', '.webp', '.gif', '.svg', '.tiff']),
    'video' => str($urlpath)->endsWith(['.mp4', '.ogg', '.mpeg', '.avi']),
    'youtube' => str($urlpath)->startsWith(['/watch']),
    'icon' => !empty($icon),
]);

$classes = Arr::toCssClasses([
    'w-full h-full object-contain' => in_array($type, ['image', 'video']),
    'w-full h-full' => $type === 'youtube',
    'flex items-center justify-center w-full h-full text-muted' => $type === 'icon',
]);

$merges = [
    ...($type === 'youtube' ? [
        'frameborder' => '0',
        'referrerpolicy' => 'strict-origin-when-cross-origin',
        'allowfullscreen' => true,
    ] : []),
    ...($type === 'video' ? [
        'controls' => true,
    ] : []),
];
@endphp

@if ($type === 'image')
    @if ($optimized)
        <object data="{!! $optimized !!}" {{ $attributes->class($classes)->only('class') }}>
            <img src="{!! $src !!}" {{ $attributes->class($classes)->only('class') }}>
        </object>
    @else
        <img src="{!! $src !!}" {{ $attributes->class($classes)->only('class') }}>
    @endif
@elseif ($type === 'video')
    <video {{ $attributes->class($classes)->merge($merges) }}>
        <source src="{{ $src }}" type="video/mp4">
    </video>
@elseif ($type === 'youtube')
    <iframe src="{{ $src }}" {{ $attributes->class($classes)->merge($merges) }}></iframe>
@elseif ($type === 'icon')
    <div {{ $attributes->class($classes)->merge($merges) }}>
        <x-dynamic-component :component="'atom::icon.'.$icon" class="size-10" />
    </div>
@endif
