@aware(['atomChoiceTile' => null])

@props([
    'name' => null,
    'label' => null,
    'caption' => null,
    'required' => false,
    'error' => null,
    'align' => 'center',
])

@php
// a tile when the group around it (not any component with a `variant`) says so: see ChoiceTile
$variant = \Jiannius\Atom\Services\ChoiceTile::resolve($atomChoiceTile) ? 'tile' : null;
$name ??= $attributes->wire('model')->value();
$error ??= $errors?->first($name);
$merges = ['name' => $name, 'required' => $required ?: null];
// what a tile holds: the slot, else the label
$content = $slot->isNotEmpty() ? $slot : ($label ? t($label) : null);

// 'self', or 'inherited' from a disabled <atom:form> or group; its own `disabled` wins (`:disabled="false"` included). A checkbox is a
// native choice, so both are a real `disabled` input, which also carries the marker for a test or a selector.
$disabledBy = \Jiannius\Atom\Services\Attributes::disabledBy($attributes);

if ($disabledBy) {
    $merges['disabled'] = true;
    $merges['data-atom-disabled'] = $disabledBy;
}
@endphp

@if ($variant === 'tile')
    {{-- A selectable tile: see components/radio/index.blade.php. The error prints inside the label, under the body, and draws the tile's border (atom.css). --}}
    <label {{ $attributes->only('class')->class('group/checkbox min-w-0 cursor-pointer rounded-panel border border-border bg-surface p-3') }} data-atom-checkbox data-atom-tile>
        <input type="checkbox" class="sr-only peer" {{ $attributes->except('class')->merge($merges) }}>

        <atom:checkbox._indicator kind="checkbox" tile />

        <atom:checkbox._tile-body :content="$content" :caption="$caption" />

        <atom:error>{{ t($error) }}</atom:error>
    </label>
@else
<label {{ $attributes->only('class')->class('group/checkbox inline-block space-y-2') }} data-atom-checkbox>
    <div>
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
                <input type="checkbox" class="sr-only peer" {{ $attributes->except('class')->merge($merges) }}>

                <atom:checkbox._indicator kind="checkbox" />
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
    </div>

    <atom:error>{{ t($error) }}</atom:error>
</label>
@endif
