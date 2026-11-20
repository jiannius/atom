@aware(['atomChoiceTile' => null])

@props([
    'label' => null,
    'caption' => null,
    'align' => 'center',
])

@php
// a tile when the group around it (not any component with a `variant`) says so: see ChoiceTile
$variant = \Jiannius\Atom\Services\ChoiceTile::resolve($atomChoiceTile) ? 'tile' : null;
// 'self', or 'inherited' from a disabled <atom:form> or group; its own `disabled` wins (`:disabled="false"` included). A radio is a native choice,
// so both are a real `disabled` input, which also carries the marker for a test or a selector.
$disabledBy = \Jiannius\Atom\Services\Attributes::disabledBy($attributes);
// what a tile holds: the slot, else the label
$content = $slot->isNotEmpty() ? $slot : ($label ? t($label) : null);
@endphp

@if ($variant === 'tile')
    {{-- A selectable tile: the label is the box, the native input stays (sr-only) so the arrow keys, Tab, `name`, `wire:model` and form posting are the
         browser's. The state is drawn from `:has()` in atom.css (data-atom-tile). The indicator sits over the top end; the body keeps clear of it. --}}
    <label {{ $attributes->only('class')->class('group/radio min-w-0 cursor-pointer rounded-panel border border-border bg-surface p-3') }} data-atom-radio data-atom-tile>
        <input type="radio" class="sr-only peer" {{ $attributes->except('class')->merge(['disabled' => $disabledBy ? true : null, 'data-atom-disabled' => $disabledBy]) }}>

        <atom:checkbox._indicator kind="radio" tile />

        <atom:checkbox._tile-body :content="$content" :caption="$caption" />
    </label>
@else
<label {{ $attributes->only('class')->class('group/radio inline-block') }} data-atom-radio>
    <div @class([
        'flex gap-2',
        'items-center' => $align === 'center',
        'items-start' => $align === 'start',
        'items-end' => $align === 'end',
    ])>
        <div @class([
            'shrink-0',
            'pt-1' => $align === 'start',
        ])>
            <input type="radio" class="sr-only peer" {{ $attributes->except('class')->merge(['disabled' => $disabledBy ? true : null, 'data-atom-disabled' => $disabledBy]) }}>

            <atom:checkbox._indicator kind="radio" />
        </div>

        @if ($slot->isNotEmpty())
            <div class="text-fg">
                {{ $slot }}
            </div>
        @elseif ($label)
            <div class="text-fg">
                {{ t($label) }}
            </div>
        @endif
    </div>

    @if ($caption)
        <div class="inline-flex ml-6">
            <atom:caption>{{ t($caption) }}</atom:caption>
        </div>
    @endif
</label>
@endif
