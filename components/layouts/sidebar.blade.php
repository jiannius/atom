@props([
    'noindex' => true,
    'title' => '',
    'dark' => false,
    'density' => 'compact',
    'editor' => false,
    'scripts' => [],
    'styles' => [],
    'vite' => ['resources/css/app.css', 'resources/js/app.js'],
])

{{-- From lg up the top bar holds only crumbs, actions and the dark toggle (the menu button and the account are for
     small screens), so with all three empty it is not drawn there ($barEmpty, data-atom-header-empty in atom.css). --}}
@php
$user = auth()->user();
$avatar = $user?->avatar;
$name = $user?->name;

$barEmpty = ! $dark
    && ! (isset($crumbs) && filled(trim((string) $crumbs)))
    && ! (isset($actions) && filled(trim((string) $actions)));
@endphp

{{-- layouts.header takes the same props and slots (brand, context, search, nav, navfoot, crumbs, actions, dropdown,
     profile, footer), so an app can choose the shell per user. A test compares the two @props blocks. --}}
<atom:html
:noindex="$noindex"
:title="$title"
:dark="$dark"
:density="$density"
:styles="$styles"
:scripts="$scripts"
:vite="$vite"
:editor="$editor"
:attributes="$attributes->class('min-h-screen bg-canvas')">
    {{-- stashed sidebar backdrop --}}
    <div
    x-data
    x-on:click="document.body.removeAttribute('data-show-stashed-sidebar')"
    class="z-10 fixed inset-0 bg-black/20 hidden [[data-show-stashed-sidebar]_&]:block lg:[[data-show-stashed-sidebar]_&]:hidden"></div>

    {{-- sidebar --}}
    <div
    x-data="{
        isHidden: false,
        screenLg: window.innerWidth >= 1024
    }"
    x-resize.document="screenLg = window.innerWidth >= 1024"
    x-init="() => {
        $el.classList.add('-translate-x-full', 'rtl:translate-x-full')
        $el.removeAttribute('data-mobile-cloak')
    }"
    x-on:toggle-sidebar.window="isHidden = !isHidden" {{--hide sidebar on large screen, only work in large screen --}}
    x-bind:data-stashed="!screenLg || isHidden"
    x-bind:style="{
        position: 'sticky',
        top: $el.offsetTop+'px',
        'max-height': 'calc(100dvh - '+$el.offsetTop+'px)'
    }"
    x-bind:class="isHidden ? '' : 'lg:translate-x-0!'"
    @class([
        '[grid-area:sidebar] z-1 flex flex-col [:where(&)]:w-[228px] px-2.5 py-3',
        'max-h-dvh overflow-y-auto overscroll-contain transition-transform',
        'border-e border-border bg-surface-sunken',
        'max-lg:data-mobile-cloak:hidden',
        '[[data-show-stashed-sidebar]_&]:translate-x-0! [[data-show-stashed-sidebar]_&]:shadow-pop',
        'z-20! data-stashed:start-0! data-stashed:fixed! data-stashed:top-0! data-stashed:min-h-dvh! data-stashed:max-h-dvh!',
    ])
    data-mobile-cloak
    data-atom-sidebar>
        @isset ($brand)
            {{ $brand }}
        @else
            <a href="/app" class="flex items-center gap-[9px] px-2 pb-3 pt-1.5" wire:navigate>
                <div class="flex size-[26px] shrink-0 items-center justify-center rounded-control bg-accent text-accent-foreground">
                    <atom:logo class="size-4 fill-current" />
                </div>
                <span class="truncate text-sm font-semibold leading-tight">
                    {{ config('app.name') }}
                </span>
            </a>
        @endisset

        @isset ($context)
            <div class="pb-2" data-atom-shell-context>{{ $context }}</div>
        @endisset

        @isset ($search)
            <div class="pb-2.5" data-atom-shell-search>{{ $search }}</div>
        @endisset

        @isset ($nav)
            <div class="flex flex-col" data-atom-nav-shell="sidebar">{{ $nav }}</div>
        @endisset

        <div class="flex-1"></div>

        @isset ($navfoot)
            <div class="flex flex-col pt-2.5" data-atom-nav-shell="sidebar" data-atom-navfoot>{{ $navfoot }}</div>
        @endisset

        {{-- User menu (large screens; small screens have it in the top bar). The row is a button only when there is a
             menu behind it: a `profile` with no `dropdown` is plain content. --}}
        @isset ($dropdown)
            <atom:dropdown class="mt-2.5 hidden lg:block">
                <button type="button" class="flex min-h-9 w-full items-center gap-2 rounded-nav px-2 py-1 hover:bg-fg/5">
                    @isset ($profile)
                        {{ $profile }}
                    @else
                        <div class="flex min-w-0 grow items-center gap-2">
                            <div class="flex size-6 shrink-0 items-center justify-center overflow-hidden rounded-full bg-neutral-bg text-xs font-semibold text-neutral-fg">
                                @if ($avatar)
                                    <img src="{{ $avatar }}" alt="{{ $name }}" class="h-full w-full object-cover">
                                @else
                                    {{ $user?->initials() }}
                                @endif
                            </div>

                            <div class="min-w-0 text-start leading-tight">
                                <div class="truncate text-sm font-medium">
                                    {{ $user?->name }}
                                </div>
                                <div class="truncate text-xs text-fg-subtle">
                                    {{ $user?->email }}
                                </div>
                            </div>
                        </div>
                    @endisset

                    <atom:icon.dropdown class="size-3.5 shrink-0 text-fg-subtle"/>
                </button>

                <atom:menu class="w-[220px]" popover>
                    {{ $dropdown }}
                </atom:menu>
            </atom:dropdown>
        @elseif (isset($profile))
            <div class="mt-2.5 flex min-h-9 items-center gap-2 px-2 py-1" data-atom-shell-profile>{{ $profile }}</div>
        @endisset
    </div>

    {{-- The top bar: 48px, sticky, crumbs on the left and actions on the right. atom.css lets it span the page's
         rows (a sticky grid item can only travel inside its own grid area), and the first row is its height. --}}
    <header class="[grid-area:header] z-10 h-12 border-b border-border bg-canvas/88 backdrop-blur-md" @if ($barEmpty) data-atom-header-empty @endif data-atom-header>
        <div class="flex h-full items-center gap-3 px-4 lg:px-7">
            {{-- toggle sidebar in small screen, only work in small screen --}}
            <button
            type="button"
            x-data
            x-on:click="document.body.hasAttribute('data-show-stashed-sidebar') ? document.body.removeAttribute('data-show-stashed-sidebar') : document.body.setAttribute('data-show-stashed-sidebar', '')"
            class="flex h-control aspect-square shrink-0 items-center justify-center rounded-control text-fg-muted hover:bg-fg/5 hover:text-fg lg:hidden"
            aria-label="{{ t('Show sidebar') }}"
            data-atom-sidebar-toggle>
                <atom:icon.menu class="size-4"/>
            </button>

            <div class="flex min-w-0 grow items-center" data-atom-shell-crumbs>
                @isset ($crumbs)
                    {{ $crumbs }}
                @endisset
            </div>

            <div class="flex shrink-0 items-center gap-1.5" data-atom-shell-actions>
                @isset ($actions)
                    {{ $actions }}
                @endisset

                {{-- <atom:html> only emits the darkmode bootstrap when $dark, so
                     window.darkmode() doesn't exist on a light-only app and the
                     switcher's menu items throw when clicked. --}}
                @if ($dark)
                    <atom:darkmode-toggle/>
                @endif

                {{-- Account menu (small screens; large screens have it at the foot of the sidebar) --}}
                @isset ($dropdown)
                    <atom:dropdown class="lg:hidden">
                        <button type="button" class="flex items-center gap-1 rounded-full p-0.5 hover:bg-fg/5" aria-label="{{ t('Account') }}">
                            <div class="flex size-6 shrink-0 items-center justify-center overflow-hidden rounded-full bg-neutral-bg text-xs font-semibold text-neutral-fg">
                                @if ($avatar)
                                    <img src="{{ $avatar }}" alt="{{ $name }}" class="h-full w-full object-cover">
                                @else
                                    {{ $user?->initials() }}
                                @endif
                            </div>

                            <atom:icon.dropdown class="size-3.5 text-fg-subtle"/>
                        </button>

                        <atom:menu class="w-[220px]" popover>
                            @isset ($profile)
                                {{ $profile }}
                            @else
                                <div class="flex items-center gap-2 truncate p-1.5">
                                    <div class="flex size-8 shrink-0 items-center justify-center overflow-hidden rounded-full bg-neutral-bg text-xs font-semibold text-neutral-fg">
                                        @if ($avatar)
                                            <img src="{{ $avatar }}" alt="{{ $name }}" class="h-full w-full object-cover">
                                        @else
                                            {{ $user?->initials() }}
                                        @endif
                                    </div>

                                    <div class="min-w-0 text-start leading-tight">
                                        <div class="truncate text-sm font-medium">
                                            {{ $user?->name }}
                                        </div>
                                        <div class="truncate text-xs text-fg-subtle">
                                            {{ $user?->email }}
                                        </div>
                                    </div>
                                </div>
                            @endisset

                            <atom:separator class="my-2"/>

                            {{ $dropdown }}
                        </atom:menu>
                    </atom:dropdown>
                @endisset
            </div>
        </div>
    </header>

    {{-- <main> is unpadded on purpose: padding comes from <atom:page>, so a host's own section partials don't pad twice. --}}
    <main class="[grid-area:main]" data-atom-main>
        {{-- The document's outline root. There is no natural <h1> in this
             layout: the visible page title comes from <atom:breadcrumbs>, which
             is a nav landmark and emits no heading element, so without this a
             screen reader gets no outline at all. Hidden visually — the title is
             already on screen.

             It sits in front of the slot on purpose (the outline root precedes
             what it labels), which means the Livewire root is NOT the first child
             of [data-atom-main]. breadcrumbs.js matches on wire:id rather than
             position for exactly this reason — don't reintroduce a positional
             selector against this element. --}}
        @if ($title)
            <h1 class="sr-only" data-atom-page-title>{{ $title }}</h1>
        @endif

        {{ $slot }}
    </main>

    @isset ($footer)
        <footer class="[grid-area:footer] z-10 border-t border-border p-4">
            {{ $footer ?? '' }}
        </footer>
    @endisset

    <atom:alert />
    <atom:toast />
    <atom:confirm />
</atom:html>
