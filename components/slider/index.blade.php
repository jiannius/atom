@props([
    'name' => null,
    'label' => null,
    'caption' => null,
    'error' => null,
    'min' => 0,
    'max' => 100,
    'step' => 1,
    'bubble' => false,
    'labels' => false,
])

@php
$name ??= $attributes->wire('model')->value();
$error ??= $errors?->first($name);

$min = is_numeric($min) ? $min + 0 : 0;
$max = is_numeric($max) ? $max + 0 : 100;
$step = is_numeric($step) ? $step + 0 : 1;
$value = $attributes->get('value', $min);

$id = $attributes->get('id') ?? ($name ? 'atom-slider-'.$name : 'atom-slider-'.\Illuminate\Support\Str::random(6));

// wire:model / x-on / etc. ride on the wrapper (x-modelable target); input-only attrs go to the input.
$wrapper = $attributes->except(['class', 'id', 'name', 'value', 'min', 'max', 'step', 'required', 'disabled']);
@endphp

<div
class="group/slider space-y-2"
data-atom-slider
x-data="slider({ min: {{ $min }}, max: {{ $max }}, step: {{ $step }}, value: @js($value) })"
x-modelable="value"
:style="{ '--atom-slider-percent': percent + '%' }"
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

    <div class="relative pt-1" data-atom-slider-track>
        @if ($bubble)
            <output aria-hidden="true" x-text="value" data-atom-slider-bubble></output>
        @endif

        <input
        id="{{ $id }}"
        type="range"
        min="{{ $min }}"
        max="{{ $max }}"
        step="{{ $step }}"
        x-model="value"
        @if ($name) name="{{ $name }}" @endif
        class="w-full text-primary"
        {{ $attributes->only(['required', 'disabled']) }}
        data-atom-slider-input/>
    </div>

    @if ($labels)
        <div class="flex justify-between text-xs text-zinc-500 dark:text-zinc-400" aria-hidden="true" data-atom-slider-labels>
            <span>{{ $min }}</span>
            <span>{{ $max }}</span>
        </div>
    @endif

    <atom:error>{{ t($error) }}</atom:error>
</div>
