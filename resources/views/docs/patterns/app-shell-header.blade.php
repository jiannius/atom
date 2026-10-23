{{-- Pattern: app shell (top nav). The same nav, brand and account slots as app-shell-sidebar.blade.php; only the
     layout tag differs. Use it for an app with about six or fewer sections, or a user who can open one or two. --}}
@php
$styles = $styles ?? [];
@endphp

<atom:layouts.header title="App shell (top nav)" :vite="[]" :styles="$styles" :dark="$dark ?? true">
    <x-slot:brand>
        <a href="?page=dashboard" class="flex shrink-0 items-center gap-2 text-sm font-semibold tracking-tight">
            <div class="flex size-[22px] items-center justify-center rounded-nav bg-accent text-accent-foreground">
                <atom:icon.blocks class="size-3.5"/>
            </div>
            <span class="max-sm:sr-only">Fieldwork</span>
        </a>
    </x-slot>

    <x-slot:context>
        <atom:button variant="ghost" icon-suffix="dropdown">Acme Studio</atom:button>
    </x-slot>

    <x-slot:search>
        <atom:input icon="search" placeholder="Search" aria-label="Search" class="w-44"/>
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
</atom:layouts.header>
