@props([
    'expandable' => false,
    'expanded' => true,
    'heading' => null,
    'hiddenIfEmpty' => true,
    'persistKey' => null,
])

{{-- The header shell's dropdown trigger points at its panel (aria-controls). The id is derived, never random: it is a
     morph key where it falls back to one, and a random one would make every render replace the control. Two groups
     with the same heading and no persist-key share a seed, so the seed is numbered by how many groups this render
     has already printed with it. The view factory's "rendered once" ledger is the counter: it is emptied when a
     top-level render ends, so the same page gets the same ids on every render.

     Known limit: that ledger is per top-level render. A layout and a Livewire component (or two Livewire
     components) are rendered separately, so groups that share a heading across them can each be "first" and print the
     same id, or be numbered differently on a later render. Groups that share a heading across a layout and a Livewire
     component need distinct `persist-key`s: the key is part of the seed, and nothing then depends on order. --}}
@php
// Namespaced so a caller's "sidebar" can't collide with the app's own entries.
$storageKey = $persistKey ? 'atom:navlist-group:'.$persistKey : null;

// A group that can start collapsed has markup the server cannot render correctly on its
// own: `expanded=false` is applied by x-show, and a persisted state is not known until
// localStorage has been read. Both wait for Alpine, so without a cloak the browser is
// free to paint the group open and snap it shut once Alpine catches up — measured at
// 655ms on a held script, and it is only invisible locally because Alpine wins the race.
// A group that simply starts open needs no correction, so it is left to render at once
// rather than waiting on Alpine to reveal it.
$cloak = $expandable && ($persistKey || $expanded !== true);

$itemsSeed = 'atom-navlist-group-'.substr(md5($persistKey.'|'.$heading), 0, 8);
$occurrence = 0;

while ($__env->hasRenderedOnce($itemsSeed.'-'.$occurrence)) {
    $occurrence++;
}

$__env->markAsRenderedOnce($itemsSeed.'-'.$occurrence);

$itemsId = $occurrence ? $itemsSeed.'-'.$occurrence : $itemsSeed;
@endphp

@if ($hiddenIfEmpty && $slot->isEmpty())

@elseif ($heading)

{{-- One markup for both shells. In the sidebar the group is a label (or, expandable, a disclosure) over its items; in
     layouts.header atom.css turns the same element into a dropdown: the label is hidden, the trigger button appears,
     and the items become a panel under it that `menu` opens. A group is rendered before the layout that holds it, so
     it can't ask which shell it is in; init() reads it from the [data-atom-nav-shell] the layout wraps the nav in. --}}
<div
x-data="{
    key: @js($expandable ? $storageKey : null),
    open: @js(!$expandable || $expanded === true),
    menu: false,
    header: false,

    init () {
        this.header = !!this.$el.closest('[data-atom-nav-shell=header]')

        if (!this.key) return

        // a stored value always wins over `expanded`, which is only the initial
        // state for a group the user has never touched
        let stored = this.read()
        if (stored !== null) this.open = stored === '1'

        // watching `open` rather than hooking the click means anything else that
        // flips it — a keyboard shortcut, a programmatic collapse-all — persists
        // for free, and the button's x-on:click stays as it was
        this.$watch('open', value => this.write(value ? '1' : '0'))
    },

    // hardened browsers and Safari private mode throw on localStorage; an
    // uncaught throw in init() kills the component and the group stops toggling
    // at all, which is far worse than not remembering
    read () {
        try {
            return window.localStorage.getItem(this.key)
        }
        catch (e) {
            return null
        }
    },

    write (value) {
        try {
            window.localStorage.setItem(this.key, value)
        }
        catch (e) {}
    },

    // the header's dropdown: Arrow Down opens it and lands on the first item
    focusFirst () {
        this.menu = true
        this.$nextTick(() => this.$refs.items.querySelector('a, button')?.focus())
    },
}"
x-bind:data-menu-open="header && menu ? '' : null"
x-on:click.outside="menu = false"
x-on:keydown.escape="if (menu) { menu = false; $refs.trigger.focus() }"
x-on:focusout="if (menu && !$el.contains($event.relatedTarget)) menu = false"
{{ $attributes->class(['group/disclosure mt-3.5 first:mt-0']) }}
data-atom-navlist-group>
    @if ($expandable)
        <button
        type="button"
        x-on:click="open = !open"
        x-bind:aria-expanded="open ? 'true' : 'false'"
        class="group/disclosure-button flex h-control w-full items-center gap-1.5 rounded-nav px-2 text-fg-subtle hover:bg-fg/5 hover:text-fg"
        data-atom-navlist-group-toggle>
            <atom:icon.down x-show="open" class="size-3!" />
            <atom:icon.right x-show="!open" class="size-3!" />

            <span class="text-xs font-medium leading-none">{{ t($heading) }}</span>
        </button>

        {{-- the disclosure is a sidebar control; layouts.header shows this label in its place (atom.css) --}}
        <div class="px-2 pb-1 pt-2 text-xs font-medium text-fg-subtle" data-atom-navlist-group-label data-for-header>{{ t($heading) }}</div>
    @else
        <div class="px-2 pb-1 pt-2 text-xs font-medium text-fg-subtle" data-atom-navlist-group-label>{{ t($heading) }}</div>
    @endif

    {{-- hidden until layouts.header asks for it (atom.css) --}}
    <button
    type="button"
    x-ref="trigger"
    x-on:click="menu = !menu"
    x-on:keydown.arrow-down.prevent="focusFirst()"
    x-bind:aria-expanded="menu ? 'true' : 'false'"
    aria-controls="{{ $itemsId }}"
    class="items-center gap-1 text-sm font-medium text-fg-muted hover:text-fg"
    data-atom-navlist-group-trigger>
        {{ t($heading) }}
        <atom:icon.down class="size-3.5 text-fg-subtle" />
    </button>

    <div
    x-ref="items"
    id="{{ $itemsId }}"
    x-show="header || open"
    @if ($cloak) x-cloak @endif
    x-on:click="menu = false"
    @class(['flex flex-col gap-px', 'ps-3' => $expandable])
    data-atom-navlist-group-items>
        {{ $slot }}
    </div>
</div>

@else
    <div {{ $attributes }} data-atom-navlist-section>
        {{ $slot }}
    </div>
@endif
