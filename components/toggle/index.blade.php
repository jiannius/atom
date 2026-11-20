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

// 'self', 'inherited' from a disabled form, or null; a toggle's own `disabled` (`:disabled="false"` too) wins over the form's.
$disabledBy = \Jiannius\Atom\Services\Attributes::disabledBy($attributes);

$merges = [
    'name' => $name,
    'required' => $required ?: null,
    'disabled' => $disabledBy ? true : null,
    'data-atom-disabled' => $disabledBy,
];
@endphp

<label {{ $attributes->only('class')->class('group/toggle inline-block space-y-2') }} data-atom-toggle>
    <div>
        <div class="flex gap-2 items-center">
            <div class="shrink-0 pt-0.5">
                <input type="checkbox" class="peer sr-only" {{ $attributes->except('class')->merge($merges) }}>

                <atom:checkbox._indicator kind="toggle" />
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
