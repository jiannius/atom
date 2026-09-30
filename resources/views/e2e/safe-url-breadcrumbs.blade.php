{{-- Hosts tests/Fixtures/SafeUrlBreadcrumbsFixture.php: a real breadcrumbs trail with
     a hostile URL in it. Same sidebar arrangement as e2e/breadcrumbs.blade.php. --}}
<atom:layouts.sidebar title="Dashboard" :vite="[]" :dark="false">
    <livewire:atom-e2e-safe-url-breadcrumbs />

    @livewireScripts
</atom:layouts.sidebar>
