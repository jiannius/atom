@props([
    'noindex' => true,
    'title' => '',
    'dark' => true,
    'editor' => false,
    'scripts' => [],
    'styles' => [],
])

<atom:html
:noindex="$noindex"
:title="$title"
:dark="$dark"
:styles="$styles"
:scripts="$scripts"
:editor="$editor"
class="min-h-screen bg-white dark:bg-zinc-800">
    {{-- stashed sidebar backdrop --}}
    <div
    x-data
    x-on:click="document.body.removeAttribute('data-show-stashed-sidebar')"
    class="z-10 fixed inset-0 bg-black/10 hidden [[data-show-stashed-sidebar]_&]:block lg:[[data-show-stashed-sidebar]_&]:hidden"></div>

    {{-- sidebar --}}
    <div 
    x-data="{ screenLg: window.innerWidth >= 1024 }"
    x-resize.document="screenLg = window.innerWidth >= 1024"
    x-init="() => {
        $el.classList.add('-translate-x-full', 'rtl:translate-x-full')
        $el.removeAttribute('data-mobile-cloak')
        $el.classList.add('transition-transform')
    }"
    x-bind:data-stashed="!screenLg"
    x-bind:style="{
        position: 'sticky',
        top: $el.offsetTop+'px',
        'max-height': 'calc(100dvh - '+$el.offsetTop+'px)'
    }"
    @class([
        '[grid-area:sidebar] z-1 flex flex-col gap-4 [:where(&)]:w-64 p-4',
        'max-h-dvh overflow-y-auto overscroll-contain',
        'border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900',
        'max-lg:data-mobile-cloak:hidden',
        '[[data-show-stashed-sidebar]_&]:translate-x-0! lg:translate-x-0!',
        'z-20! data-stashed:start-0! data-stashed:fixed! data-stashed:top-0! data-stashed:min-h-dvh! data-stashed:max-h-dvh!',
    ])
    data-mobile-cloak
    data-atom-sidebar>
        @isset ($brand)
            {{ $brand }}
        @else
            <a href="/app" class="me-5 flex items-center space-x-2 rtl:space-x-reverse" wire:navigate>
                <div class="flex aspect-square size-8 items-center justify-center rounded-md bg-accent-content text-accent-foreground">
                    <atom:logo class="size-5 fill-current text-white dark:text-black" />
                </div>
                <div class="ms-1 grid flex-1 text-start">
                    <span class="mb-0.5 truncate leading-tight text-lg font-medium">
                        {{ config('app.name') }}
                    </span>
                </div>
            </a>
        @endisset

        {{ $nav ?? '' }}

        <div class="flex-1"></div>

        {{ $navfoot ?? '' }}

        {{-- Desktop User Menu --}}
        @if (isset($dropdown) || isset($profile))
            <atom:dropdown class="hidden lg:block">
                <button type="button" class="w-full rounded-lg flex items-center gap-2 p-1 hover:bg-zinc-800/5 dark:hover:bg-white/10">
                    @isset ($profile)
                        {{ $profile }}
                    @else
                        <div class="grow flex items-center truncate">
                            <div class="shrink-0 size-10 text-lg bg-zinc-200 dark:bg-zinc-700 rounded-lg flex items-center justify-center">
                                @if ($avatar = auth()->user()->avatar ?? null)
                                    <img src="{{ $avatar }}" alt="{{ $name }}" class="w-full h-full object-cover">
                                @else
                                    {{ auth()->user()->initials() }}
                                @endif
                            </div>

                            <div class="mx-2 text-zinc-500 text-left dark:text-white truncate">
                                <div class="font-medium truncate">
                                    {{ auth()->user()->name }}
                                </div>
                                <div class="text-sm text-zinc-500 truncate dark:text-white/50">
                                    {{ auth()->user()->email }}
                                </div>
                            </div>
                        </div>
                    @endisset

                    <atom:icon.dropdown/>
                </button>

                @isset ($dropdown)
                    <atom:menu class="w-[220px]">
                        {{ $dropdown }}
                    </atom:menu>
                @endisset
            </atom:dropdown>
        @endif
    </div>

    <header class="[grid-area:header] z-10 px-6 lg:px-8 lg:py-3" data-atom-header>
        <div class="min-h-14 lg:hidden flex items-center">
            <button
            type="button"
            x-data
            x-on:click="document.body.hasAttribute('data-show-stashed-sidebar') ? document.body.removeAttribute('data-show-stashed-sidebar') : document.body.setAttribute('data-show-stashed-sidebar', '')"
            class="shrink-0 flex items-center justify-center rounded-lg size-10 hover:bg-zinc-100 dark:hover:bg-zinc-700"
            aria-label="{{ t('Toggle sidebar') }}"
            data-atom-sidebar-toggle>
                <atom:icon.menu class="size-5"/>
            </button>

            <div class="grow"></div>

            <atom:darkmode-toggle/>

            <!-- Mobile User Menu -->
            @if (isset($dropdown) || isset($profile))
                <atom:dropdown>
                    <button type="button" class="w-full rounded-lg flex items-center gap-2 p-0.5 hover:bg-zinc-100 dark:hover:bg-zinc-700">
                        <div class="shrink-0 size-10 bg-zinc-200 dark:bg-zinc-700 rounded-lg flex items-center justify-center">
                            @if ($avatar)
                                <img src="{{ $avatar }}" alt="{{ $name }}" class="w-full h-full object-cover">
                            @else
                                {{ auth()->user()->initials() }}
                            @endif
                        </div>

                        <atom:icon.dropdown/>
                    </button>

                    @isset ($dropdown)
                        <atom:menu class="w-[220px]">
                            @isset ($profile)
                                {{ $profile }}
                            @else
                                <div class="flex items-center truncate">
                                    <div class="shrink-0 size-10 text-lg bg-zinc-200 dark:bg-zinc-700 rounded-lg flex items-center justify-center">
                                        @if ($avatar)
                                            <img src="{{ $avatar }}" alt="{{ $name }}" class="w-full h-full object-cover">
                                        @else
                                            {{ auth()->user()->initials() }}
                                        @endif
                                    </div>

                                    <div class="mx-2 text-zinc-500 text-left dark:text-white truncate">
                                        <div class="font-medium truncate">
                                            {{ auth()->user()->name }}
                                        </div>
                                        <div class="text-sm text-zinc-500 truncate dark:text-white/50">
                                            {{ auth()->user()->email }}
                                        </div>
                                    </div>
                                </div>
                            @endisset

                            <atom:separator class="my-2"/>

                            {{ $dropdown }}
                        </atom:menu>
                    @endisset
                </atom:dropdown>
            @endif
        </div>

        <div class="min-h-14 lg:min-h-16 flex items-center">
            <div class="grow">
                @persist('breadcrumbs')
                    <atom:breadcrumbs />
                @endpersist
            </div>

            <div id="header-actions" class="shrink-0"></div>
        </div>
    </header>

    <main class="[grid-area:main] p-6 lg:p-8 lg:pt-0" data-atom-main>
        {{ $slot }}
    </main>

    <footer class="[grid-area:footer] z-10 flex items-center gap-3 p-6 lg:px-8">
        <div class="grow">
            {{ $footer ?? '' }}
        </div>

        <div class="shrink-0 hidden lg:block">
            <atom:darkmode-toggle />
        </div>
    </footer>

    <atom:alert />
    <atom:toast />
    <atom:confirm />
</atom:html>
