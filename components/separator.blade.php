@props([
    'align' => 'center',
])

<div role="none" {{ $attributes->class('w-full flex items-center') }} data-atom-separator>
    @if (in_array($align, ['center', 'right']))
        <div class="border-0 bg-border h-px w-full"></div>
    @endif

    @if ($slot->isNotEmpty())
        <span @class([
            'shrink whitespace-nowrap text-center text-fg-muted',
            'font-medium uppercase tracking-wide text-xs',
            'ml-4' => $align === 'right',
            'mr-4' => $align === 'left',
            'mx-4' => $align === 'center',
        ])>{{ $slot }}</span>

        @if (in_array($align, ['center', 'left']))
            <div class="border-0 bg-border h-px w-full"></div>
        @endif
    @endif
</div>
