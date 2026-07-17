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

$id = $attributes->get('id') ?? ($name ? 'atom-rating-'.$name : 'atom-rating-'.\Illuminate\Support\Str::random(6));

// wire:model / x-on ride on the wrapper (x-modelable target); prop attrs are stripped.
$wrapper = $attributes->except(['class', 'id', 'value', 'count', 'half', 'readonly', 'clearable', 'icon']);
@endphp

<div
class="space-y-2"
data-atom-rating
x-data="rating({ count: {{ $count }}, half: {{ $flag($half) }}, readonly: {{ $flag($readonly) }}, clearable: {{ $flag($clearable) }}, value: {{ $value }} })"
x-modelable="value"
:style="{ '--atom-rating-percent': percent + '%' }"
{{ $wrapper }}>
    @if ($slot->isNotEmpty() || $label || $caption)
        <div>
            @if ($slot->isNotEmpty())
                <label for="{{ $id }}" class="dark:text-white">{{ $slot }}</label>
            @elseif ($label)
                <label for="{{ $id }}" class="dark:text-white">{!! t($label) !!}</label>
            @endif

            @if ($caption)
                <atom:caption>{{ t($caption) }}</atom:caption>
            @endif
        </div>
    @endif

    <div
    id="{{ $id }}"
    data-atom-rating-track
    @if ($readonly)
    role="img"
    :aria-label="`${value} out of {{ $count }}`"
    @else
    x-ref="track"
    role="slider"
    tabindex="0"
    aria-valuemin="0"
    :aria-valuemax="count"
    :aria-valuenow="value"
    aria-label="{{ t($label ?? $name ?? 'Rating') }}"
    x-on:pointermove="onMove($event)"
    x-on:pointerleave="onLeave()"
    x-on:click="onClick($event)"
    x-on:keydown="onKey($event)"
    @endif>
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
