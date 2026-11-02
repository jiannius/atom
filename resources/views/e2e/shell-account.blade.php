@php
$layout = ($shell ?? null) === 'header' ? 'atom::layouts.header' : 'atom::layouts.sidebar';
@endphp

{{-- The account menu in atom's own brand row (no `brand` slot), in either shell: ?shell=header, ?account=end, ?caption=1 (the `logo` and `caption` slots). --}}
<x-dynamic-component :component="$layout" title="E2E: shell account" :vite="[]" :styles="$styles" :dark="$dark" :account="request()->query('account') === 'end' ? 'end' : 'brand'" :theme-switcher="request()->query('own') !== '1'">
    <x-slot:nav>
        <atom:navlist>
            <atom:navlist.item href="?page=dashboard" icon="dashboard">Dashboard</atom:navlist.item>
        </atom:navlist>
    </x-slot>

    @if (request()->query('caption') === '1')
        <x-slot:logo>
            <span class="text-sm font-semibold" data-logo-slot>T</span>
        </x-slot>

        <x-slot:caption>
            <span class="flex min-w-0 gap-1" data-caption-slot>
                <span class="min-w-0 truncate">Ada Lovelace</span>
                <span class="shrink-0">· HQ</span>
            </span>
        </x-slot>
    @endif

    <x-slot:dropdown>
        <atom:menu.item icon="settings" href="?page=settings">Settings</atom:menu.item>
        <atom:menu.item icon="logout" href="?page=logout">Log out</atom:menu.item>
    </x-slot>

    <div class="p-6">Account</div>

    {{-- ?own=1: a host's own theme switcher (atom draws none), the README's example --}}
    @if (request()->query('own') === '1')
        <div class="p-6" x-data="{ mode: window.darkmode() }" x-on:darkmode-changed.document="mode = $event.detail" data-own-switcher>
            <button type="button" x-on:click="window.darkmode('dark')" x-bind:aria-pressed="mode === 'dark'">Own dark</button>
            <button type="button" x-on:click="window.darkmode('system')" x-bind:aria-pressed="mode === 'system'">Own system</button>
        </div>
    @endif

    {{-- no Livewire component on this page, so Livewire's own Alpine has to be loaded for atom.js --}}
    @livewireScripts
</x-dynamic-component>
