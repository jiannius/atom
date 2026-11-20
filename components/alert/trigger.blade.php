@props([
    'variant' => null,
    'heading' => null,
    'subheading' => null,
    'message' => null,
    'html' => null,
    'button' => null,
])

<div
x-data
x-on:click="{!! \Jiannius\Atom\Atom::TRIGGER_DISABLED_GUARD !!} atom.alert(@js(app('atom')->triggerPayload($variant, $heading, $subheading ?? $message, $html, [
    'button' => t($button),
]))).then(() => $dispatch('dismissed'))"
{{ $attributes->class('contents') }}
data-atom-alert-trigger>
    {{ $slot }}
</div>
