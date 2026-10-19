@aware(['disabled' => false])

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

// Inherit a read-only state from an enclosing <atom:form disabled>.
if ($disabled ?? false) {
    $merges['disabled'] = true;
}
@endphp

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
                        <svg xmlns="http://www.w3.org/2000/svg" class="size-3" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-check-icon lucide-check"><path d="M20 6 9 17l-5-5"/></svg>
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
