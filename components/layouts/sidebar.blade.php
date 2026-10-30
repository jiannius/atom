@props([
    'noindex' => true,
    'title' => '',
    'dark' => false,
    'density' => 'compact',
    'scripts' => [],
    'styles' => [],
    'vite' => ['resources/css/app.css', 'resources/js/app.js'],
    'account' => 'brand',
    'themeSwitcher' => true,
])

{{-- From lg up the top bar holds only crumbs, actions and the dark toggle (drawn only when there is no account menu to hold the theme row) (the menu button and the account are for
     small screens), so with all three empty it is not drawn there ($barEmpty, data-atom-header-empty in atom.css), except while
     the sidebar is collapsed: the bar then holds the button that brings it back, and atom.css draws it whatever the mark says. With the
     bar not drawn, the collapse button is the round one on the sidebar's edge (layouts._sidebar-circle).
     A bar that holds only crumbs ($crumbsOnly, data-atom-header-crumbs-only) can only be known empty in the browser: the trail
     is a persisted <atom:breadcrumbs> whose markup is always there, and it prints nothing for one crumb above a page.header or
     for none. breadcrumbs.js turns data-atom-header-empty on and off on it, and the server starts it on when it already knows the
     trail is one crumb above a page.header ($trailEmpty), so the first frame has no empty bar. --}}
{{-- A host's User model may have no public initials() method (or one that returns no usable string): the initials are then derived from the display name,
     and an avatar, name or email the model refuses to read (MissingAttributeException only) reads as null. --}}
@php
$user = auth()->user();
$read = function (string $attribute) use ($user) {
    try {
        return isset($user->{$attribute}) ? $user->{$attribute} : null;
    } catch (\Illuminate\Database\Eloquent\MissingAttributeException) {
        return null;
    }
};
$avatar = $read('avatar');
$name = $read('name');
$initials = $user && method_exists($user, 'initials') && (new \ReflectionMethod($user, 'initials'))->isPublic() ? $user->initials() : null;
$initials = is_string($initials) || $initials instanceof \Stringable ? trim((string) $initials) : null;
$initials = $user && blank($initials)
    ? str((string) $name)->squish()->initials()->take(2)->upper()->toString()
    : $initials;

/* The account menu opens from the brand row (`account="brand"`, the default) or from the row at the foot of the sidebar
   (`account="end"`). Either way it needs a `dropdown` slot; without one the brand row stays a plain link. */
$accountInBrand = $account !== 'end' && isset($dropdown);

/* The theme switcher lives in the account menu (a row there); the bar's own toggle is only for a shell with no account menu.
   `theme-switcher="false"` draws neither, and `dark` still emits the bootstrap, so the host's own control can call window.darkmode(). */
$showTheme = $dark && $themeSwitcher;
$themeInBar = $showTheme && ! isset($dropdown);

/* A slot counts only when it holds more than comments and whitespace: a Livewire view's `@if` blocks that all come out false
   still print `<!--[if BLOCK]-->` markers, which a string test takes for content (an empty bar drawn). A slot not passed is '' here. */
$present = fn ($slot) => $slot instanceof \Illuminate\View\ComponentSlot ? $slot->hasActualContent() : filled(trim((string) $slot));

$barEmpty = ! $themeInBar
    && ! (isset($crumbs) && $present($crumbs))
    && ! (isset($actions) && $present($actions));

$crumbsOnly = ! $barEmpty
    && ! $themeInBar
    && ! (isset($actions) && $present($actions));

/* The page mounted before this layout rendered (a full-page component, or a component in the slot), so its trail length and its
   page.header are known: a trail of one crumb above a page.header prints nothing, and the bar need not draw for the first
   frames. 0 and null are unknown (no breadcrumbs() method, or no home), and the browser stays the authority (syncHeader). */
