@props([
    'src' => null,
    'icon' => null,
    'file' => null,
])

@php
$src ??= $file?->url;
$icon ??= 'file';

// Raw SVG markup is only printed from an Htmlable the caller built on purpose. A plain
// string is a name, so one that looks like markup (a record's value) is not an icon at all.
if (is_string($icon) && str_starts_with(ltrim($icon), '<')) {
    $icon = 'file';
}

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

// A video is YouTube only when the URL's host is one of YouTube's: a path of `/watch` on any other host is just a link.
// What is framed is rebuilt from the 11-character video id alone (privacy-enhanced host), never the URL as given, so
// nothing else in it (a query, a fragment, another host) reaches the iframe.
$youtubeId = null;

if ($src) {
    $parts = parse_url((string) $src) ?: [];
    $host = strtolower((string) ($parts['host'] ?? ''));
    $path = (string) ($parts['path'] ?? '');
    $idPattern = '[A-Za-z0-9_-]{11}';

    if (in_array($host, ['youtube.com', 'www.youtube.com', 'm.youtube.com', 'www.youtube-nocookie.com'], true)) {
        if ($path === '/watch') {
            parse_str((string) ($parts['query'] ?? ''), $query);
            $candidate = $query['v'] ?? null;
            $youtubeId = is_string($candidate) && preg_match("/^{$idPattern}$/", $candidate) ? $candidate : null;
        }
        elseif (preg_match("#^/(?:embed|shorts|live)/({$idPattern})/?$#", $path, $matches)) {
            $youtubeId = $matches[1];
        }
    }
    elseif ($host === 'youtu.be' && preg_match("#^/({$idPattern})/?$#", $path, $matches)) {
        $youtubeId = $matches[1];
    }
}

$urlpath = $src ? (parse_url($src)['path'] ?? '') : '';
$type = Arr::pick([
    'image' => str($urlpath)->endsWith(['.jpg', '.jpeg', '.png', '.webp', '.gif', '.svg', '.tiff']),
    'video' => str($urlpath)->endsWith(['.mp4', '.ogg', '.mpeg', '.avi']),
    'youtube' => $youtubeId !== null,
    'icon' => !empty($icon),
]);

$classes = Arr::toCssClasses([
    'w-full h-full object-contain' => in_array($type, ['image', 'video']),
    'w-full h-full' => $type === 'youtube',
    'flex items-center justify-center w-full h-full text-fg-subtle' => $type === 'icon',
]);

$merges = [
    ...($type === 'youtube' ? [
        'title' => t('Embedded video'),
        'frameborder' => '0',
        'referrerpolicy' => 'strict-origin-when-cross-origin',
        'allowfullscreen' => true,
        'loading' => 'lazy',
    ] : []),
    ...($type === 'video' ? [
        'controls' => true,
    ] : []),
];
@endphp

@if ($type === 'image')
    <img src="{{ $src }}" {{ $attributes->class($classes) }}>
@elseif ($type === 'video')
    <video {{ $attributes->class($classes)->merge($merges) }}>
        <source src="{{ $src }}" type="video/mp4">
    </video>
@elseif ($type === 'youtube')
    {{-- sandboxed: the player needs its scripts and its own origin, nothing else of the page, and a caller cannot loosen it --}}
    <iframe src="https://www.youtube-nocookie.com/embed/{{ $youtubeId }}" {{ $attributes->except(['sandbox', 'srcdoc', 'src'])->class($classes)->merge($merges) }} sandbox="allow-scripts allow-same-origin allow-presentation allow-popups allow-popups-to-escape-sandbox"></iframe>
@elseif ($type === 'icon')
    <div {{ $attributes->class($classes)->merge($merges) }}>
        @if ($icon instanceof \Illuminate\Contracts\Support\Htmlable) {{ $icon }}
        @else <x-dynamic-component :component="'atom::icon.'.$icon" />
        @endif
    </div>
@endif
