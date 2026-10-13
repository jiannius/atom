{{-- Hosts tests/Fixtures/BreadcrumbsBackFixture.php: a listing and a record page below it,
     for the breadcrumb back() query-string case. A trail only outlives a wire:navigate if
     the element holding it does (Livewire swaps the body, and Alpine starts again on the
     new one), so it is @persist-ed here, as a host that wants one trail must do. --}}
<atom:layouts.sidebar title="Listing" :vite="[]" :dark="false">
    @persist('breadcrumbs')
        <atom:breadcrumbs />
    @endpersist

    <livewire:atom-e2e-breadcrumbs-back />

    @livewireScripts
</atom:layouts.sidebar>
