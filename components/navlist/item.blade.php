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
    'h-10 relative flex items-center gap-3 rounded-lg',
    'px-2.5!' => $square,
    'py-0 text-start w-full px-3 my-px',
    'text-zinc-500 dark:text-white/80',
    ...match ($accent) {
        true => [
            'data-current:text-(--color-accent-content) hover:data-current:text-(--color-accent-content)',
            'data-current:bg-white dark:data-current:bg-white/[7%] data-current:border data-current:border-zinc-200 dark:data-current:border-transparent',
            'hover:text-zinc-800 dark:hover:text-white dark:hover:bg-white/[7%] hover:bg-zinc-800/5 ',
            'border border-transparent',
        ],
        false => [
            'data-current:text-zinc-800 dark:data-current:text-zinc-100 data-current:border-zinc-200',
            'data-current:bg-white dark:data-current:bg-white/10 data-current:border data-current:border-zinc-200 dark:data-current:border-white/10 data-current:shadow-xs',
            'hover:text-zinc-800 dark:hover:text-white',
        ],
    },
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
        <atom:navlist.badge :color="$badgeColor">{{ $badge }}</atom:navlist.badge>
    @endif
</{{ $el }}>
