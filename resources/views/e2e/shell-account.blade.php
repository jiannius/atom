@php
$layout = ($shell ?? null) === 'header' ? 'atom::layouts.header' : 'atom::layouts.sidebar';
@endphp

{{-- The account menu in atom's own brand row (no `brand` slot), in either shell: ?shell=header. --}}
<x-dynamic-component :component="$layout" title="E2E: shell account" :vite="[]" :styles="$styles" :dark="$dark">
    <x-slot:nav>
        <atom:navlist>
            <atom:navlist.item href="?page=dashboard" icon="dashboard">Dashboard</atom:navlist.item>
        </atom:navlist>
    </x-slot>

    <x-slot:dropdown>
        <atom:menu.item icon="settings" href="?page=settings">Settings</atom:menu.item>
        <atom:menu.item icon="logout" href="?page=logout">Log out</atom:menu.item>
    </x-slot>

    <div class="p-6">Account</div>

    {{-- no Livewire component on this page, so Livewire's own Alpine has to be loaded for atom.js --}}
    @livewireScripts
</x-dynamic-component>
