@props([
    'src' => null,
    'icon' => null,
    'file' => null,
])

@php
$src ??= $file?->url;
$icon ??= 'file';

// The src is a URL the browser loads, so only http(s) and scheme-less (relative,
// protocol-relative) values get through; anything else (javascript:, vbscript:, data:)
// falls back to the icon. The scheme is read the way the WHATWG URL parser does: after
// decoding entities (`&#106;avascript:` is a script URL if a host prints it unencoded),
// with tabs and newlines removed anywhere and leading control characters and spaces
// dropped. A value that is still decoding after five rounds is refused, and so is one that
// still holds a numeric reference (`&#1;` before the scheme, or `&#106avascript:` with no
// semicolon) once decoding is done: PHP leaves those alone and a browser decodes them.
if ($src) {
    $probe = (string) $src;
    $settled = false;

    for ($round = 0; $round < 5 && !$settled; $round++) {
        $decoded = html_entity_decode($probe, ENT_QUOTES | ENT_HTML5);
        $settled = $decoded === $probe;
        $probe = $decoded;
    }

    $probe = ltrim(str_replace(["\t", "\n", "\r"], '', $probe), "\x00..\x20");
    $scheme = preg_match('/^([a-z][a-z0-9+.\-]*):/i', $probe, $matches) ? strtolower($matches[1]) : null;

    if (!$settled || str_contains($probe, '&#') || ($scheme !== null && !in_array($scheme, ['http', 'https'], true))) {
        $src = null;
    }
}

$urlpath = $src ? (parse_url($src)['path'] ?? '') : '';
$type = Arr::pick([
    'image' => str($urlpath)->endsWith(['.jpg', '.jpeg', '.png', '.webp', '.gif', '.svg', '.tiff']),
    'video' => str($urlpath)->endsWith(['.mp4', '.ogg', '.mpeg', '.avi']),
    'youtube' => str($urlpath)->startsWith(['/watch']),
    'icon' => !empty($icon),
]);

$classes = Arr::toCssClasses([
    'w-full h-full object-contain' => in_array($type, ['image', 'video']),
    'w-full h-full' => $type === 'youtube',
    'flex items-center justify-center w-full h-full text-muted dark:text-muted-foreground' => $type === 'icon',
]);

$merges = [
    ...($type === 'youtube' ? [
        'title' => t('Embedded video'),
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
    <img src="{{ $src }}" {{ $attributes->class($classes)->only('class') }}>
@elseif ($type === 'video')
    <video {{ $attributes->class($classes)->merge($merges) }}>
        <source src="{{ $src }}" type="video/mp4">
    </video>
@elseif ($type === 'youtube')
    <iframe src="{{ $src }}" {{ $attributes->class($classes)->merge($merges) }}></iframe>
@elseif ($type === 'icon')
    <div {{ $attributes->class($classes)->merge($merges) }}>
        @if (str($icon)->startsWith('<svg')) {!! $icon !!}
        @else <x-dynamic-component :component="'atom::icon.'.$icon" />
        @endif
    </div>
@endif
