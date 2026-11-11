@props([
    'code' => '+60',
    'invalid' => false,
    'copyable' => false,
    'clearable' => false,
    'placeholder' => null,
])

@php
$dialcodes = app('atom')->action('get-options', ['name' => 'dialcodes']);

// `data-atom-disabled` (`self` or `inherited`) is printed by the input tag; the number box takes the disabled look from it as plain utilities
// (a form-disabled box is readonly, not disabled), which a focus border still beats. The dial code is a native choice: disabled in both.
$disabledBy = $attributes->get('data-atom-disabled');

$classes = Arr::toCssClasses([
    'h-control w-full pl-[9.5rem] pr-2.5 no-spinner rounded-control border text-sm',
    $disabledBy ? 'cursor-not-allowed bg-surface-sunken text-fg-subtle' : 'bg-surface text-fg',
    'placeholder:text-fg-subtle',
    'disabled:cursor-not-allowed disabled:border-border disabled:bg-surface-sunken disabled:text-fg-subtle',
    $invalid ? 'focus-visible:border-danger-fg' : 'focus-visible:border-focus',
    'focus-visible:outline-offset-0',
    $invalid ? 'border-danger-fg' : ($disabledBy ? 'border-border' : 'border-control-border'),
    'group-has-[[data-atom-error]]/field:border-danger-fg group-has-[[data-atom-error]]/field:focus-visible:border-danger-fg',
]);
@endphp

<div
x-data="telInput({ code: @js($code) })"
x-modelable="telValue"
class="group/input relative w-full"
{{ $attributes->except(['id', 'class', 'placeholder', 'required', 'invalid', 'disabled', 'readonly']) }}
data-atom-input-tel>
    <div class="absolute top-0 bottom-0 left-0 w-[9rem] flex items-center gap-2">
        <div class="relative w-full">
            <select
            x-model="code"
            x-on:input.stop="keepCode"
            x-on:change="keepCode($event); format()"
            @disabled($disabledBy)
            data-atom-input-tel-country
            class="h-control appearance-none bg-transparent pl-2.5 pr-6 w-full rounded-s-control text-sm focus-visible:outline-offset-[-2px] {{ $disabledBy ? 'cursor-not-allowed text-fg-subtle' : 'text-fg' }}">
                @foreach ($dialcodes as $dialcode)
                    <option value="{{ data_get($dialcode, 'value') }}">
                        {{ data_get($dialcode, 'label') }}
                    </option>
                @endforeach
            </select>

            <div class="pointer-events-none absolute top-0 bottom-0 right-0 pr-2 flex items-center justify-center">
                <atom:icon.dropdown class="size-4 text-fg-subtle" />
            </div>
        </div>
    </div>

    <input
    type="tel"
    x-model="number"
    x-on:input.stop="format()"
    {{ $attributes->class($classes)->merge([
        'placeholder' => t($placeholder),
    ])->only(['id', 'class', 'placeholder', 'required', 'invalid', 'disabled', 'readonly']) }}>
</div>
