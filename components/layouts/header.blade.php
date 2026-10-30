@props([
    'noindex' => true,
    'title' => '',
    'dark' => false,
    'density' => 'compact',
    'scripts' => [],
    'styles' => [],
    'vite' => ['resources/css/app.css', 'resources/js/app.js'],
    'account' => 'brand',
])

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

/* The account menu opens from the logo on the left (`account="brand"`, the default) or from the avatar at the bar's end
   (`account="end"`). Either way it needs a `dropdown` slot; without one the logo stays a plain link. */
$accountInBrand = $account !== 'end' && isset($dropdown);

/* The page mounted before this layout rendered, so its trail length and page.header are known: one crumb above a page.header
   prints nothing, and the crumbs row need not draw for the first frames. 0 and null are unknown; the browser stays the
   authority (breadcrumbs.js syncHeader), and the crumbs slot is asked last, since it parses it: crumbsHoldOnlyTheTrail() applies the browser's
   "alone" rule on the server, so a host that wraps the trail or puts a sibling beside it is not drawn empty and then put back (a layout shift). */
$trailEmpty = isset($crumbs)
    && request()->attributes->get(\Jiannius\Atom\Atom::CRUMB_COUNT_ATTRIBUTE) === 1
    && preg_match('/<h1\b[^>]*\sdata-atom-page-header-title/', (string) $slot) === 1
    && app('atom')->crumbsHoldOnlyTheTrail($crumbs);
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
:attributes="$attributes->class('min-h-screen bg-canvas')">
    {{-- The bar: 52px, sticky, hairline below. atom.css lets it span the page's rows (a sticky grid item can only
         travel inside its own grid area). Not `relative`: below lg the nav panel is positioned against this element. --}}
    <header
    x-data="headerShell"
    x-on:keydown.escape.capture="escape($event)"
    x-on:click.outside="closeAll()"
    x-on:focusout="if (search && $event.relatedTarget && !$refs.search.contains($event.relatedTarget) && $event.relatedTarget !== $refs.searchButton) search = false"
    x-on:livewire:navigate.window="closeAll()"
    class="[grid-area:header] z-20 h-13 border-b border-border bg-surface/92 backdrop-blur-md"
    data-atom-header
    data-atom-header-bar>
        <div class="mx-auto flex h-full w-full max-w-[1248px] items-center gap-5 ps-[max(1.5rem,env(safe-area-inset-left,0px))] pe-[max(1.5rem,env(safe-area-inset-right,0px))] max-lg:gap-2.5 max-lg:ps-[max(1rem,env(safe-area-inset-left,0px))] max-lg:pe-[max(1rem,env(safe-area-inset-right,0px))]">
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

            {{-- The logo as the account menu: the logo tile still goes home, and the app name and the caret open the menu
                 (on a phone the name is visually hidden, so the caret is the trigger). A host's `brand` slot is kept as
                 it is (it may be a link), with a compact avatar trigger beside it. --}}
            @if ($accountInBrand)
                <div class="flex shrink-0 items-center gap-0.5" data-atom-shell-brand>
                    @isset ($brand)
                        {{ $brand }}

                        <atom:dropdown>
                            <button type="button" class="flex h-control items-center gap-1 rounded-control px-1 hover:bg-fg/5" data-atom-account-trigger>
                                <div class="flex size-6 shrink-0 items-center justify-center overflow-hidden rounded-full bg-neutral-bg text-xs font-semibold text-neutral-fg">
                                    @if ($avatar)
                                        <img src="{{ $avatar }}" alt="" class="h-full w-full object-cover">
                                    @else
                                        <span aria-hidden="true">{{ $initials }}</span>
                                    @endif
                                </div>
                                <span class="sr-only">{{ t('Account') }}</span>
                                <atom:icon.dropdown class="size-3.5 shrink-0 text-fg-subtle"/>
                            </button>

                            <atom:layouts._account-menu :avatar="$avatar" :name="$name" :email="$read('email')" :initials="$initials" :profile="$profile ?? null">
                                {{ $dropdown }}
                            </atom:layouts._account-menu>
                        </atom:dropdown>
                    @else
                        <a href="/app" class="flex size-[22px] shrink-0 items-center justify-center rounded-nav bg-accent text-accent-foreground" aria-label="{{ t('Home') }}" wire:navigate>
                            <atom:logo class="size-3.5 fill-current" />
                        </a>

                        <atom:dropdown>
                            <button type="button" class="flex h-control items-center gap-1 rounded-control px-1.5 text-sm font-semibold tracking-tight hover:bg-fg/5" data-atom-account-trigger>
                                <span class="max-sm:sr-only">{{ config('app.name') }}</span>
                                <span class="sr-only">{{ t('Account') }}</span>
                                <atom:icon.dropdown class="size-3.5 shrink-0 text-fg-subtle"/>
                            </button>

                            <atom:layouts._account-menu :avatar="$avatar" :name="$name" :email="$read('email')" :initials="$initials" :profile="$profile ?? null">
                                {{ $dropdown }}
                            </atom:layouts._account-menu>
                        </atom:dropdown>
                    @endisset
                </div>
            @elseif (isset($brand))
                {{ $brand }}
            @else
                <a href="/app" class="flex shrink-0 items-center gap-2 text-sm font-semibold tracking-tight" wire:navigate>
                    <div class="flex size-[22px] items-center justify-center rounded-nav bg-accent text-accent-foreground">
                        <atom:logo class="size-3.5 fill-current" />
                    </div>
                    <span class="max-sm:sr-only">{{ config('app.name') }}</span>
                </a>
            @endif

            {{-- The nav slot: the bar's items from lg up, a panel under the bar below it. The context and search slots
                 live in the same element, so each is one copy of the host's markup: in the bar, beside the logo and
                 at its end (from lg to xl the search is a popover the search button opens), and the first two rows of
                 the panel in menu mode, where a workspace switcher and the search stay reachable. The priority-plus
                 "More" dropdown follows the nav slot (headerShell). --}}
            <div id="atom-header-nav" x-ref="nav" x-bind:data-open="menu ? '' : null" data-atom-nav-shell="header" data-atom-header-nav>
                @isset ($context)
                    <span class="-ms-2 text-lg font-light text-border-strong" aria-hidden="true" data-atom-shell-context-divider>/</span>
                    <div data-atom-shell-context>{{ $context }}</div>
                @endisset

                @isset ($search)
                    <div id="atom-header-search" x-ref="search" x-bind:data-open="search ? '' : null" data-atom-shell-search>{{ $search }}</div>
                @endisset

                @isset ($nav)
                    {{ $nav }}

                    {{-- "More": copies of the nav items that do not fit the bar, written by headerShell. It is the group
                         dropdown's markup, so atom.css draws it the same way, and it is not shown until something moved. --}}
                    <div
                    x-ref="more"
                    x-bind:data-menu-open="more ? '' : null"
                    x-on:focusout="if (more && $event.relatedTarget && !$el.contains($event.relatedTarget)) more = false"
                    data-atom-navlist-group
                    data-atom-header-more>
                        <button
                        type="button"
                        x-ref="moreButton"
                        x-on:click="toggleMore()"
                        x-on:keydown.arrow-down.prevent="openMore()"
                        x-bind:aria-expanded="more ? 'true' : 'false'"
                        aria-controls="atom-header-more-items"
                        class="items-center gap-1 text-sm font-medium text-fg-muted hover:text-fg"
                        data-atom-navlist-group-trigger>
                            {{ t('More') }}
                            <atom:icon.down class="size-3.5 text-fg-subtle" />
                        </button>

                        <div x-ref="moreItems" id="atom-header-more-items" x-on:click="more = false" wire:ignore class="flex flex-col gap-px" data-atom-navlist-group-items></div>
                    </div>
                @endisset

                @isset ($navfoot)
                    <div class="mt-2 border-t border-border pt-2 lg:mt-0 lg:border-0 lg:pt-0" data-atom-navfoot>{{ $navfoot }}</div>
                @endisset
            </div>

            <div class="ms-auto flex shrink-0 items-center gap-2" data-atom-shell-actions>
                {{-- From lg to xl the field does not fit the bar: this button opens it in a popover. Below lg the field is the
                     menu panel's second row, from xl it is inline (atom.css decides which is shown). --}}
                @isset ($search)
                    <button
                    type="button"
                    x-ref="searchButton"
                    x-on:click="toggleSearch()"
                    x-bind:aria-expanded="search ? 'true' : 'false'"
                    aria-controls="atom-header-search"
                    aria-label="{{ t('Search') }}"
                    class="h-control aspect-square shrink-0 items-center justify-center rounded-control text-fg-muted hover:bg-fg/5 hover:text-fg"
                    data-atom-shell-search-button>
                        <atom:icon.search class="size-4"/>
                    </button>
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

                {{-- The avatar at the bar's end (`account="end"`) is a button only when there is a menu behind it. A `profile`
                     with no `dropdown` is plain content. --}}
                @if (isset($dropdown) && ! $accountInBrand)
                    <atom:dropdown position="bottom" align="end">
                        <button type="button" class="flex size-7 items-center justify-center overflow-hidden rounded-full bg-neutral-bg text-xs font-semibold text-neutral-fg" aria-label="{{ t('Account') }}">
                            @if ($avatar)
                                <img src="{{ $avatar }}" alt="{{ $name }}" class="h-full w-full object-cover">
                            @else
                                {{ $initials }}
                            @endif
                        </button>

                        <atom:layouts._account-menu :avatar="$avatar" :name="$name" :email="$read('email')" :initials="$initials" :profile="$profile ?? null">
                            {{ $dropdown }}
                        </atom:layouts._account-menu>
                    </atom:dropdown>
                @elseif (isset($profile))
                    <div class="min-w-0" data-atom-shell-profile>{{ $profile }}</div>
                @endif
            </div>
        </div>
    </header>

    @isset ($crumbs)
        <div class="[grid-area:crumbs] mx-auto flex w-full max-w-[1248px] min-w-0 items-center ps-[max(1.5rem,env(safe-area-inset-left,0px))] pe-[max(1.5rem,env(safe-area-inset-right,0px))] pt-4 max-lg:ps-[max(1rem,env(safe-area-inset-left,0px))] max-lg:pe-[max(1rem,env(safe-area-inset-right,0px))]" @if ($trailEmpty) data-atom-header-empty @endif data-atom-header-crumbs data-atom-shell-crumbs>
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
