@aware(['disabled' => false])

@props([
    'label' => null,
    'caption' => null,
    'align' => 'center',
])

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
            <input type="radio" class="sr-only peer" {{ $attributes->except('class')->merge(['disabled' => ($disabled ?? false) ?: null]) }}>

            <div
            aria-hidden="true"
            @class([
                'size-4 rounded-full flex items-center justify-center',
                'bg-surface border border-fg-subtle',
                'peer-checked:border-accent-ink peer-checked:bg-accent-ink peer-checked:*:opacity-100',
                'peer-disabled:opacity-50 peer-disabled:cursor-not-allowed',
                'peer-focus-visible:outline-2 peer-focus-visible:outline-offset-1 peer-focus-visible:outline-focus',
                'group-has-[.error]/radio:outline-1 group-has-[.error]/radio:outline-danger-fg group-has-[.error]/radio:outline-offset-1',
            ])>
                <div class="size-1.5 opacity-0 rounded-full bg-surface"></div>
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
</label>
