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
// input carries its own name. Derived, not per render: see Services\Attributes::fieldId().
$labelId = $label ? \Jiannius\Atom\Services\Attributes::fieldId($attributes, 'atom-time-picker', $name, $label).'-label' : null;
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
    /* The field recipe (see input/general). Hour, minute and meridiem are three inputs in one box, so the ring belongs to the
       box and the inputs inside it draw none. */
    $classes = Arr::toCssClasses([
        'h-control relative flex w-full items-center pl-2.5 pr-8 no-spinner rounded-control border bg-surface text-sm text-fg cursor-default',
        $invalid ? 'has-[:focus-visible]:border-danger-fg' : 'has-[:focus-visible]:border-focus',
        'has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-focus',
        $invalid ? 'border-danger-fg' : 'border-control-border',
        'group-has-[[data-atom-error]]/field:border-danger-fg group-has-[[data-atom-error]]/field:has-[:focus-visible]:border-danger-fg',
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
            type="text"
            inputmode="numeric"
            x-model.lazy="hr"
            x-on:input="sanitise($el)"
            x-on:click.stop="$el.select()"
            x-on:focus="$el.select()"
            x-on:keydown.up.stop.prevent="up('hr', $el.value)"
            x-on:keydown.down.stop.prevent="down('hr', $el.value)"
            x-on:keydown.left.stop.prevent="down('hr', $el.value)"
            x-on:keydown.right.stop.prevent="up('hr', $el.value)"
            maxlength="2"
            aria-label="{{ t('Hour') }}"
            class="appearance-none w-6 text-center no-spinner bg-transparent focus-visible:outline-none">

            <span class="font-medium text-fg-muted">:</span>

            <input
            type="text"
            inputmode="numeric"
            x-model.lazy="min"
            x-on:input="sanitise($el)"
            x-on:click.stop="$el.select()"
            x-on:focus="$el.select()"
            x-on:keydown.up.stop.prevent="up('min', $el.value)"
            x-on:keydown.down.stop.prevent="down('min', $el.value)"
            x-on:keydown.left.stop.prevent="down('min', $el.value)"
            x-on:keydown.right.stop.prevent="up('min', $el.value)"
            maxlength="2"
            aria-label="{{ t('Minute') }}"
            class="appearance-none w-6 text-center no-spinner bg-transparent focus-visible:outline-none">

            <input
            type="text"
            x-bind:value="am"
            x-on:click.stop="up('am')"
            x-on:keydown.up.stop.prevent="up('am')"
            x-on:keydown.down.stop.prevent="down('am')"
            aria-label="{{ t('AM or PM') }}"
            class="appearance-none w-8 text-center bg-transparent focus-visible:outline-none" readonly>
        </div>

        <div class="z-1 absolute top-0 bottom-0 flex items-center justify-center text-fg-subtle pr-2.5 right-0">
            <atom:icon.clock class="size-4" />
        </div>
    </div>
@endif
