@props([
    'time' => false,
    'invalid' => false,
    'placeholder' => 'Select date',
])

@php
$classes = Arr::toCssClasses([
    'h-10 w-full py-2 pl-3 pr-10 no-spinner rounded-lg shadow-sm outline-offset-1 cursor-default',
    'text-zinc-700 dark:text-zinc-200',
    'bg-white dark:bg-white/10',
    'dark:placeholder-zinc-400',
    'focus:outline-1 focus:outline-zinc-200 dark:focus:outline-2 hover:outline-1 hover:outline-zinc-100/50',
    $invalid ? 'border border-red-400' : 'border border-zinc-200 dark:border-white/10',
    'group-has-[[data-atom-error]]/field:border group-has-[[data-atom-error]]/field:border-red-400',
]);
@endphp

<div
x-data="datePicker({ time: @js($time) })"
x-modelable="datePickerValue"
class="group/date-picker relative w-full"
data-atom-date-picker
{{ $attributes->except(['class', 'placeholder']) }}>
    <atom:dropdown x-on:open="visible = true" x-on:close="visible = false" locked>
        <div class="relative">
            <input
            type="text"
            x-bind:value="datePickerString"
            {{ $attributes->class($classes)->merge(['placeholder' => t($placeholder)])->only(['class', 'placeholder']) }}
            readonly>

            <div class="z-1 absolute top-0 bottom-0 flex items-center justify-center pr-3 right-0">
                <div x-show="datePickerValue" x-on:click.stop="datePickerValue = null" class="flex items-center justify-center w-full h-full text-muted-foreground hover:text-muted">
                    <atom:icon.close />
                </div>

                <div x-show="!datePickerValue" class="pointer-events-none flex items-center justify-center w-full h-full text-muted">
                    <atom:icon.calendar />
                </div>
            </div>
        </div>

        <atom:menu class="w-[300px]" popover>
            <atom:date-picker.calendar />

            @if ($time)
                <div x-show="datePickerString" class="px-2 pb-2">
                    <div class="text-sm text-muted-foreground mb-2 uppercase">{{ t('Time') }}</div>
                    <atom:time-picker x-model="datePickerValue" />
                </div>
            @endif
        </atom:menu>
    </atom:dropdown>
</div>
