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

<label {{ $attributes->only('class')->class('group/toggle inline-block space-y-2') }} data-atom-toggle>
    <div>
        <div class="flex gap-2 items-center">
            <div class="shrink-0 pt-0.5">
                <input type="checkbox" class="peer sr-only" {{ $attributes->except('class')->merge($merges) }}>

                <div
                aria-hidden="true"
                @class([
                    'group h-5 w-9 relative inline-flex items-center rounded-full transition',
                    'bg-surface border border-fg-subtle',
                    'peer-checked:bg-accent-ink peer-checked:border-accent-ink',
                    'peer-checked:[&>span]:translate-x-[19px] peer-checked:[&>span]:bg-surface',
                    'peer-disabled:opacity-50 peer-disabled:cursor-not-allowed',
                    'peer-focus-visible:outline-2 peer-focus-visible:outline-offset-1 peer-focus-visible:outline-focus',
                    'group-has-[.error]/toggle:outline-1 group-has-[.error]/toggle:outline-danger-fg group-has-[.error]/toggle:outline-offset-1',
                ])>
                    <span class="size-3 rounded-full transition translate-x-[3px] bg-fg-subtle"></span>
                </div>
            </div>
    
            <div class="text-fg">
                @if ($slot->isNotEmpty())
                    {{ $slot }}
                @elseif ($label)
                    {{ t($label) }}
                @endif
            </div>
        </div>

        @if ($caption)
            <div class="inline-flex ml-11">
                <atom:caption>{{ t($caption) }}</atom:caption>
            </div>
        @endif
    </div>

    <atom:error>{{ t($error) }}</atom:error>
</label>
