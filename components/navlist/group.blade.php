@props([
    'expandable' => false,
    'expanded' => true,
    'heading' => null,
    'hiddenIfEmpty' => true,
    'persistKey' => null,
])

@php
// Namespaced so a caller's "sidebar" can't collide with the app's own entries.
$storageKey = $persistKey ? 'atom:navlist-group:'.$persistKey : null;
@endphp

@if ($hiddenIfEmpty && $slot->isEmpty())

@elseif ($expandable && $heading)

<div
x-data="{
    key: @js($storageKey),
    open: @js($expanded === true),

    init () {
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
}"
{{ $attributes->class('group/disclosure') }}
data-atom-navlist-group>
    <button
    type="button"
    x-on:click="open = !open"
    x-bind:aria-expanded="open ? 'true' : 'false'"
    class="group/disclosure-button mb-[2px] flex h-10 w-full items-center rounded-lg text-zinc-500 hover:bg-zinc-800/5 hover:text-zinc-800 lg:h-8 dark:text-white/80 dark:hover:bg-white/[7%] dark:hover:text-white">
        <div class="ps-3 pe-4">
            <atom:icon.down x-show="open" class="size-3!" />
            <atom:icon.right x-show="!open" class="size-3!" />
        </div>

        <span class="text-sm font-medium leading-none">{{ t($heading) }}</span>
    </button>

    <div x-show="open" class="relative space-y-[2px] ps-7">
        <div class="absolute inset-y-[3px] start-0 ms-4 w-px bg-zinc-200 dark:bg-white/30"></div>
        {{ $slot }}
    </div>
</div>

@elseif ($heading)
    <div class="mt-5 first:mt-0">
        <div class="px-3 pb-1.5">
            <div class="text-xs font-semibold uppercase tracking-wider text-muted">{{ t($heading) }}</div>
        </div>

        <div {{ $attributes->class(['flex flex-col']) }}>
            {{ $slot }}
        </div>
    </div>
@else
    <div {{ $attributes }}>
        {{ $slot }}
    </div>
@endif
