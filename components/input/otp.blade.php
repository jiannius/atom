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
    'size-11 text-center text-lg font-medium no-spinner rounded-lg shadow-sm outline-offset-1',
    'text-zinc-700 dark:text-zinc-200',
    'bg-white dark:bg-white/10',
    'focus:outline-1 focus:outline-zinc-200 dark:focus:outline-2 hover:outline-1 hover:outline-zinc-100/50',
    $invalid ? 'border border-red-400' : 'border border-zinc-200 dark:border-white/10',
    'group-has-[[data-atom-error]]/field:border group-has-[[data-atom-error]]/field:border-red-400',
]);
@endphp

<div
x-data="otp({ length: {{ $length }}, submit: @js($submit) })"
x-modelable="code"
{{ $attributes->except(['class', 'length', 'masked', 'groups', 'submit', 'invalid'])->class('flex items-center gap-2') }}
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
