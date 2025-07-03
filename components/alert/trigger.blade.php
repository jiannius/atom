@props([
    'variant' => null,
    'heading' => null,
    'subheading' => null,
    'message' => null,
    'button' => null,
])

<div
x-data
x-on:click="$el.querySelector('button[disabled]') || atom.alert(@js(array_filter([
    'variant' => $variant,
    'heading' => t($heading),
    'subheading' => t($subheading ?? $message),
    'button' => t($button),
]))).then(() => $dispatch('dismissed'))"
{{ $attributes->class('contents') }}
data-atom-alert-trigger>
    {{ $slot }}
</div>
