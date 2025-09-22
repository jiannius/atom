@aware(['variant'])

@props([
    'label' => null,
    'caption' => null,
])

<label
@class([
    'group/radio',
    $variant === 'card'
        ? 'py-3 px-4 border border-zinc-200 dark:border-zinc-700 shadow-sm rounded-lg hover:bg-zinc-50 dark:hover:bg-zinc-700 has-checked:bg-zinc-100 dark:has-checked:bg-zinc-700'
        : 'block',
])
data-atom-radio>
    <div class="flex gap-2 items-center">
        <div class="shrink-0">
            <input type="radio" class="peer hidden" {{ $attributes->except('class') }}>

            <div
            tabindex="0"
            role="checkbox"
            @class([
                'size-4.5 rounded-md flex items-center justify-center focus:outline-none',
                'bg-white dark:bg-white/10',
                'border border-zinc-300 dark:border-white/10',
                'text-zinc-300 dark:text-transparent',
                'peer-checked:border-zinc-800 peer-checked:shadow-sm peer-checked:*:opacity-100',
                'dark:peer-checked:border-white',
                'group-has-[.error]/checkbox:outline-1 group-has-[.error]/checkbox:outline-red-500 group-has-[.error]/checkbox:outline-offset-1',
            ])>
                <div class="size-3 opacity-0 rounded bg-zinc-700 dark:bg-white"></div>
            </div>
        </div>

        <div class="dark:text-white">
            @if ($label instanceof \Illuminate\View\ComponentSlot)
                {{ $label }}
            @elseif ($label)
                {!! t($label) !!}
            @endif
        </div>
    </div>

    @if ($caption)
        <div class="inline-flex ml-6.5">
            <atom:caption>{{ t($caption) }}</atom:caption>
        </div>
    @elseif ($slot->isNotEmpty())
        {{ $slot }}
    @endif
</label>
