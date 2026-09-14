<atom:html title="E2E: Static Table" :vite="[]" :dark="false" class="min-h-screen bg-white">
{{-- A plain-Blade <atom:table> on a page that renders NO Livewire component —
     the shape every page under /atom/docs has. Livewire only auto-injects its
     stylesheet on a request that rendered a component, and that stylesheet is
     what normally hides a wire:loading element, so this is the one arrangement
     where the table's loading overlay has nothing holding it down. --}}

{{-- The rig serves no Tailwind; these are the classes the overlay carries,
     written out as Tailwind v4 compiles them. --}}
<style>
    .relative { position: relative; }
    .absolute { position: absolute; }
    .inset-0 { inset: 0px; }
    .z-10 { z-index: 10; }
    .justify-center { justify-content: center; }
    .overflow-hidden { overflow: hidden; }
    .overflow-x-auto { overflow-x: auto; }
    .rounded-lg { border-radius: 0.5rem; }
    .bg-white\/60 { background-color: color-mix(in oklab, #fff 60%, transparent); }
    .size-6 { width: 1.5rem; height: 1.5rem; }
    .min-w-full { min-width: 100%; }
    .py-3 { padding-block: 0.75rem; }
    .px-4 { padding-inline: 1rem; }
</style>

<div class="p-4">
    <atom:table>
        <x-slot:columns>
            <atom:table.column>Name</atom:table.column>
        </x-slot:columns>
        <x-slot:rows>
            <atom:table.row>
                <atom:table.cell data-name="Static">Static</atom:table.cell>
            </atom:table.row>
        </x-slot:rows>
    </atom:table>
</div>

@livewireScripts
</atom:html>
