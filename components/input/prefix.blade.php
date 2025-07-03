@aware(['size'])

@php
$prefix = $attributes->get('prefix');
$suffix = $attributes->get('suffix');

$classes = $attributes->classes()
    ->add('shrink-0 border border-zinc-200 border-b-zinc-300/80 shadow-sm overflow-hidden')
    ->add('px-6 flex items-center justify-center bg-zinc-100')
    ->add($size === 'sm' ? 'h-8 text-sm' : 'h-10')
    ;
@endphp

<div class="flex items-center w-full">
    @if ($prefix)
        <div {{ $attributes->class([$classes, 'rounded-l-lg border-r-0'])->only('class') }} data-atom-input-prefix>
            @t($prefix)
        </div>
    @endif

    <div class="{{ collect([
        'grow',
        '[[data-atom-input-prefix]+&>[data-atom-input]>input]:rounded-l-none',
        '[[data-atom-input-prefix]+&>[data-atom-input]>input]:-ml-px',
        '[&:has(+[data-atom-input-suffix])>[data-atom-input]>input]:rounded-r-none',
        '[[data-atom-input-prefix]+&>[data-atom-select-native]>select]:rounded-l-none',
        '[[data-atom-input-prefix]+&>[data-atom-select-native]>select]:-ml-px',
        '[&:has(+[data-atom-input-suffix])>[data-atom-select-native]>select]:rounded-r-none',
        '[[data-atom-input-prefix]+&>[data-atom-select-listbox-trigger]>button]:rounded-l-none',
        '[[data-atom-input-prefix]+&>[data-atom-select-listbox-trigger]>button]:-ml-px',
        '[&:has(+[data-atom-input-suffix])>[data-atom-select-listbox-trigger]>button]:rounded-r-none',
        '[[data-atom-input-prefix]+&>[data-atom-date-picker]>[data-anchor]>input]:rounded-l-none',
        '[[data-atom-input-prefix]+&>[data-atom-date-picker]>[data-anchor]>input]:-ml-px',
        '[&:has(+[data-atom-input-suffix])>[data-atom-date-picker]>[data-anchor]>input]:rounded-r-none',
    ])->filter()->join(' ') }}">
        {{ $slot }}
    </div>

    @if ($suffix)
        <div {{ $attributes->class([$classes, 'rounded-r-lg border-l-0 -ml-px'])->only('class') }} data-atom-input-suffix>
            @t($suffix)
        </div>
    @endif
</div>
