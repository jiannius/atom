@props([
    'noindex' => false,
    'title' => null,
    'description' => null,
    'image' => null,
    'jsonld' => null,
    'hreflang' => null,
    'canonical' => null,
    'gtm' => null,
    'ga' => null,
    'fbp' => null,
    'fonts' => 'inter',
    'dark' => false,
    'styles' => [],
    'scripts' => [],
    'editor' => false,
    'vite' => ['resources/css/app.css', 'resources/js/app.js'],
])

@php
$title ??= config('page.title') ?? config('app.name');
$description ??= config('page.description');
$image ??= config('page.image') ?? asset('storage/img/logo.png');
$jsonld ??= config('page.jsonld');
$hreflang ??= config('page.hreflang');
$canonical ??= config('page.canonical');
$gtm ??= config('page.gtm');
$ga ??= config('page.ga');
$fbp ??= config('page.fbp');
$fonts ??= config('page.fonts');
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @if ($dark) class="dark" @endif>
<head>
<title>{{ $title }}</title>
<meta charset="utf-8" />
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
@if (config('services.recaptcha.site_key'))
<meta name="recaptcha-sitekey" content="{{ config('services.recaptcha.site_key') }}">
@endif

@if ($noindex)
<meta name="robots" content="noindex, nofollow" />
@else
<meta property="og:locale" content="en_US">
<meta property="og:url" content="{{ url()->current() }}">
<meta property="og:type" content="website">
<meta property="og:title" content="{{ $title }}">
<meta property="og:description" content="{{ $description }}">
<meta property="og:image" content="{{ $image }}">
<meta property="og:image:alt" content="{{ $title }}">
<meta property="og:site_name" content="{{ $title }}">
<meta name="twitter:card" content="summary" />
<meta name="twitter:title" content="{{ $title }}">
<meta name="twitter:description" content="{{ $description }}">
<meta name="twitter:image" content="{{ $image }}">
<meta name="twitter:image:alt" content="{{ $title }}">
@endif

@stack('meta')

@if (!$noindex && $jsonld)<script type="application/ld+json">@json($jsonld)</script>@endif
@if (!$noindex && $hreflang)<link rel="alternate" href="{{ url()->current() }}" hreflang="{{ $hreflang }}"/>@endif
@if (!$noindex && $canonical)<link rel="canonical" href="{{ $canonical }}" />@endif

@if (file_exists(storage_path('app/public/img/favicon.ico')))
<link rel="icon" href="{{ asset('storage/img/favicon.ico') }}" sizes="any">
@endif

@if (file_exists(storage_path('app/public/img/favicon.svg')))
<link rel="icon" href="{{ asset('storage/img/favicon.svg') }}" type="image/svg+xml">
@endif

@if (file_exists(storage_path('app/public/img/apple-touch-icon.png')))
<link rel="apple-touch-icon" href="{{ asset('storage/img/apple-touch-icon.png') }}">
@endif

@if (!$noindex && $gtm)
<!-- Google Tag Manager -->
<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
})(window,document,'script','dataLayer','{{ $gtm }}');</script>
<!-- End Google Tag Manager -->
@endif

@if (!$noindex && $ga)
<!-- Global site tag (gtag.js) - Google Analytics -->
<script async src="https://www.googletagmanager.com/gtag/js?id={{ $ga }}"></script>
<script>
window.dataLayer = window.dataLayer || [];
function gtag(){dataLayer.push(arguments);}
gtag('js', new Date());
</script>
@endif

@if (!$noindex && $fbp)
<!-- Facebook Pixel Code -->
<script>
!function(f,b,e,v,n,t,s)
{if(f.fbq)return;n=f.fbq=function(){n.callMethod?
n.callMethod.apply(n,arguments):n.queue.push(arguments)};
if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
n.queue=[];t=b.createElement(e);t.async=!0;
t.src=v;s=b.getElementsByTagName(e)[0];
s.parentNode.insertBefore(t,s)}(window, document,'script',
'https://connect.facebook.net/en_US/fbevents.js');
fbq('init', '{{ $fbp }}');
fbq('track', 'PageView');
</script>
<!-- End Facebook Pixel Code -->
@endif

@if ($styles)
@foreach ($styles as $style)
<link rel="stylesheet" href="{{ $style }}">
@endforeach
@endif

<link rel="stylesheet" href="{{ app('atom')->asset()->version('atom.css') }}">

@if ($vite)
@vite($vite)
@endif

@stack('styles')

@if ($dark)
<style>
    :root.dark {
        color-scheme: dark;
    }
</style>
<script>
    window.darkmode = (mode = null) => {
        let applyDark = () => document.documentElement.classList.add('dark')
        let applyLight = () => document.documentElement.classList.remove('dark')

        mode = mode || window.localStorage.getItem('darkmode') || 'system'

        if (mode === 'system') {
            let media = window.matchMedia('(prefers-color-scheme: dark)')
            window.localStorage.removeItem('darkmode')
            media.matches ? applyDark() : applyLight()
        } else if (mode === 'dark') {
            window.localStorage.setItem('darkmode', 'dark')
            applyDark()
        } else if (mode === 'light') {
            window.localStorage.setItem('darkmode', 'light')
            applyLight()
        }

        document.dispatchEvent(new CustomEvent('darkmode-changed', { detail: mode }))
    }

    darkmode()
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => darkmode())
    document.addEventListener('livewire:navigated', () => darkmode())
</script>
@endif
</head>

<body {{ $attributes->only('class') }}>
    @if (!$noindex && $gtm)
    <!-- Google Tag Manager (noscript) -->
    <noscript><iframe src="https://www.googletagmanager.com/ns.html?id={{ $gtm }}" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
    <!-- End Google Tag Manager (noscript) -->
    @endif
    
    @if (!$noindex && $fbp)
    <img height="1" width="1" style="display:none" src="https://www.facebook.com/tr?id={{ $fbp }}&ev=PageView&noscript=1" />
    @endif
    
    {{ $slot }}    
</body>

@if ($fonts)
<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family={{ $fonts }}:400,500,600,700,800,900" rel="stylesheet" />
@endif

<script type="module" src="{{ app('atom')->asset()->version('atom.js') }}" data-navigate-once></script>

@stack('scripts')
</html>