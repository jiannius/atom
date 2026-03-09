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
// Button should be a square if it has no text contents...
$square ??= $slot->isEmpty();

$el = $as ??= $href ? 'a' : 'button';

// check href is current request url
$isCurrent = function () use ($href, $current) {
    if (is_bool($current)) return $current;

    $hrefToCheck = str($href)->startsWith(trim(config('app.url'))) ? (string) str($href)->after(trim(config('app.url'), '/')) : $href;
    $hrefToCheck = $hrefToCheck === '' ? '/' : $hrefToCheck;
    $hrefToCheck = $hrefToCheck === '/' ? '/' : trim($hrefToCheck, '/');

    if (!$hrefToCheck) return false;

    // Support current route detection during Livewire update requests as well...
    return app('livewire')?->isLivewireRequest()
        ? str()->is($hrefToCheck, app('livewire')->originalPath())
        : request()->is($hrefToCheck);
};

$classes = Arr::toCssClasses([
    'relative flex items-center gap-3 rounded-lg',
    'px-2.5!' => $square,
    'py-2 px-3 text-start w-full border border-transparent',
    'text-zinc-500 dark:text-white/80',
    'hover:bg-zinc-100 dark:hover:bg-zinc-800',
    'hover:text-zinc-800 dark:hover:text-white',
    'data-current:bg-zinc-100 data-current:text-zinc-800 data-current:border-zinc-200 data-current:px-3',
    'dark:data-current:bg-zinc-700 dark:data-current:text-white dark:data-current:border-transparent',
]);
@endphp

<{{ $el }} {{ $attributes->class($classes)->merge([
    'type' => $el === 'button' ? 'button' : false,
    'href' => $el === 'button' ? false : $href,
    'data-current' => $isCurrent(),
    'data-atom-navlist-item' => true,
]) }}>
    @if ($icon)
        <div class="relative">
            <x-dynamic-component :component="'atom::icon.'.$icon" class="block size-5"/>

            @if ($iconDot)
                <div class="absolute top-[-2px] end-[-2px]">
                    <div class="size-[6px] rounded-full bg-zinc-500 dark:bg-zinc-400"></div>
                </div>
            @endif
        </div>
    @endif

    @if ($slot->isNotEmpty())
        <div class="flex-1 font-medium leading-none whitespace-nowrap [[data-nav-footer]_&]:hidden [[data-nav-sidebar]_[data-nav-footer]_&]:block" data-content>
            {{ $slot }}
        </div>
    @endif

    @if ($iconSuffix)
        <x-dynamic-component :component="'atom::icon.'.$iconSuffix" class="block size-4"/>
    @endif

    @if ($badge)
        <atom:navlist.badge :attributes="$badge->attributes" class="-mr-1.5">{{ $badge }}</atom:navlist.badge>
    @elseif ($count)
        <div class="text-xs text-right text-muted dark:text-muted-foreground">
            {{ $count }}
        </div>
    @endif
</{{ $el }}>
