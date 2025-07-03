@props([
    'noindex' => true,
    'title' => '',
    'dark' => true,
])

<atom:html
:noindex="$noindex"
:title="$title"
:dark="$dark"
class="min-h-screen bg-white antialiased dark:bg-linear-to-b dark:from-neutral-950 dark:to-neutral-900">
    {{ $slot }}
</atom:html>
