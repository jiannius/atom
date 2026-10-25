{{-- Hosts tests/Fixtures/BreadcrumbsTrailFixture.php: a shell with the trail persisted in the `crumbs` slot, on the consumer
     Tailwind (?tw=floor|latest, latest by default). The sidebar shell by default, the top-nav shell with ?shell=header (its crumbs
     are a row of their own under the bar, not part of it). /dashboard has one crumb, /two two, /customers five, /northwind six,
     /invoice eight, /long five with a very long last title, /reports one crumb, /hostile and /hostile-parent have a javascript: URL
     in the trail; the links on the page are wire:navigate, so a trail grows and resets as it does for a host. --}}
@php
$tw = in_array(request()->query('tw'), ['floor', 'latest'], true) ? request()->query('tw') : 'latest';
$shell = request()->query('shell') === 'header' ? 'header' : 'sidebar';
@endphp

<x-dynamic-component :component="'atom::layouts.'.$shell" title="Trail" :vite="[]" :dark="false" :styles="['/atom/e2e/consumer-css/'.$tw]">
    <x-slot:crumbs>
        @persist('breadcrumbs')
            <atom:breadcrumbs />
        @endpersist
    </x-slot:crumbs>

    <livewire:atom-e2e-breadcrumbs-trail />

    @livewireScripts
</x-dynamic-component>
