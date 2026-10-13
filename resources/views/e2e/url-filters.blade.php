{{-- Hosts tests/Fixtures/UrlFiltersFixture.php: a listing whose filters live in the URL and
     a record page below it. As in breadcrumbs-back, the trail only outlives a wire:navigate
     if the element holding it does, so it is @persist-ed, as a host that wants one must do. --}}
<atom:layouts.sidebar title="Listing" :vite="[]" :dark="false">
    @persist('breadcrumbs')
        <atom:breadcrumbs />
    @endpersist

    <livewire:atom-e2e-url-filters />

    @livewireScripts
</atom:layouts.sidebar>
