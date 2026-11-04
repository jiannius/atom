@props([
    'content' => null,
    'markdown' => null,
    'size' => null,
])

@php
/* One of three sources, in this order: a markdown string (converted and then run through Content::sanitize(), the gate for HTML
   from outside; when it is refused or empties out the text is printed as it is, so a message never goes blank), stored Tiptap JSON
   or HTML (Content::render()), or the slot, which is the host's own markup and is printed as written. Nothing reaches the page
   unescaped without passing the Tiptap schema first. */
$size = $size === 'sm' ? 'sm' : 'base';
$text = null;
$html = null;

if ($markdown !== null) {
    $html = \Jiannius\Atom\Tiptap\Markdown::toHtml($markdown);
    $text = $html === null ? (is_scalar($markdown) || $markdown instanceof \Stringable ? (string) $markdown : '') : null;
}
elseif ($content !== null) {
    $html = \Jiannius\Atom\Tiptap\Content::render($content);
}
@endphp

<link rel="stylesheet" href="{{ app('atom')->asset()->version('tiptap.css') }}">

@if ($text !== null)
    <div {{ $attributes->class(['editor-content editor-prose whitespace-pre-line [overflow-wrap:anywhere]']) }} data-size="{{ $size }}" data-atom-prose data-fallback>{{ $text }}</div>
@else
    <div {{ $attributes->class(['editor-content editor-prose [overflow-wrap:anywhere]']) }} data-size="{{ $size }}" data-atom-prose>
        @if ($html !== null)
            {!! $html !!}
        @else
            {{ $slot }}
        @endif
    </div>
@endif
