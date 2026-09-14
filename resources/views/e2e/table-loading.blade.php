<atom:html title="E2E: Table Loading" :vite="[]" :dark="false" class="min-h-screen bg-white">
{{-- Hosts tests/Fixtures/TableLoadingFixture.php so the E2E can watch the loading
     overlay during a real Livewire round-trip on a table that runs past the fold. --}}

{{-- The rig serves no Tailwind, so every utility the overlay's geometry rests on
     would be inert here and a broken overlay would pass. These are the classes
     the component actually carries, written out as Tailwind v4 compiles them, so
     the test measures what a consuming app renders rather than an empty box. --}}
<style>
    .relative { position: relative; }
    .absolute { position: absolute; }
    .sticky { position: sticky; }
    .inset-0 { inset: 0px; }
    .top-0 { top: 0px; }
    .z-10 { z-index: 10; }
    .h-dvh { height: 100dvh; }
    .max-h-full { max-height: 100%; }
    .flex { display: flex; }
    .items-center { align-items: center; }
    .justify-center { justify-content: center; }
    .overflow-hidden { overflow: hidden; }
    .overflow-x-auto { overflow-x: auto; }
    .rounded-lg { border-radius: 0.5rem; }
    .bg-white\/60 { background-color: color-mix(in oklab, #fff 60%, transparent); }
    .size-6 { width: 1.5rem; height: 1.5rem; }
    .min-w-full { min-width: 100%; }
    .table-fixed { table-layout: fixed; }
    .py-3 { padding-block: 0.75rem; }
    .px-4 { padding-inline: 1rem; }
    .py-2 { padding-block: 0.5rem; }
    .whitespace-nowrap { white-space: nowrap; }
    .space-y-4 > * + * { margin-top: 1rem; }
    .shrink-0 { flex-shrink: 0; }
    .grow { flex-grow: 1; }
    .ml-auto { margin-left: auto; }
    .gap-3 { gap: 0.75rem; }
    .flex-wrap { flex-wrap: wrap; }
    .min-h-10 { min-height: 2.5rem; }
    .justify-between { justify-content: space-between; }
</style>

<div class="p-4">
    <livewire:atom-e2e-table-loading />
</div>

@livewireScripts
</atom:html>
