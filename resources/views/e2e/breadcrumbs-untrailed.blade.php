{{-- A titled page that renders <atom:breadcrumbs> over a component declaring no
     breadcrumbs() method — the empty-payload state. It must render nothing rather
     than throw out of the navigate handler. --}}
<atom:layouts.sidebar title="Dashboard" :vite="[]" :dark="false">
    <livewire:atom-e2e-breadcrumbs-untrailed />

    @livewireScripts
</atom:layouts.sidebar>
