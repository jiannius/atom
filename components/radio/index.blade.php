@aware(['disabled' => false])

@props([
    'label' => null,
    'caption' => null,
    'align' => 'center',
])

<label class="group/radio inline-block" data-atom-radio>
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
            <input type="radio" class="sr-only peer" {{ $attributes->merge(['disabled' => ($disabled ?? false) ?: null]) }}>

            <div
            aria-hidden="true"
            @class([
                'size-4.5 rounded-md flex items-center justify-center',
                'bg-white dark:bg-white/10',
                'border border-zinc-300 dark:border-white/10',
                'text-zinc-300 dark:text-transparent',
                'peer-checked:border-zinc-800 peer-checked:shadow-sm peer-checked:*:opacity-100',
                'dark:peer-checked:border-white',
                'peer-focus-visible:outline-1 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-zinc-400',
                'group-has-[.error]/radio:outline-1 group-has-[.error]/radio:outline-red-500 group-has-[.error]/radio:outline-offset-1',
            ])>
                <div class="size-3 opacity-0 rounded bg-zinc-700 dark:bg-white"></div>
            </div>
        </div>

        @if ($slot->isNotEmpty())
            <div class="dark:text-white">
                {{ $slot }}
            </div>
        @elseif ($label)
            <div class="dark:text-white">
                {!! t($label) !!}
            </div>
        @endif
    </div>

    @if ($caption)
        <div class="inline-flex ml-6.5">
            <atom:caption>{{ t($caption) }}</atom:caption>
        </div>
    @endif
</label>
