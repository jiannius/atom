@props([
    'align' => 'center',
])

<div role="none" {{ $attributes->class('w-full flex items-center') }} data-atom-separator>
    @if (in_array($align, ['center', 'right']))
        <div class="border-0 bg-zinc-200 h-px w-full dark:bg-zinc-600"></div>
    @endif

    @if ($slot->isNotEmpty())
        <span @class([
            'shrink whitespace-nowrap text-center text-muted dark:text-muted-foreground',
            $attributes->get('class', 'font-medium uppercase text-sm'),
            'ml-4' => $align === 'right',
            'mr-4' => $align === 'left',
            'mx-4' => $align === 'center',
        ])>{{ $slot }}</span>

        @if (in_array($align, ['center', 'left']))
            <div class="border-0 bg-zinc-200 h-px w-full dark:bg-zinc-600"></div>
        @endif
    @endif
</div>