$trailEmpty = $crumbsOnly
    && request()->attributes->get(\Jiannius\Atom\Atom::CRUMB_COUNT_ATTRIBUTE) === 1
    && preg_match('/<h1\b[^>]*\sdata-atom-page-header-title/', (string) $slot) === 1
    && app('atom')->crumbsHoldOnlyTheTrail($crumbs);
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
:attributes="$attributes->class('min-h-screen bg-canvas')">
    {{-- The sidebar collapsed from lg up is remembered in localStorage and written on <html> before the sidebar is parsed, so the
         first paint is the right one (atom.css reads the attribute). It sits in the body, not the head, so it runs again after a
         wire:navigate swaps the body in, which is when the attribute has to be written again. --}}
    <script>
        try {
            document.documentElement.toggleAttribute('data-atom-sidebar-collapsed', window.localStorage.getItem('atom-sidebar-collapsed') === '1')
        } catch (e) {}
    </script>

    {{-- stashed sidebar backdrop --}}
    <div
    x-data
    x-on:click="document.body.removeAttribute('data-show-stashed-sidebar')"
    class="z-10 fixed inset-0 bg-black/20 hidden [[data-show-stashed-sidebar]_&]:block lg:[[data-show-stashed-sidebar]_&]:hidden"></div>

    {{-- sidebar --}}
    <div
    x-data="{
        isHidden: document.documentElement.hasAttribute('data-atom-sidebar-collapsed'),
        screenLg: window.innerWidth >= 1024
    }"
    {{-- the window's own resize event, not x-resize.document: that one is a ResizeObserver whose handler changes the page's layout (the
         sidebar leaves the flow at 1024px), which the browser reports as "ResizeObserver loop completed with undelivered notifications" --}}
    x-on:resize.window="screenLg = window.innerWidth >= 1024"
    x-init="() => {
        $el.classList.add('-translate-x-full', 'rtl:translate-x-full')
        $el.removeAttribute('data-mobile-cloak')

        $watch('isHidden', (hidden) => {
            let fromBar = document.activeElement?.matches?.('[data-atom-sidebar-collapse]')

            document.documentElement.toggleAttribute('data-atom-sidebar-collapsed', hidden)

            try {
                hidden ? window.localStorage.setItem('atom-sidebar-collapsed', '1') : window.localStorage.removeItem('atom-sidebar-collapsed')
            } catch (e) {}

            $dispatch('atom-sidebar-collapsed', hidden)

            // The sidebar the focus was in (or the page, from the shortcut) is gone: the bar's button is where it goes, so the next
            // Tab does not start from the top of the document.
            if (hidden && screenLg && (!document.activeElement || document.activeElement === document.body || $el.contains(document.activeElement))) {
                $nextTick(() => document.querySelector('[data-atom-sidebar-collapse]')?.focus())
            }

            // A bar with nothing else in it goes again when the sidebar is back, and the button the focus was on with it: the round one is its twin.
            if (!hidden && fromBar) {
                $nextTick(() => {
                    let button = document.querySelector('[data-atom-sidebar-collapse]')

                    if (!button || button.getClientRects().length === 0) document.querySelector('[data-atom-sidebar-circle]')?.focus()
                })
            }
        })

        // The stashed sidebar is for below lg. A menu button press that left it open when the window grew would show it over the page.
        $watch('screenLg', (lg) => lg && document.body.removeAttribute('data-show-stashed-sidebar'))
    }"
    x-on:toggle-sidebar.window="isHidden = !isHidden" {{--hide sidebar on large screen, only work in large screen --}}
    {{-- Ctrl or Cmd + B, unless the focus is in a field or an editor (the editor's bold), a dialog is open, or the key is held down --}}
    x-on:keydown.window="(e) => {
        // an event with no key (autofill, a synthetic Event) is not ours
        let key = (e.key ?? '').toLowerCase()

        // B by its letter, or by its place on a layout that has no Latin B (not on one that has the letter elsewhere)
        if (!screenLg || e.repeat || e.defaultPrevented || !(e.ctrlKey || e.metaKey) || e.altKey || e.shiftKey) return
        if (key !== 'b' && !(e.code === 'KeyB' && !/^[a-z]$/.test(key))) return
        if (e.target?.isContentEditable || e.target?.closest?.('input, textarea, select') || document.querySelector('dialog[open]')) return

        e.preventDefault()
        isHidden = !isHidden
    }"
    x-bind:data-stashed="!screenLg || isHidden"
    x-bind:style="{
        position: 'sticky',
        top: $el.offsetTop+'px',
        'max-height': 'calc(100dvh - '+$el.offsetTop+'px)'
    }"
    x-bind:class="isHidden ? '' : 'lg:translate-x-0!'"
    @class([
        '[grid-area:sidebar] z-1 flex flex-col [:where(&)]:w-[228px] px-2.5 py-3',
        'max-h-dvh transition-transform',
        'border-e border-border bg-surface-sunken',
        'max-lg:data-mobile-cloak:hidden',
        '[[data-show-stashed-sidebar]_&]:translate-x-0! [[data-show-stashed-sidebar]_&]:shadow-pop',
        'z-20! data-stashed:start-0! data-stashed:fixed! data-stashed:top-0! data-stashed:min-h-dvh! data-stashed:max-h-dvh!',
    ])
    id="atom-sidebar"
    data-mobile-cloak
    data-atom-sidebar>
        {{-- The brand row as the account menu: the logo tile still goes home, and the app name (the user's name under
             it) and the caret open the menu. A host's `brand` slot is kept as it is (it may be a link), with a caret
             button beside it. --}}
        @if ($accountInBrand)
            <div @class(['relative flex items-center gap-1', 'pb-3' => ! isset($brand)]) data-atom-shell-brand>
                @isset ($brand)
                    <div class="min-w-0 grow">{{ $brand }}</div>

                    <atom:dropdown class="shrink-0">
                        <button type="button" class="flex h-control items-center rounded-control px-1.5 hover:bg-fg/5" data-atom-account-trigger>
                            <span class="sr-only">{{ t('Account') }}</span>
                            <atom:icon.dropdown class="size-3.5 shrink-0 text-fg-subtle"/>
                        </button>

                        <atom:layouts._account-menu :avatar="$avatar" :name="$name" :email="$read('email')" :initials="$initials" :profile="$profile ?? null" :theme="$showTheme">
                            {{ $dropdown }}
                        </atom:layouts._account-menu>
                    </atom:dropdown>

                    <atom:layouts._sidebar-circle anchor="center"/>
                @else
                    <a href="/app" class="ms-2 flex size-[26px] shrink-0 items-center justify-center rounded-control bg-accent text-accent-foreground" aria-label="{{ t('Home') }}" wire:navigate>
                        <atom:logo class="size-4 fill-current" />
                    </a>

                    <atom:dropdown class="min-w-0 grow">
                        <button type="button" class="flex w-full min-w-0 items-start gap-2 rounded-control px-1.5 py-1 text-start hover:bg-fg/5" data-atom-account-trigger>
                            <span class="grid min-w-0 grow leading-tight">
                                <span class="truncate text-sm font-semibold">{{ config('app.name') }}</span>
                                @if (filled($name))
                                    <span class="truncate text-[11px] text-fg-subtle">{{ $name }}</span>
                                @endif
                            </span>
                            <span class="sr-only">{{ t('Account') }}</span>
                            {{-- on the app name's line, not centred on the two lines --}}
                            <atom:icon.dropdown class="mt-[3px] size-3.5 shrink-0 text-fg-subtle"/>
                        </button>

                        <atom:layouts._account-menu :avatar="$avatar" :name="$name" :email="$read('email')" :initials="$initials" :profile="$profile ?? null" :theme="$showTheme">
                            {{ $dropdown }}
                        </atom:layouts._account-menu>
                    </atom:dropdown>

                    <atom:layouts._sidebar-circle anchor="caret"/>
                @endisset
            </div>
        @elseif (isset($brand))
            <div class="relative">
                {{ $brand }}

                <atom:layouts._sidebar-circle anchor="center"/>
            </div>
        @else
            <div class="relative">
                <a href="/app" class="flex items-center gap-[9px] px-2 pb-3 pt-1.5" wire:navigate>
                    <div class="flex size-[26px] shrink-0 items-center justify-center rounded-control bg-accent text-accent-foreground">
                        <atom:logo class="size-4 fill-current" />
                    </div>
                    <span class="truncate text-sm font-semibold leading-tight">
                        {{ config('app.name') }}
                    </span>
                </a>

                <atom:layouts._sidebar-circle anchor="logo"/>
            </div>
        @endif

        {{-- Everything under the brand row scrolls; the brand row stays, so the round collapse button on the sidebar's edge is not clipped by the scroll box.
             The box takes the sidebar's side padding itself (-mx / px), so a focus ring on an item is not cut at the box's edge and the scrollbar sits on the sidebar's edge. --}}
        <div class="-mx-2.5 flex min-h-0 flex-1 flex-col overflow-y-auto overscroll-contain px-2.5" data-atom-sidebar-scroll>

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

        {{-- User menu at the foot (`account="end"`; large screens, small screens have it in the top bar). The row is a
             button only when there is a menu behind it: a `profile` with no `dropdown` is plain content. --}}
        @if (isset($dropdown) && ! $accountInBrand)
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
                                    {{ $initials }}
                                @endif
                            </div>

                            <div class="min-w-0 text-start leading-tight">
                                <div class="truncate text-sm font-medium">
                                    {{ $name }}
                                </div>
                                <div class="truncate text-xs text-fg-subtle">
                                    {{ $read('email') }}
                                </div>
                            </div>
                        </div>
                    @endisset

                    <atom:icon.dropdown class="size-3.5 shrink-0 text-fg-subtle"/>
                </button>

                <atom:menu class="w-[220px]" popover>
                    @if ($showTheme)
                        <atom:layouts._theme-row/>

                        <atom:separator class="my-2"/>
                    @endif

                    {{ $dropdown }}
                </atom:menu>
            </atom:dropdown>
        @elseif (! isset($dropdown) && isset($profile))
            <div class="mt-2.5 flex min-h-9 items-center gap-2 px-2 py-1" data-atom-shell-profile>{{ $profile }}</div>
        @endif
        </div>
    </div>

    {{-- The top bar: 48px, sticky, crumbs on the left and actions on the right. atom.css lets it span the page's
         rows (a sticky grid item can only travel inside its own grid area), and the first row is its height. --}}
    <header class="[grid-area:header] z-10 h-12 border-b border-border bg-canvas/88 backdrop-blur-md" @if ($barEmpty || $trailEmpty) data-atom-header-empty @endif @if ($crumbsOnly) data-atom-header-crumbs-only @endif data-atom-header>
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

            {{-- collapse the sidebar from lg up (Ctrl or Cmd + B too): it drives the sidebar's isHidden through toggle-sidebar, and
                 keeps its own copy of the state from atom-sidebar-collapsed. The glyph is chosen by atom.css from <html>, so
                 the first paint has the right one. --}}
            <button
            type="button"
            x-data="{ collapsed: document.documentElement.hasAttribute('data-atom-sidebar-collapsed') }"
            x-on:atom-sidebar-collapsed.window="collapsed = $event.detail"
            x-on:click="$dispatch('toggle-sidebar')"
            x-bind:aria-expanded="collapsed ? 'false' : 'true'"
            aria-expanded="true"
            aria-controls="atom-sidebar"
            aria-keyshortcuts="Control+B Meta+B"
            aria-label="{{ t('Sidebar') }}"
            title="{{ t('Toggle sidebar') }} (Ctrl/⌘ B)"
            class="flex h-control aspect-square shrink-0 items-center justify-center rounded-control text-fg-muted hover:bg-fg/5 hover:text-fg max-lg:hidden"
            data-atom-sidebar-collapse>
                <atom:icon.panel-left-close class="size-4 rtl:-scale-x-100" data-atom-sidebar-glyph="collapse"/>
                <atom:icon.panel-left-open class="size-4 rtl:-scale-x-100" data-atom-sidebar-glyph="expand"/>
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
                @if ($themeInBar)
                    <atom:darkmode-toggle/>
                @endif

                {{-- Account menu (small screens, where the sidebar is stashed; large screens have it in the sidebar's brand row or foot) --}}
                @isset ($dropdown)
                    <atom:dropdown class="lg:hidden">
                        <button type="button" class="flex items-center gap-1 rounded-full p-0.5 hover:bg-fg/5" aria-label="{{ t('Account') }}">
                            <div class="flex size-6 shrink-0 items-center justify-center overflow-hidden rounded-full bg-neutral-bg text-xs font-semibold text-neutral-fg">
                                @if ($avatar)
                                    <img src="{{ $avatar }}" alt="{{ $name }}" class="h-full w-full object-cover">
                                @else
                                    {{ $initials }}
                                @endif
                            </div>

                            <atom:icon.dropdown class="size-3.5 text-fg-subtle"/>
                        </button>

                        <atom:layouts._account-menu :avatar="$avatar" :name="$name" :email="$read('email')" :initials="$initials" :profile="$profile ?? null" :theme="$showTheme">
                            {{ $dropdown }}
                        </atom:layouts._account-menu>
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
