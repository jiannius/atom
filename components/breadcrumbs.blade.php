@props([
    'heading' => true,
])

{{-- With exactly one crumb the trail prints it as a heading (`heading="false"` turns that off), unless the page prints its own
     <atom:page.header>: then its title is the heading and this one would say it twice. breadcrumbs.js reads that on every
     navigation (`hasPageHeader`), since the trail is persisted across wire:navigate.

     With more than one crumb the trail is ONE line, never wrapped. Below `sm` it is only a back link to the parent (the page's
     own title says where you are). From `sm` up it shows every crumb while they fit; when they do not, breadcrumbs.js hides the
     middle ones, oldest first, behind a "..." menu, keeping the first crumb, the parent and the current page, and only then
     truncates the current page. It decides by measuring (see `fit()`): every crumb is always in the list, and a hidden one is
     taken out of the flow but kept measurable (`absolute invisible`), so the width of a crumb never depends on whether it shows.

     When the trail prints nothing (no crumb, or one crumb above a page.header, or one with `heading="false"`) and it is all a shell's
     bar holds, breadcrumbs.js marks the bar empty (`data-atom-header-empty`) so it is not drawn: see `syncHeader()`.

     A binding inside an x-for clone reads only its `item`: a field that moves with `labels` or the open state goes stale
     (CLAUDE.md, Calendar), so every flag the row needs is computed into `entries` in breadcrumbs.js. --}}

<div
x-data="breadcrumbs({ heading: @js((bool) $heading) })"
x-on:navigate-back.window="back()"
x-on:livewire:navigated.window="build()"
x-on:livewire:navigate.window="getLatestHref($event)"
class="w-full min-w-0"
data-atom-breadcrumbs>
    @if ($heading)
        <template x-if="breadcrumbs.length === 1 && ! hasPageHeader" hidden>
            <atom:heading size="xl" x-text="breadcrumbs[0].title"></atom:heading>
        </template>
    @endif

    <template x-if="breadcrumbs.length > 1" hidden>
        <div class="w-full min-w-0">
            {{-- a phone: the way up, with the parent's own address (its query string kept). The list and this link are both 24px
                 (`min-h-6`) at least: the "..." control is 24px and the crumbs 16px, so without it the row grew by 8px the
                 moment the list started to collapse, which moved the page under it (layouts.header has an auto-height row)
                 and made the ResizeObserver report a loop. --}}
            <a
            x-bind:href="atom.safeUrl(parent.href)"
            x-bind:title="parent.title"
            class="flex min-h-6 max-w-full items-center gap-1 rounded-nav text-sm font-medium leading-none text-fg-muted transition-colors hover:text-fg sm:hidden"
            wire:navigate
            data-atom-crumb-back>
                <atom:icon.left class="size-4 shrink-0 text-fg-subtle"/>
                <span class="truncate whitespace-nowrap" x-text="parent.title"></span>
            </a>

            <ol {{ $attributes->class(['relative flex min-h-6 w-full min-w-0 flex-nowrap items-center gap-1.5 overflow-hidden text-sm max-sm:hidden']) }} data-atom-crumb-list>
                <template x-for="item in entries" x-bind:key="item.key">
                    <li
                    x-bind:class="{
                        'shrink-0 max-w-40': item.type === 'crumb' && ! item.last,
                        'min-w-0 shrink': item.last,
                        'shrink-0': item.type === 'more',
                        'absolute left-0 top-0 w-max invisible pointer-events-none': item.hidden,
                    }"
                    x-bind:data-atom-crumb="item.type">
                        <template x-if="item.type === 'crumb' && ! item.last">
                            <div class="flex items-center gap-1.5 truncate">
                                <a
                                x-text="item.title"
                                x-bind:title="item.title"
                                x-bind:href="atom.safeUrl(item.href)"
                                class="leading-none font-medium truncate whitespace-nowrap rounded-nav text-fg-muted transition-colors hover:text-fg"
                                wire:navigate></a>
                                <atom:icon.right class="shrink-0 text-fg-subtle size-4"/>
                            </div>
                        </template>

                        <template x-if="item.type === 'crumb' && item.last">
                            <span x-text="item.title" x-bind:title="item.title" class="block leading-none font-medium truncate whitespace-nowrap text-fg" aria-current="page" data-atom-crumb-current></span>
                        </template>

                        {{-- the crumbs that do not fit: a real button that opens a menu of them, in order. Focus moves to the first
                             one on open, Arrow Up and Down walk them, Escape closes and focus goes back to the button. --}}
                        <template x-if="item.type === 'more'">
                            <div class="flex items-center gap-1.5" x-on:open="$nextTick(() => menuFocus($el, 0))">
                                <atom:dropdown position="bottom" align="start">
                                    <button
                                    type="button"
                                    class="flex size-6 items-center justify-center rounded-nav text-fg-muted transition-colors hover:bg-fg/5 hover:text-fg"
                                    aria-label="{{ t('Show hidden breadcrumbs') }}"
                                    title="{{ t('Show hidden breadcrumbs') }}"
                                    data-atom-crumb-more>
                                        <atom:icon.ellipsis class="size-4"/>
                                    </button>

                                    <atom:menu popover class="max-w-72" x-on:keydown="menuKey($event)" data-atom-crumb-menu>
                                        <template x-for="crumb in item.items" x-bind:key="crumb.key">
                                            <a
                                            x-bind:href="atom.safeUrl(crumb.href)"
                                            x-bind:title="crumb.title"
                                            class="flex min-h-control w-full items-center gap-2 rounded-nav px-2.5 py-1 text-left text-sm text-fg hover:bg-fg/[0.06] focus-visible:-outline-offset-2 focus-visible:bg-fg/[0.06]"
                                            role="menuitem"
                                            wire:navigate
                                            data-atom-menu-item>
                                                <span class="grow truncate leading-tight whitespace-nowrap" x-text="crumb.title"></span>
                                            </a>
                                        </template>
                                    </atom:menu>
                                </atom:dropdown>

                                <atom:icon.right class="shrink-0 text-fg-subtle size-4"/>
                            </div>
                        </template>
                    </li>
                </template>
            </ol>
        </div>
    </template>
</div>
