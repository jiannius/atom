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
x-on:click="$el.querySelector('button[disabled]') || atom.confirm(@js(array_filter([
    'variant' => $variant,
    'heading' => t($heading),
    'subheading' => t($subheading ?? $message),
    'html' => $html,
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
