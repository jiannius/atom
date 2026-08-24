{{-- Same fixture as e2e/breadcrumbs.blade.php, but with the Livewire component
     inside a plain wrapper div — the layout consuming apps actually write. The
     Livewire root is then neither the first child of [data-atom-main] nor a direct
     child of it at all. --}}
<atom:layouts.sidebar title="Dashboard" :vite="[]" :dark="false">
    <div class="p-4">
        <livewire:atom-e2e-breadcrumbs />
    </div>

    @livewireScripts
</atom:layouts.sidebar>
