@props([
    // refer https://ellisonleao.github.io/sharer.js for available sites (keys checked against sharer.js 0.5.4;
    // an atom icon must exist for the key, and `twitter-x` is sent to sharer.js as `x`)
    'sites' => [
        'facebook',
        'twitter-x',
        'linkedin',
        'whatsapp',
        'telegram',
        'email',
    ],
    'url' => null,
    'title' => null,
])

@php
// Site keys and icons are atom's; data-sharer needs the key sharer.js (checked against 0.5.4)
// knows. It has `twitter` and `x` but no `twitter-x`, so that one button did nothing.
$sharerKeys = ['twitter-x' => 'x'];

// A sharer whose target is an app link (email's mailto:) needs data-link="true"; without it
// sharer.js opens it in a popup, which is left blank. The other default sites are web pages.
$linkSharers = ['email'];
@endphp

<div {{ $attributes }}>
    <div class="text-sm text-fg-muted font-medium mb-2">
        {{ t('Share to') }}
    </div>

    <div x-data x-init="window.Sharer && Sharer.init()" class="flex items-center gap-2 flex-wrap">
        @foreach ($sites as $site)
            <atom:tooltip :content="str($site)->headline()->toString()">
                <button
                type="button"
                data-sharer="{{ $sharerKeys[$site] ?? $site }}"
                @if (in_array($sharerKeys[$site] ?? $site, $linkSharers, true)) data-link="true" @endif
                data-url="{{ $url }}"
                data-title="{{ $title }}"
                aria-label="{{ str($site)->headline()->toString() }}"
                class="h-control aspect-square rounded-control flex items-center justify-center cursor-pointer text-fg-muted hover:bg-fg/5 hover:text-fg">
                    <x-dynamic-component :component="'atom::icon.'.$site" class="size-4" />
                </button>
            </atom:tooltip>
        @endforeach

        <atom:tooltip :content="t('Copy Link')">
            <button
            type="button"
            x-on:click.stop="$clipboard({{ js($url) }})"
            aria-label="{{ t('Copy Link') }}"
            class="h-control aspect-square rounded-control flex items-center justify-center cursor-pointer text-fg-muted hover:bg-fg/5 hover:text-fg">
                <atom:icon.link class="size-4" />
            </button>
        </atom:tooltip>
    </div>
</div>

