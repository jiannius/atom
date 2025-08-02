@props([
    'code' => '+60',
    'invalid' => false,
    'copyable' => false,
    'clearable' => false,
    'placeholder' => null,
    'invalid' => false,
])

@php
$dialcodes = app('atom')->action('get-options', ['name' => 'dialcodes']);

$classes = Arr::toCssClasses([
    'h-10 w-full py-2 pl-[9.5rem] pr-3 no-spinner rounded-lg shadow-sm outline-offset-1',
    'text-zinc-700 dark:text-zinc-200',
    'bg-white dark:bg-white/10',
    'dark:placeholder-zinc-400',
    'focus:outline-1 focus:outline-zinc-200 dark:focus:outline-2 hover:outline-1 hover:outline-zinc-100/50',
    $invalid ? 'border border-red-400' : 'border border-zinc-200 dark:border-white/10',
    'group-has-[[data-atom-error]]/field:border group-has-[[data-atom-error]]/field:border-red-400',
]);
@endphp

<div
x-data="telInput({ code: @js($code) })"
x-modelable="telValue"
class="group/input relative w-full"
{{ $attributes->except('class', 'placeholder', 'required', 'invalid', 'disabled', 'readonly') }}
data-atom-input-tel>
    <div class="absolute top-0 bottom-0 left-0 w-[9rem] flex items-center gap-2">
        <div class="relative w-full">
            <select
            x-model="code"
            x-on:input.stop
            data-atom-input-tel-country
            class="appearance-none bg-transparent pl-3 pr-6 w-full focus:outline-none">
                @foreach ($dialcodes as $dialcode)
                    <option value="{{ data_get($dialcode, 'value') }}">
                        {{ data_get($dialcode, 'label') }}
                    </option>
                @endforeach
            </select>

            <div class="pointer-events-none absolute top-0 bottom-0 right-0 pr-2 flex items-center justify-center">
                <atom:icon.dropdown />
            </div>
        </div>
    </div>

    <input
    type="tel"
    x-model="number"
    x-on:input.stop
    {{ $attributes->class($classes)->merge([
        'placeholder' => t($placeholder),
    ])->only(['class', 'placeholder', 'required', 'invalid', 'disabled', 'readonly']) }}>
</div>
