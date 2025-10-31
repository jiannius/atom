@props([
    'max' => null,
])

@php
$html = $slot->toHtml();
preg_match_all('/<([a-zA-Z0-9]+)\b[^>]*\bdata-atom-avatar\b[^>]*>.*?<\/\1>/si', $html, $matches);
$avatars = collect(head($matches))->filter()->values();

$more = null;
if ($max && $avatars->count() > $max) {
    $more = $avatars->count() - $max;
    $avatars = $avatars->take($max);
    $template = $avatars->last();
    $more = preg_replace(
        '/(<([a-zA-Z0-9]+)\b[^>]*\bdata-atom-avatar\b[^>]*>).*?(<\/\2>)/si',
        '$1<div class="w-full h-full flex items-center justify-center cursor-default">+'.$more.'</div>$3',
        $template
    );
}
@endphp

<div {{ $attributes->class([
    'inline-flex items-center flex-wrap',
    '[&_[data-atom-avatar]]:-ml-1.5 [&_[data-atom-avatar]]:first:ml-0',
]) }}>
    @foreach ($avatars as $avatar)
        {!! $avatar !!}
    @endforeach

    @if ($more)
        {!! $more !!}
    @endif
</div>