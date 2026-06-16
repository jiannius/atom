@props([
    'content' => null,
])

<link rel="stylesheet" href="{{ app('atom')->asset()->version('tiptap.css') }}">

<div {{ $attributes->class(['editor-content']) }}>
    {!! \Jiannius\Atom\Tiptap\Content::render($content ?? (string) $slot) !!}
</div>
