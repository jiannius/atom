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
        <button type="button" class="flex h-control w-full items-center gap-2 rounded-control px-2 text-sm font-medium hover:bg-fg/5">
            <span class="flex size-[18px] items-center justify-center rounded-full bg-info-bg text-[10px] font-semibold text-info-fg">A</span>
            <span class="truncate">Acme Studio</span>
            <atom:icon.dropdown class="ms-auto size-3.5 text-fg-subtle"/>
        </button>
    </x-slot>

    <x-slot:search>
        <div class="flex h-control w-full items-center gap-2 rounded-control border border-border-strong bg-surface px-2 text-fg-subtle">
            <atom:icon.search class="size-4"/>
            <span>Search</span>
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
        <div class="flex items-center gap-1.5 text-fg-subtle">
            <span>Sales</span>
            <atom:icon.right class="size-3.5"/>
            <span class="font-medium text-fg">{{ str(request()->query('page', 'invoices'))->headline() }}</span>
        </div>
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
