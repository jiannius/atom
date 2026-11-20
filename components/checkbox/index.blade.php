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

        <div
        aria-hidden="true"
        data-atom-tile-indicator
        @class([
            'size-4 rounded flex items-center justify-center',
            'bg-surface border border-fg-subtle text-surface',
            'peer-checked:border-accent-ink peer-checked:bg-accent-ink peer-checked:*:opacity-100',
            'peer-disabled:opacity-50',
        ])>
            <div class="size-3 opacity-0 flex items-center justify-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="size-3" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
            </div>
        </div>

        <div class="min-w-0 pe-6 text-fg peer-disabled:text-fg-subtle" data-atom-tile-body>
            @if ($slot->isNotEmpty())
                {{ $slot }}
            @elseif ($label)
                {{ t($label) }}
            @endif
        </div>

        @if ($caption)
            <atom:caption class="pe-6">{{ t($caption) }}</atom:caption>
        @endif

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

                <div
                aria-hidden="true"
                @class([
                    'size-4 rounded flex items-center justify-center',
                    'bg-surface border border-fg-subtle text-surface',
                    'peer-checked:border-accent-ink peer-checked:bg-accent-ink peer-checked:*:opacity-100',
                    'peer-disabled:opacity-50 peer-disabled:cursor-not-allowed',
                    'peer-focus-visible:outline-2 peer-focus-visible:outline-offset-1 peer-focus-visible:outline-focus',
                    'group-has-[.error]/checkbox:outline-1 group-has-[.error]/checkbox:outline-danger-fg group-has-[.error]/checkbox:outline-offset-1',
                ])>
                    <div class="size-3 opacity-0 flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="size-3" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                    </div>
                </div>
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
