{{-- Pattern: app shell (sidebar). The same nav, brand and account slots as app-shell-header.blade.php; only the
     layout tag differs. Use it for an app with many modules, or a user who sees many of them. --}}
@php
$styles = $styles ?? [];
@endphp

<atom:layouts.sidebar title="App shell (sidebar)" :vite="[]" :styles="$styles" :dark="$dark ?? true">
    <x-slot:brand>
        {{-- a brand with a subline (the company or the branch) is host markup: no component for it --}}
        <a href="?page=dashboard" class="flex items-center gap-[9px] px-2 pb-3 pt-1.5">
            <div class="flex size-[26px] shrink-0 items-center justify-center rounded-control bg-accent text-accent-foreground">
                <atom:icon.blocks class="size-4"/>
            </div>
            <span class="grid min-w-0 leading-tight">
                <span class="truncate text-sm font-semibold">Fieldwork</span>
                <span class="truncate text-xs text-fg-subtle" data-testid="brand-subline">Acme Studio · MYR</span>
            </span>
        </a>
    </x-slot>

    <x-slot:context>
        <atom:button block variant="ghost" icon-suffix="dropdown" class="justify-between!">Acme Studio</atom:button>
    </x-slot>

    <x-slot:search>
        {{-- the shortcut hint is host markup too: a <atom:kbd> laid over the end of the field, which has room for it (the input
             takes `pr-8`; widen it with a class if the hint is wider). The shortcut itself is yours to bind, or use <atom:command>. --}}
        <div class="relative" data-testid="shell-search">
            <atom:input icon="search" placeholder="Search" aria-label="Search" class="pr-12!"/>
            <atom:kbd keys="cmd+k" class="pointer-events-none absolute end-2 top-1/2 -translate-y-1/2"/>
        </div>
    </x-slot>

    <x-slot:nav>
        @include('atom::docs.patterns.nav')
    </x-slot>

    <x-slot:navfoot>
        <atom:navlist>
            <atom:navlist.item href="?page=help" icon="life-buoy">Help</atom:navlist.item>
        </atom:navlist>
    </x-slot>

    <x-slot:crumbs>
        @include('atom::docs.patterns.crumbs')
    </x-slot>

    {{-- the shell's `actions` slot is for a secondary action (or nothing): the page's primary is its header's `primary` slot --}}
    <x-slot:actions>
        <atom:button icon="download">Export</atom:button>
    </x-slot>

    <x-slot:dropdown>
        <atom:menu.item icon="settings">Account settings</atom:menu.item>
        <atom:menu.item icon="logout">Sign out</atom:menu.item>
    </x-slot>

    @include('atom::docs.patterns.page')

    {{-- no Livewire component on this page, so Livewire's own Alpine has to be loaded for atom.js --}}
    @livewireScripts
</atom:layouts.sidebar>
