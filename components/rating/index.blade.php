@props([
    'name' => null,
    'label' => null,
    'caption' => null,
    'error' => null,
    'count' => 5,
    'value' => 0,
    'half' => false,
    'readonly' => false,
    'clearable' => false,
    'icon' => null,
])

@php
$name ??= $attributes->wire('model')->value();
$error ??= $errors?->first($name);

$count = (int) $count;
$value = is_numeric($value) ? $value + 0 : 0;

$flag = fn ($v) => $v ? 'true' : 'false';

/* 'self', 'inherited' from a disabled form, or null; the JS reads it as `data-atom-disabled` at event time, never from x-data. A disabled rating is a slider that does nothing, `readonly` alone a display value (role="img"). */
$disabledBy = \Jiannius\Atom\Services\Attributes::disabledBy($attributes);
$display = $readonly && ! $disabledBy;

$id = $attributes->get('id') ?? ($name ? 'atom-rating-'.$name : 'atom-rating-'.\Illuminate\Support\Str::random(6));

// wire:model / x-on and the caller's class ride on the wrapper (x-modelable target); prop attrs are stripped.
$wrapper = $attributes->except(['id', 'value', 'count', 'half', 'readonly', 'disabled', 'clearable', 'icon']);
@endphp

<div
data-atom-rating
x-data="rating({ count: {{ $count }}, half: {{ $flag($half) }}, clearable: {{ $flag($clearable) }}, value: {{ $value }} })"
x-modelable="value"
@if ($disabledBy)
data-atom-disabled="{{ $disabledBy }}"
@endif
:style="{ '--atom-rating-percent': percent + '%' }"
{{ $wrapper->class('space-y-2') }}>
    @if ($slot->isNotEmpty() || $label || $caption)
        <div>
            @if ($slot->isNotEmpty())
                <label for="{{ $id }}" class="text-sm text-fg">{{ $slot }}</label>
            @elseif ($label)
                <label for="{{ $id }}" class="text-sm text-fg">{{ t($label) }}</label>
            @endif

            @if ($caption)
                <atom:caption>{{ t($caption) }}</atom:caption>
            @endif
        </div>
    @endif

    <div
    id="{{ $id }}"
    data-atom-rating-track
    x-ref="track"
    @if ($display)
    data-atom-rating-readonly
    role="img"
    :aria-label="`${value} out of {{ $count }}`"
    @else
    role="slider"
    @if ($disabledBy)
    aria-disabled="true"
    @else
    tabindex="0"
    @endif
    aria-valuemin="0"
    :aria-valuemax="count"
    :aria-valuenow="value"
    aria-label="{{ t($label ?? $name ?? 'Rating') }}"
    @endif
    x-on:pointermove="onMove($event)"
    x-on:pointerleave="onLeave()"
    x-on:click="onClick($event)"
    x-on:keydown="onKey($event)">
        <div aria-hidden="true" data-atom-rating-base>
            @for ($i = 0; $i < $count; $i++)
                <atom:rating._icon :icon="$icon"/>
            @endfor
        </div>

        <div aria-hidden="true" data-atom-rating-fill>
            @for ($i = 0; $i < $count; $i++)
                <atom:rating._icon :icon="$icon"/>
            @endfor
        </div>
    </div>

    <atom:error>{{ t($error) }}</atom:error>
</div>
