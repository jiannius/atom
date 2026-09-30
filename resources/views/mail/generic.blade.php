@php
// a cta url is caller data (often a record's link); a `javascript:` one would be a live link
$ctaUrl = safe_url(data_get($cta, 'url'));
@endphp
<x-mail::message>
{!! $content !!}

@if ($cta && $ctaUrl)
<x-mail::button :url="$ctaUrl">
{!! data_get($cta, 'label') !!}
</x-mail::button>
@endif
</x-mail::message>
