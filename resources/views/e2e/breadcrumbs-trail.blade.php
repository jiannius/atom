{{-- Hosts tests/Fixtures/BreadcrumbsTrailFixture.php: the v4 sidebar shell with the trail persisted in the `crumbs` slot, on the
     consumer Tailwind (?tw=floor|latest, latest by default). /dashboard has one crumb, /two two, /customers five, /northwind six, /invoice eight,
     /long five with a very long last title; the links on the page are wire:navigate, so a trail grows and resets as it does for a host. --}}
@php
$tw = in_array(request()->query('tw'), ['floor', 'latest'], true) ? request()->query('tw') : 'latest';
@endphp

<atom:layouts.sidebar title="Trail" :vite="[]" :dark="false" :styles="['/atom/e2e/consumer-css/'.$tw]">
    <x-slot:crumbs>
        @persist('breadcrumbs')
            <atom:breadcrumbs />
        @endpersist
    </x-slot:crumbs>

    <livewire:atom-e2e-breadcrumbs-trail />

    @livewireScripts
</atom:layouts.sidebar>
