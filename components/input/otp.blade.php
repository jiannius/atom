@props([
    'length' => 6,
    'masked' => false,
    'groups' => null,
    'submit' => null,
    'invalid' => false,
])

@php
$length = (int) $length;
$groups = $groups ? (int) $groups : null;

$box = Arr::toCssClasses([
    'size-10 text-center text-lg font-medium no-spinner rounded-control border bg-surface text-fg',
    'disabled:cursor-not-allowed disabled:bg-surface-sunken disabled:text-fg-subtle',
    $invalid ? 'focus-visible:border-danger-fg' : 'focus-visible:border-focus',
    'focus-visible:outline-offset-0',
    $invalid ? 'border-danger-fg' : 'border-control-border',
    'group-has-[[data-atom-error]]/field:border-danger-fg group-has-[[data-atom-error]]/field:focus-visible:border-danger-fg',
]);
@endphp

<div
x-data="otp({ length: {{ $length }}, submit: @js($submit) })"
x-modelable="code"
{{ $attributes->except(['length', 'masked', 'groups', 'submit', 'invalid'])->class('flex items-center gap-2') }}
data-atom-input-otp>
    @for ($i = 0; $i < $length; $i++)
        <input
        type="{{ $masked ? 'password' : 'text' }}"
        inputmode="numeric"
        autocomplete="{{ $i === 0 ? 'one-time-code' : 'off' }}"
        maxlength="1"
        x-model="digits[{{ $i }}]"
        x-on:input.stop="onInput({{ $i }}, $event)"
        x-on:keydown="onKeydown({{ $i }}, $event)"
        x-on:paste.prevent="onPaste($event)"
        x-on:focus="$event.target.select()"
        aria-label="{{ t('Digit :n', ['n' => $i + 1]) }}"
        class="{{ $box }}"
        {{ $attributes->only(['required', 'disabled', 'readonly']) }}
        data-atom-input-otp-box/>

        @if ($groups && ($i + 1) % $groups === 0 && $i < $length - 1)
            <span class="w-2" aria-hidden="true"></span>
        @endif
    @endfor
</div>
