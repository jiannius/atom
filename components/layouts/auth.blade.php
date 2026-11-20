@props([
    'noindex' => true,
    'title' => '',
    'dark' => false,
    'density' => 'comfortable',
    'tint' => null,
    'vite' => ['resources/css/app.css', 'resources/js/app.js'],
])

<atom:html
:noindex="$noindex"
:title="$title"
:dark="$dark"
:density="$density"
:tint="$tint"
:vite="$vite"
:attributes="$attributes->class('min-h-screen bg-canvas antialiased')">
    {{ $slot }}
</atom:html>
