@php
// ?tw=floor|latest opts in to the consumer build, ?shell=header|sidebar puts the fixture under an app shell, ?dark=1
$tw = in_array(request()->query('tw'), ['floor', 'latest'], true) ? request()->query('tw') : 'latest';
$shell = request()->query('shell') === 'header' ? 'header' : 'sidebar';
@endphp

<x-dynamic-component :component="'atom::layouts.'.$shell" title="E2E: slide-over-form" :vite="[]" :styles="['/atom/e2e/consumer-css/'.$tw]" :dark="request()->query('dark') === '1'">
    <x-slot:nav>
        <atom:navlist>
            <atom:navlist.item href="?page=dashboard" icon="dashboard">Dashboard</atom:navlist.item>
        </atom:navlist>
    </x-slot>

    <atom:page>
        <livewire:atom-e2e-slide-over-form />
    </atom:page>

    @livewireScripts
</x-dynamic-component>
