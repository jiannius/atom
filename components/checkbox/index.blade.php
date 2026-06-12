@props([
    'name' => null,
    'label' => null,
    'caption' => null,
    'required' => false,
    'error' => null,
    'align' => 'center',
])

@php
$name ??= $attributes->wire('model')->value();
$error ??= $errors?->first($name);
$merges = ['name' => $name];
@endphp

<label class="group/checkbox inline-block space-y-2" data-atom-checkbox>
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
                <input type="checkbox" class="hidden peer" {{ $attributes->merge($merges) }}>

                <div
                tabindex="0"
                role="checkbox"
                @class([
                    'size-4.5 rounded-md flex items-center justify-center focus:outline-none',
                    'bg-white dark:bg-white/10',
                    'border border-zinc-300 dark:border-white/10',
                    'text-zinc-300 dark:text-black',
                    'peer-checked:border-zinc-800 peer-checked:shadow-sm peer-checked:*:opacity-100',
                    'dark:peer-checked:border-white',
                    'group-has-[.error]/checkbox:outline-1 group-has-[.error]/checkbox:outline-red-500 group-has-[.error]/checkbox:outline-offset-1',
                ])>
                    <div class="size-3 opacity-0 rounded bg-zinc-700 dark:bg-white flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="size-2.5" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-check-icon lucide-check"><path d="M20 6 9 17l-5-5"/></svg>
                    </div>
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
    </div>

    <atom:error>{{ t($error) }}</atom:error>
</label>
