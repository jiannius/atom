{{-- Pattern: app shell (sidebar). The same nav, brand and account slots as app-shell-header.blade.php; only the
     layout tag differs. Use it for an app with many modules, or a user who sees many of them. --}}
@php
$styles = $styles ?? [];
@endphp

<atom:layouts.sidebar title="App shell (sidebar)" :vite="[]" :styles="$styles" :dark="$dark ?? true">
    <x-slot:brand>
        <a href="?page=dashboard" class="flex items-center gap-[9px] px-2 pb-3 pt-1.5">
            <div class="flex size-[26px] items-center justify-center rounded-control bg-accent text-accent-foreground">
                <atom:icon.blocks class="size-4"/>
            </div>
            <span class="truncate text-sm font-semibold">Fieldwork</span>
        </a>
    </x-slot>

    <x-slot:context>
        <atom:button block size="sm" variant="ghost" icon-suffix="dropdown" class="justify-between!">Acme Studio</atom:button>
    </x-slot>

    <x-slot:search>
        <atom:input icon="search" placeholder="Search" aria-label="Search"/>
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

    <x-slot:actions>
        <atom:button size="sm" variant="primary" icon="add">New invoice</atom:button>
    </x-slot>

    <x-slot:dropdown>
        <atom:menu.item icon="settings">Account settings</atom:menu.item>
        <atom:menu.item icon="logout">Sign out</atom:menu.item>
    </x-slot>

    @include('atom::docs.patterns.page')

    {{-- no Livewire component on this page, so Livewire's own Alpine has to be loaded for atom.js --}}
    @livewireScripts
</atom:layouts.sidebar>
