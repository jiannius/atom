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

// 'self', 'inherited' from a disabled form, or null; the field's own `disabled` (`:disabled="false"` too) wins over the form's.
// Its own is a real `disabled` on every box. A form's is `readonly`, so the code stays selectable and copyable, and the box tab-stops.
$disabledBy = \Jiannius\Atom\Services\Attributes::disabledBy($attributes);
$readonly = $disabledBy === 'inherited' || (bool) $attributes->get('readonly');

// The look is picked here from the state rather than by a `disabled:` variant, so a readonly box in a disabled form looks the same and a focused
// one still draws its focus border (the plain border sorts below the variant).
$box = Arr::toCssClasses([
    'size-10 text-center text-lg font-medium no-spinner rounded-control border',
    $disabledBy ? 'bg-surface-sunken text-fg-subtle cursor-not-allowed' : 'bg-surface text-fg',
    'disabled:cursor-not-allowed disabled:border-border disabled:bg-surface-sunken disabled:text-fg-subtle',
    $invalid ? 'focus-visible:border-danger-fg' : 'focus-visible:border-focus',
    'focus-visible:outline-offset-0',
    $invalid ? 'border-danger-fg' : ($disabledBy ? 'border-border' : 'border-control-border'),
    'group-has-[[data-atom-error]]/field:border-danger-fg group-has-[[data-atom-error]]/field:focus-visible:border-danger-fg',
]);
@endphp

<div
x-data="otp({ length: {{ $length }}, submit: @js($submit) })"
x-modelable="code"
{{ $attributes->except(['length', 'masked', 'groups', 'submit', 'invalid', 'required', 'disabled', 'readonly'])->class('flex items-center gap-2') }}
@if ($disabledBy) data-atom-disabled="{{ $disabledBy }}" @endif
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
        @if ($disabledBy === 'self') disabled @endif
        @if ($readonly) readonly @endif
        {{ $attributes->only('required') }}
        data-atom-input-otp-box/>

        @if ($groups && ($i + 1) % $groups === 0 && $i < $length - 1)
            <span class="w-2" aria-hidden="true"></span>
        @endif
    @endfor
</div>
