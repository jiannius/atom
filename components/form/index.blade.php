@props([
    'inset' => false,
    'cols' => null,
    'recaptcha' => false,
    'disabled' => false,
])

@php
// The submit method drives loading: the form carries .is-loading while this
// Livewire action runs, so an <atom:button type=submit> inside it spins for
// the right method (create/save/submit/...), not a hardcoded one.
$submit = $attributes->wire('submit')->value() ?: 'submit';

// recaptcha="register" sets the score action; a bare `recaptcha` uses the
// submit method name as the action.
$recaptchaAction = is_string($recaptcha) ? $recaptcha : $submit;
$recaptcha = (bool) $recaptcha;

$merges = [
    'wire:target' => $submit,
    'wire:loading.class' => 'is-loading',
];

if ($disabled) {
    // Read-only form: fields render themselves readonly/disabled via @aware
    // (so values stay selectable + links clickable — unlike `inert`). Drop
    // wire:submit and hard-block native submission so a stray submit button or
    // Enter can't post. The reCAPTCHA branch is skipped for the same reason.
    $attributes = $attributes->whereDoesntStartWith('wire:submit');
    $merges['onsubmit'] = 'return false';
}
elseif ($recaptcha) {
    // Intercept the submit so reCAPTCHA can mint + attach a token before the
    // Livewire action fires. Drop the native wire:submit (it would fire
    // immediately) and call the method ourselves once the token is set.
    $merges['x-data'] = '';
    $merges['x-on:submit.prevent'] = "window.atom.recaptcha({ el: \$el, wire: \$wire, action: '$recaptchaAction', submit: () => \$wire.$submit() })";
    $attributes = $attributes->whereDoesntStartWith('wire:submit');
}
else {
    $merges['wire:submit'] = $submit;
}
@endphp

<form {{ $attributes->class([
    'group/form relative',
    'flex flex-col gap-6' => !$inset,
    'opacity-70' => $disabled,
])->merge($merges) }}
data-atom-form>
    @if ($cols)
        <atom:form.grid :cols="$cols">{{ $slot }}</atom:form.grid>
    @else
        {{ $slot }}
    @endif
</form>
