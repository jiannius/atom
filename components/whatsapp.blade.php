@props([
    'number' => null,
    'text' => null,
])

@php
$url = collect([
    'https://wa.me/'.$number,
    $text ? 'text='.$text : null,
])->filter()->join('?');

// a caller `style` is appended last so it wins
$style = Arr::toCssStyles(array_filter([
    'z-index: 200',
    $attributes->get('style'),
]));
@endphp

{{-- The position is a [:where(&)] default (zero specificity), so a caller's
     `fixed bottom-32 right-14` wins without having to replace the whole class. --}}
<a
href="{{ $url }}"
style="{{ $style }}"
{{ $attributes->except(['href', 'style'])->merge(['target' => '_blank'])->class([
    'bg-success-fg text-surface rounded-pill shadow-pop flex items-center justify-center gap-2 h-control-lg px-5 text-sm font-medium transition-transform hover:scale-105',
    '[:where(&)]:fixed [:where(&)]:right-14 [:where(&)]:bottom-14',
]) }}>
    <atom:icon.whatsapp class="size-4" /> {{ t('Whatsapp Us') }}
</a>
