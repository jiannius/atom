@props([
    'number' => null,
    'text' => null,
])

@php
$url = collect([
    'https://wa.me/'.$number,
    $text ? 'text='.$text : null,
])->filter()->join('?');
@endphp

<a
href="{{ $url }}"
target="_blank"
style="z-index: 200;"
@class([
    'bg-green-500 rounded-full shadow flex items-center justify-center gap-3 py-2 px-5 text-lg text-white transition-transform hover:scale-110',
    $attributes->get('class', 'fixed right-14 bottom-14'),
])>
    <atom:icon.whatsapp /> {{ t('Whatsapp Us') }}
</a>
