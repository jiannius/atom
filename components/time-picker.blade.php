@props([
    'name' => null,
    'label' => null,
    'caption' => null,
    'inline' => false,
    'required' => false,
    'invalid' => false,
    'error' => null,
    'ariaLabelledby' => null,
])

@php
$name ??= $attributes->wire('model')->value();
$error ??= $errors?->first($name);

// Hour, minute and meridiem are three separate inputs, so a single <label for> cannot
// reach them: the set is named as a group pointing back at the field's label, and each
// input carries its own name. Derived, not per render: see ComponentAttributeBag::fieldId().
$labelId = $label ? $attributes->fieldId('atom-time-picker', $name, $label).'-label' : null;
@endphp

@if ($label || $caption)
    <atom:input.field
    :label="$label"
    :caption="$caption"
    :inline="$inline"
    :required="$required"
    :error="$error"
    :label-id="$labelId">
        <atom:time-picker :invalid="$invalid" :aria-labelledby="$labelId" :attributes="$attributes" />
    </atom:input.field>
@else
    @php
    $classes = Arr::toCssClasses([
        'h-10 relative w-full py-2 pl-3 pr-10 no-spinner rounded-lg shadow-sm outline-offset-1 cursor-default',
        'text-zinc-700 dark:text-zinc-200',
        'bg-white dark:bg-white/10',
        'has-[:focus]:outline-1 has-[:focus]:outline-zinc-200 dark:has-[:focus]:outline-2 hover:outline-1 hover:outline-zinc-100/50',
        $invalid ? 'border border-red-400' : 'border border-zinc-200 dark:border-white/10',
        'group-has-[[data-atom-error]]/field:border group-has-[[data-atom-error]]/field:border-red-400',
    ]);
    @endphp

    <div
    x-data="timePicker()"
    x-modelable="timePickerValue"
    {{ $attributes->class($classes) }}>
        <div
        {{-- only a NAMED group is worth announcing; without a label this would be a
             boundary carrying nothing, and the three inputs name themselves anyway --}}
        @if ($ariaLabelledby) role="group" aria-labelledby="{{ $ariaLabelledby }}" @endif
        x-on:input.stop
        class="flex items-center gap-2">
            <input
            type="number"
            x-model.lazy="hr"
            x-on:click.stop="up('hr')"
            x-on:keydown.up.stop.prevent="up('hr')"
            x-on:keydown.down.stop.prevent="down('hr')"
            x-on:keydown.left.stop.prevent="down('hr')"
            x-on:keydown.right.stop.prevent="up('hr')"
            maxlength="2"
            aria-label="{{ t('Hour') }}"
            class="appearance-none w-8 text-center no-spinner">

            <span class="font-bold">:</span>

            <input
            type="number"
            x-model.lazy="min"
            x-on:click.stop="up('min')"
            x-on:keydown.up.stop.prevent="up('min')"
            x-on:keydown.down.stop.prevent="down('min')"
            x-on:keydown.left.stop.prevent="down('min')"
            x-on:keydown.right.stop.prevent="up('min')"
            maxlength="2"
            aria-label="{{ t('Minute') }}"
            class="appearance-none w-8 text-center no-spinner">

            <input
            type="text"
            x-bind:value="am"
            x-on:click.stop="up('am')"
            x-on:keydown.up.stop.prevent="up('am')"
            x-on:keydown.down.stop.prevent="down('am')"
            aria-label="{{ t('AM or PM') }}"
            class="appearance-none w-8 text-center" readonly>
        </div>

        <div class="z-1 absolute top-0 bottom-0 flex items-center justify-center pr-3 right-0">
            <atom:icon.clock />
        </div>
    </div>
@endif
