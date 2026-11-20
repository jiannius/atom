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
x-on:click="{{ \Jiannius\Atom\Atom::TRIGGER_DISABLED_GUARD }} atom.toast(@js(app('atom')->triggerPayload($variant, $heading, $subheading ?? $message, $html, [
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
