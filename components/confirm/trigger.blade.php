@props([
    'variant' => null,
    'heading' => null,
    'subheading' => null,
    'message' => null,
    'html' => null,
    'buttonConfirm' => null,
    'buttonCancel' => null,
    'password' => null,
    'passphrase' => null,
    'reason' => null,
    'reasonLabel' => null,
    'reasonPlaceholder' => null,
])

<div
x-data
x-on:click="$event.target.closest?.('[data-atom-confirm]') || {!! \Jiannius\Atom\Atom::TRIGGER_DISABLED_GUARD !!} atom.confirm(@js(app('atom')->triggerPayload($variant, $heading, $subheading ?? $message, $html, [
    'buttonConfirm' => t($buttonConfirm),
    'buttonCancel' => t($buttonCancel),
    'password' => $password ?? false,
    'passphrase' => $passphrase,
    'passphraseLabel' => $passphrase ? t('Please type "'.$passphrase.'" to continue') : null,
    'reason' => $reason ?? false,
    'reasonLabel' => t($reasonLabel),
    'reasonPlaceholder' => t($reasonPlaceholder),
]))).then(accepted => $dispatch('confirmed', accepted)).catch(e => $dispatch('rejected', e))"
{{ $attributes->class('contents') }}
data-atom-confirm-trigger>
    {{ $slot }}
</div>
