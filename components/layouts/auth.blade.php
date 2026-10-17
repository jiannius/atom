@props([
    'noindex' => true,
    'title' => '',
    'dark' => false,
    'density' => 'comfortable',
    'vite' => ['resources/css/app.css', 'resources/js/app.js'],
])

<atom:html
:noindex="$noindex"
:title="$title"
:dark="$dark"
:density="$density"
:vite="$vite"
:attributes="$attributes->class('min-h-screen bg-canvas antialiased')">
    {{ $slot }}
</atom:html>
