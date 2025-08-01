@props([
    'name' => null,
    'label' => null,
    'caption' => null,
    'required' => false,
    'error' => null,
])

@php
$name ??= $attributes->wire('model')->value();
$error ??= $errors?->first($name);
$merges = ['name' => $name];
@endphp

<label class="group/toggle inline-block space-y-2" data-atom-toggle>
    <div>
        <div class="flex gap-2 items-center">
            <div class="shrink-0 pt-0.5">
                <input type="checkbox" class="peer hidden" {{ $attributes->merge($merges) }}>
    
                <div
                tabindex="0"
                role="checkbox"
                @class([
                    'group h-5 w-8 relative inline-flex items-center rounded-full transition',
                    'bg-zinc-200 dark:bg-zinc-900 peer-checked:bg-zinc-900 dark:peer-checked:bg-white',
                    'border border-zinc-200 dark:border-zinc-600',
                    'peer-checked:[&>span]:translate-x-[12px] dark:peer-checked:[&>span]:bg-zinc-900',
                    'peer-disabled:opacity-40 peer-disabled:cursor-not-allowed',
                    'focus:outline-none focus:ring-1 focus:ring-offset-1 focus:ring-primary',
                    'group-has-[.error]/toggle:outline-1 group-has-[.error]/toggle:outline-red-500 group-has-[.error]/toggle:outline-offset-1',
                ])>
                    <span class="size-3.5 rounded-full transition translate-x-[2px] bg-white"></span>
                </div>
            </div>
    
            <div class="dark:text-white">
                @if ($slot->isNotEmpty())
                    {{ $slot }}
                @elseif ($label)
                    {!! t($label) !!}
                @endif
            </div>
        </div>

        @if ($caption)
            <div class="inline-flex ml-6.5">
                <atom:caption>{{ t($caption) }}</atom:caption>
            </div>
        @endif
    </div>

    <atom:error>{{ t($error) }}</atom:error>
</label>
