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

@php
$user = auth()->user();
$avatar = $user?->avatar;
$name = $user?->name;
@endphp

{{-- The top-nav shell, for an app with about six or fewer sections. It takes the same props and slots as
     layouts.sidebar (brand, context, search, nav, navfoot, crumbs, actions, dropdown, profile, footer), and the same
     `nav` markup, so an app can choose the shell per user. A test compares the two @props blocks.

     The nav slot is not rewritten for this shell: it is wrapped in [data-atom-nav-shell="header"], and atom.css draws
     the navlist inside it as bar items (a navlist.group as a dropdown). The host chooses the shell; atom never
     switches on its own. --}}
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
    {{-- The bar: 52px, sticky, hairline below. atom.css lets it span the page's rows (a sticky grid item can only
         travel inside its own grid area). Not `relative`: below md the nav panel is positioned against this element. --}}
    <header
    x-data="{ menu: false }"
    x-on:keydown.escape="if (menu) { menu = false; $refs.menuButton.focus() }"
    x-on:click.outside="menu = false"
    x-on:livewire:navigate.window="menu = false"
    class="[grid-area:header] z-20 h-13 border-b border-border bg-surface/92 backdrop-blur-md"
    data-atom-header
    data-atom-header-bar>
        <div class="mx-auto flex h-full w-full max-w-[1248px] items-center gap-5 ps-[max(1.5rem,env(safe-area-inset-left,0px))] pe-[max(1.5rem,env(safe-area-inset-right,0px))] max-md:gap-2.5 max-md:ps-[max(1rem,env(safe-area-inset-left,0px))] max-md:pe-[max(1rem,env(safe-area-inset-right,0px))]">
            <button
            type="button"
            x-ref="menuButton"
            x-on:click="menu = !menu"
            x-bind:aria-expanded="menu ? 'true' : 'false'"
            aria-controls="atom-header-nav"
            aria-label="{{ t('Menu') }}"
            class="h-control aspect-square shrink-0 items-center justify-center rounded-control text-fg-muted hover:bg-fg/5 hover:text-fg"
            data-atom-header-menu-button>
                <atom:icon.menu class="size-4"/>
            </button>

            @isset ($brand)
                {{ $brand }}
            @else
                <a href="/app" class="flex shrink-0 items-center gap-2 text-sm font-semibold tracking-tight" wire:navigate>
                    <div class="flex size-[22px] items-center justify-center rounded-nav bg-accent text-accent-foreground">
                        <atom:logo class="size-3.5 fill-current" />
                    </div>
                    <span class="max-sm:sr-only">{{ config('app.name') }}</span>
                </a>
            @endisset

            @isset ($context)
                <span class="-mx-2 text-lg font-light text-border-strong max-md:hidden" aria-hidden="true">/</span>
                <div class="shrink-0 max-md:hidden" data-atom-shell-context>{{ $context }}</div>
            @endisset

            {{-- the nav slot: the bar's items from md up, a panel under the bar below it --}}
            <div id="atom-header-nav" x-bind:data-open="menu ? '' : null" data-atom-nav-shell="header" data-atom-header-nav>
                @isset ($nav)
                    {{ $nav }}
                @endisset

                @isset ($navfoot)
                    <div class="mt-2 border-t border-border pt-2 md:mt-0 md:border-0 md:pt-0" data-atom-navfoot>{{ $navfoot }}</div>
                @endisset
            </div>

            <div class="ms-auto flex shrink-0 items-center gap-2" data-atom-shell-actions>
                @isset ($search)
                    <div class="max-md:hidden" data-atom-shell-search>{{ $search }}</div>
                @endisset

                @isset ($actions)
                    {{ $actions }}
                @endisset

                {{-- <atom:html> only emits the darkmode bootstrap when $dark, so
                     window.darkmode() doesn't exist on a light-only app and the
                     switcher's menu items throw when clicked. --}}
                @if ($dark)
                    <atom:darkmode-toggle/>
                @endif

                @if (isset($dropdown) || isset($profile))
                    <atom:dropdown position="bottom" align="end">
                        <button type="button" class="flex size-7 items-center justify-center overflow-hidden rounded-full bg-neutral-bg text-xs font-semibold text-neutral-fg" aria-label="{{ t('Account') }}">
                            @if ($avatar)
                                <img src="{{ $avatar }}" alt="{{ $name }}" class="h-full w-full object-cover">
                            @else
                                {{ $user?->initials() }}
                            @endif
                        </button>

                        @isset ($dropdown)
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
                        @endisset
                    </atom:dropdown>
                @endif
            </div>
        </div>
    </header>

    @isset ($crumbs)
        <div class="[grid-area:crumbs] mx-auto flex w-full max-w-[1248px] min-w-0 items-center ps-[max(1.5rem,env(safe-area-inset-left,0px))] pe-[max(1.5rem,env(safe-area-inset-right,0px))] pt-4 max-md:ps-[max(1rem,env(safe-area-inset-left,0px))] max-md:pe-[max(1rem,env(safe-area-inset-right,0px))]" data-atom-header-crumbs data-atom-shell-crumbs>
            {{ $crumbs }}
        </div>
    @endisset

    {{-- <main> is a centred 1248px column and unpadded on purpose: padding comes from <atom:page>, so a host's own
         section partials don't pad twice, and a page's content lines up with the bar's. --}}
    <main class="[grid-area:main] mx-auto w-full max-w-[1248px]" data-atom-main>
        {{-- The document's outline root, as in layouts.sidebar: the visible page title comes from the page, so this
             <h1> is hidden visually. It sits in front of the slot on purpose; breadcrumbs.js matches on wire:id
             rather than position, so don't reintroduce a positional selector against this element. --}}
        @if ($title)
            <h1 class="sr-only" data-atom-page-title>{{ $title }}</h1>
        @endif

        {{ $slot }}
    </main>

    @isset ($footer)
        <footer class="[grid-area:footer] z-10 mx-auto w-full max-w-[1248px] px-6 py-4">
            {{ $footer ?? '' }}
        </footer>
    @endisset

    <atom:alert />
    <atom:toast />
    <atom:confirm />
</atom:html>
