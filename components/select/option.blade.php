@aware(['variant', 'multiple'])

@props([
    'option' => null,
    'value' => null,
    'label' => null,
    'color' => null,
    'avatar' => null,
    'caption' => null,
    'note' => null,
    'badge' => null,
    'badgeColor' => null,
    'tag' => null,
    'meta' => null,
])

@php
$variant ??= 'native';
$value ??= data_get($option, 'value');
$label ??= data_get($option, 'label');
$color ??= data_get($option, 'color');
$avatar ??= data_get($option, 'avatar');
$caption ??= data_get($option, 'caption');
$note ??= data_get($option, 'note');
$badge ??= data_get($option, 'badge');
$badgeColor ??= data_get($option, 'badge_color');
$tag ??= data_get($option, 'tag');
$meta ??= data_get($option, 'meta');
$html = $slot->toHTML();
@endphp

@if ($variant === 'native')
    <option {{ $attributes->merge(['value' => $value]) }} data-atom-option>
        @if ($slot->isNotEmpty())
            {{ $slot }}
        @else
            {{ t($label) }}
        @endif
    </option>
@else
    <li x-on:mouseover="moveTo($el)" x-on:mouseout="moveTo($el, false)" data-atom-option {{ $attrs->except('value') }}>
        <div
            @if (!$attributes->get('x-model'))
            x-data="{
                option: @js([
                    'value' => $value,
                    'label' => $label,
                    'caption' => $caption,
                    'avatar' => $avatar,
                    'color' => $color,
                    'badge' => $badge,
                    'badgeColor' => $badgeColor,
                    'note' => $note,
                    'tag' => $tag,
                    'meta' => $meta,
                    'html' => $html,
                ]),
            }"
            @elseif ($attributes->get('x-model') !== 'option')
            x-data="{
                option: { ...{{ $attributes->get('x-model') }}},
            }"
            @endif
            x-init="() => {
                if (!option.html) {
                    let color = option.color ? `<div style='background-color: ${option.color}' class='shrink-0 w-3 h-3 rounded-full bg-zinc-100 flex items-center justify-center'></div>` : ''
                    option.html = `<div class='flex items-center gap-2'>${color}<span>${option.label}</span></div>`
                }
            }"
            x-on:click="select(option.value)"
            x-bind:data-option-body="Atom.json(option)"
            x-bind:data-option-selected="isSelected(option.value)"
            class="p-2 flex gap-3 cursor-default rounded-md data-[option-selected]:bg-zinc-800/5 [[data-option-focus]>&]:bg-zinc-800/5">
            <div class="shrink-0 w-6 h-6 flex items-center justify-center text-transparent [[data-option-selected]>&]:text-zinc-400">
                <atom:icon.check/>
            </div>

            <div x-html="option.html" class="grow" data-option-content></div>
        </div>
    </li>
@endif