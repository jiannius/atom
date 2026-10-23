@php
// ?tw=floor|latest opts in to the consumer build, ?shell=header|sidebar puts the fixture under an app shell, ?dark=1
$tw = in_array(request()->query('tw'), ['floor', 'latest'], true) ? request()->query('tw') : 'latest';
$shell = request()->query('shell') === 'header' ? 'header' : 'sidebar';
// ?accent=%23rrggbb sets --color-accent the way a host does in its @theme; with none, the default accent
$accent = preg_match('/^#[0-9a-f]{6}$/i', (string) request()->query('accent')) ? request()->query('accent') : null;
@endphp

<x-dynamic-component :component="'atom::layouts.'.$shell" title="E2E: timeline" :vite="[]" :styles="['/atom/e2e/consumer-css/'.$tw]" :dark="request()->query('dark') === '1'">
    @if ($accent)
        <style>:root { --color-accent: {{ $accent }}; }</style>
    @endif

    <x-slot:nav>
        <atom:navlist>
            <atom:navlist.item href="?page=dashboard" icon="dashboard">Dashboard</atom:navlist.item>
        </atom:navlist>
    </x-slot>

    <atom:page>
        <livewire:atom-e2e-timeline />
    </atom:page>

    @livewireScripts
</x-dynamic-component>
