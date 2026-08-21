@props([
    'noindex' => true,
    'title' => '',
    'dark' => false,
    'vite' => ['resources/css/app.css', 'resources/js/app.js'],
])

<atom:html
:noindex="$noindex"
:title="$title"
:dark="$dark"
:vite="$vite"
class="min-h-screen bg-white antialiased dark:bg-linear-to-b dark:from-neutral-950 dark:to-neutral-900">
    {{ $slot }}
</atom:html>
