@props([
    'as' => null,
    'href' => null,
    'icon' => null,
    'iconDot' => false,
    'iconSuffix' => null,
    'badgeColor' => null,
    'accent' => true,
    'badge' => null,
    'current' => null,
    'count' => null,
])

@php
$href = safe_url($href);

$square ??= $slot->isEmpty();

$el = $as ??= $href ? 'a' : 'button';

/* An item is current when the PATH of its href is the path of the request. A query string, a fragment and a trailing slash do not
   matter, and the host of an absolute href is not compared with APP_URL as a string: an href that names a host counts only when
   that host is the request's or APP_URL's (the port is ignored), so an app served from another domain than APP_URL still
   marks its item while an external link (`https://github.com/orders` on /orders) never does. APP_URL's own path prefix is
   stripped, nothing else of it. An href with no host and no path (`?tab=x`, `#top`, none) is never current on its own: pass :current; one that names this app's host and no path (`url('/')`) is the home page. A scheme other than http or https (`mailto:`, `tel:`) is never current.
   A `*` in the path is a pattern, as it always was. */
$isCurrent = function () use ($href, $current) {
    if (is_bool($current)) return $current;

    $parts = parse_url(trim((string) $href));

    if (! is_array($parts)) return false;

    // mailto:, tel: and the like are not pages of this app
    if (isset($parts['scheme']) && ! in_array(strtolower($parts['scheme']), ['http', 'https'], true)) return false;

    $host = strtolower((string) ($parts['host'] ?? ''));

    if ($host !== '') {
        $appHost = strtolower((string) parse_url(trim((string) config('app.url')), PHP_URL_HOST));

        if ($host !== strtolower(request()->getHost()) && $host !== $appHost) return false;
    }

    $path = (string) ($parts['path'] ?? '');

    // an app installed under a path (APP_URL=https://host/sub) is read from that path
    $prefix = trim((string) parse_url(trim((string) config('app.url')), PHP_URL_PATH), '/');

    if ($prefix !== '' && ($path === '/'.$prefix || str_starts_with($path, '/'.$prefix.'/'))) {
        $path = substr($path, strlen($prefix) + 1);
    }

    $path = trim($path, '/');

    // an href with no host and no path (`?tab=x`, `#top`, none) is not the home page; one that names this app's host and no path
    // (`route('home')`, `url('/')`: `http://localhost`) is, as is '/' (which reads as an empty path after the trim and the prefix)
    if ($path === '' && $host === '' && ($parts['path'] ?? '') === '') return false;

    $path = $path === '' ? '/' : $path;

    return app('livewire')?->isLivewireRequest()
        ? str()->is($path, app('livewire')->originalPath())
        : request()->is($path);
};

$active = $isCurrent();

$classes = Arr::toCssClasses([
    'relative flex h-control w-full items-center gap-[9px] rounded-nav px-2 text-start text-sm font-medium',
    'px-2.5!' => $square,
    'text-fg-muted hover:bg-fg/5 hover:text-fg',
    '[&[data-current]]:bg-surface [&[data-current]]:text-fg [&[data-current]]:shadow-[0_0_0_1px_var(--atom-border)]',
]);
@endphp

{{-- The look is the sidebar's: a 28px row, 16px icon, a hairline ring when current. atom.css restyles this same
     markup as a top-bar item inside layouts.header, keyed on [data-atom-nav-shell="header"], so one nav slot
     serves both shells. --}}
<{{ $el }} {{ $attributes->class($classes)->merge([
    'type' => $el === 'button' ? 'button' : false,
    'href' => $el === 'button' ? false : $href,
    'data-current' => $active,
    'aria-current' => $active ? 'page' : false,
    'data-atom-navlist-item' => true,
]) }}>
    @if ($icon)
        <div class="relative flex shrink-0 items-center" data-atom-navlist-icon>
            <x-dynamic-component :component="'atom::icon.'.$icon" class="block size-4"/>

            @if ($iconDot)
                <div class="absolute top-[-2px] end-[-2px]">
                    <div class="size-[6px] rounded-full bg-fg-subtle"></div>
                </div>
            @endif
        </div>
    @endif

    @if ($slot->isNotEmpty())
        <div class="flex-1 truncate whitespace-nowrap leading-none" data-content>
            {{ $slot }}
        </div>
    @endif

    @if ($iconSuffix)
        <x-dynamic-component :component="'atom::icon.'.$iconSuffix" class="block size-4"/>
    @endif

    @if ($badge)
        <atom:navlist.badge :attributes="$badge->attributes" class="-me-1">{{ $badge }}</atom:navlist.badge>
    @elseif ($count)
        <div class="ms-auto text-xs tabular-nums text-fg-subtle" data-atom-navlist-count>
            {{ $count }}
        </div>
    @endif
</{{ $el }}>
