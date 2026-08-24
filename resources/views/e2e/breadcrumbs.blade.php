{{-- Hosts the Livewire fixture (tests/Fixtures/BreadcrumbsFixture.php) inside the
     real sidebar layout WITH a title, so the layout's sr-only <h1> sits in front of
     the Livewire root — the arrangement that broke trail resolution in v3.18.0. --}}
<atom:layouts.sidebar title="Dashboard" :vite="[]" :dark="false">
    <livewire:atom-e2e-breadcrumbs />

    @livewireScripts
</atom:layouts.sidebar>
