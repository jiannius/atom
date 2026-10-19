@props([
    'code' => '+60',
    'invalid' => false,
    'copyable' => false,
    'clearable' => false,
    'placeholder' => null,
])

@php
$dialcodes = app('atom')->action('get-options', ['name' => 'dialcodes']);

$classes = Arr::toCssClasses([
    'h-control w-full pl-[9.5rem] pr-2.5 no-spinner rounded-control border bg-surface text-sm text-fg',
    'placeholder:text-fg-subtle',
    'disabled:cursor-not-allowed disabled:bg-surface-sunken disabled:text-fg-subtle',
    $invalid ? 'focus-visible:border-danger-fg' : 'focus-visible:border-focus',
    'focus-visible:outline-offset-0',
    $invalid ? 'border-danger-fg' : 'border-control-border',
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
            x-on:input.stop
            data-atom-input-tel-country
            class="h-control appearance-none bg-transparent pl-2.5 pr-6 w-full rounded-s-control text-sm text-fg focus-visible:outline-offset-[-2px]">
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
    x-on:input.stop
    {{ $attributes->class($classes)->merge([
        'placeholder' => t($placeholder),
    ])->only(['id', 'class', 'placeholder', 'required', 'invalid', 'disabled', 'readonly']) }}>
</div>
