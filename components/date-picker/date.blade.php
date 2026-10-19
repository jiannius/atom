@props([
    'time' => false,
    'invalid' => false,
    'disabled' => false,
    'placeholder' => 'Select date',
])

@php
/* The field recipe (see input/general); two 16px icons (clear, calendar) sit at the end. */
$classes = Arr::toCssClasses([
    'h-control w-full pl-2.5 pr-14 no-spinner rounded-control border bg-surface text-sm text-fg cursor-text',
    'placeholder:text-fg-subtle',
    'disabled:cursor-not-allowed disabled:bg-surface-sunken disabled:text-fg-subtle',
    $invalid ? 'focus-visible:border-danger-fg' : 'focus-visible:border-focus',
    'focus-visible:outline-offset-0',
    $disabled ? 'bg-surface-sunken text-fg-subtle cursor-not-allowed' : '',
    $invalid ? 'border-danger-fg' : 'border-control-border',
    'group-has-[[data-atom-error]]/field:border-danger-fg group-has-[[data-atom-error]]/field:focus-visible:border-danger-fg',
]);
@endphp

<div
x-data="datePicker({ time: @js($time) })"
x-modelable="datePickerValue"
@if ($disabled) aria-disabled="true" @endif
@class(['group/date-picker relative', 'pointer-events-none' => $disabled])
data-atom-date-picker
{{ $attributes->except(['id', 'class', 'placeholder', 'disabled']) }}>
    <atom:dropdown x-on:open="visible = true" x-on:close="visible = false" locked>
        @if ($slot->isNotEmpty())
            <div class="relative">
                {{ $slot }}
            </div>
        @else
            <div class="relative" data-atom-dropdown-trigger>
                <input
                type="text"
                x-bind:value="datePickerString"
                x-on:blur="commitTyped($el)"
                x-on:keydown.enter="typedEnter($event)"
                x-on:keydown.escape="revertTyped($el)"
                @if (! $disabled) x-on:keydown.down.prevent="show()" @endif
                autocomplete="off"
                spellcheck="false"
                {{ $attributes->class($classes)->merge(['placeholder' => t($placeholder)])->only(['id', 'class', 'placeholder']) }}
                @if ($disabled) readonly @endif>

                <div class="z-1 absolute top-0 bottom-0 right-0 flex items-center gap-2 pr-2.5 text-fg-subtle">
                    <div x-show="datePickerValue" x-on:click.stop="datePickerValue = null" class="flex items-center justify-center h-full hover:text-fg">
                        <atom:icon.close class="size-4" />
                    </div>

                    {{-- A tap target of its own, so a touch user can open the calendar without
                         focusing the input (which would raise the soft keyboard). Not in the tab
                         order: keyboard users open it with ArrowDown from the input. --}}
                    <button type="button" tabindex="-1" aria-label="{{ t('Open calendar') }}" class="flex items-center justify-center h-full hover:text-fg">
                        <atom:icon.calendar class="size-4" />
                    </button>
                </div>
            </div>
        @endif

        <atom:menu class="w-[300px]" popover wire:ignore>
            <atom:date-picker.calendar />

            @if ($time)
                <div x-bind:class="!datePickerString && 'opacity-50 pointer-events-none'" class="px-2 pb-2">
                    <div class="text-xs font-medium text-fg-muted mb-2">{{ t('Time') }}</div>
                    <atom:time-picker x-model="datePickerValue" />
                </div>
            @endif
        </atom:menu>
    </atom:dropdown>
</div>
