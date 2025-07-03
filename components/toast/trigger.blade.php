@props([
    'variant' => null,
    'heading' => null,
    'subheading' => null,
    'message' => null,
    'html' => null,
    'position' => null,
    'align' => null,
    'delay' => null,
    'navigate' => null,
    'url' => null,
])

<div
x-data
x-on:click="$el.querySelector('button[disabled]') || atom.toast(@js(array_filter([
    'variant' => $variant,
    'heading' => t($heading),
    'subheading' => t($subheading ?? $message),
    'html' => $html,
    'position' => $position,
    'align' => $align,
    'delay' => $delay,
    'navigate' => $navigate,
    'url' => $url,
])))"
{{ $attributes->class('contents') }}
data-atom-toast-trigger>
    {{ $slot }}
</div>
