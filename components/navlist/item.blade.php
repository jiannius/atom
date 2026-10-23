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

/* An item is current when the PATH of its href is the path of the request: the host in an absolute href is not compared, so
   an app served from another host than APP_URL still marks its item, and a query string or a trailing slash does not
   matter. An href with no path (`?tab=x`, `#top`, none at all) is never current on its own: pass :current. A `*` in the path
   is a pattern, as it always was. */
$isCurrent = function () use ($href, $current) {
    if (is_bool($current)) return $current;

    $appUrl = trim((string) config('app.url'));
    $target = (string) $href;

    // an href under APP_URL is read relative to it, so an app installed in a sub-directory keeps working
    if ($appUrl !== '' && str($target)->startsWith($appUrl)) {
        $target = (string) str($target)->after(rtrim($appUrl, '/'));

        if ($target === '' || in_array($target[0], ['?', '#'], true)) $target = '/'.$target;
    }

    $path = parse_url($target, PHP_URL_PATH);

    if (! is_string($path) || $path === '') return false;

    $path = $path === '/' ? '/' : trim($path, '/');

    if ($path === '') return false;

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
