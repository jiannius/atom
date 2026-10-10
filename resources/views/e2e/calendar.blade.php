<atom:html title="E2E: Calendar" :vite="[]" :dark="false" class="min-h-screen bg-white">
{{-- Hosts the Livewire fixture (tests/Fixtures/CalendarFixture.php) so the E2E can drive a bound calendar:
     navigation, requests and Livewire re-renders around its wire:ignore'd body. --}}
{{-- The rig ships no Tailwind. These are Tailwind's own definitions of the utilities the calendar's markup
     leans on, so a chip has a size and a click lands where it would in an app. They name no calendar element:
     the markup's class list decides, and what atom.css owns (the grid) stays atom.css's. --}}
<style>
    *, ::before, ::after { box-sizing: border-box }
    .flex { display: flex }
    .inline-flex { display: inline-flex }
    .items-center { align-items: center }
    .items-start { align-items: flex-start }
    .justify-between { justify-content: space-between }
    .justify-center { justify-content: center }
    .flex-wrap { flex-wrap: wrap }
    .shrink-0 { flex-shrink: 0 }
    .min-w-0 { min-width: 0 }
    .w-full { width: 100% }
    .h-full { height: 100% }
    .flex-col { flex-direction: column }
    .overflow-hidden { overflow: hidden }
    .w-64 { width: 16rem }
    .h-5 { height: 1.25rem }
    .h-6 { height: 1.5rem }
    .size-1\.5 { width: .375rem; height: .375rem }
    .size-2 { width: .5rem; height: .5rem }
    .size-3 { width: .75rem; height: .75rem }
    .size-4 { width: 1rem; height: 1rem }
    .size-5 { width: 1.25rem; height: 1.25rem }
    .size-6 { width: 1.5rem; height: 1.5rem }
    .size-8 { width: 2rem; height: 2rem }
    .size-9 { width: 2.25rem; height: 2.25rem }
    .truncate { overflow: hidden; text-overflow: ellipsis; white-space: nowrap }
    .gap-1 { gap: .25rem }
    .gap-1\.5 { gap: .375rem }
    .gap-3 { gap: .75rem }
    .p-3 { padding: .75rem }
    .p-4 { padding: 1rem }
    .px-1 { padding-inline: .25rem }
    .px-1\.5 { padding-inline: .375rem }
    .text-xs { font-size: .75rem }
    .text-left { text-align: left }
    .rounded-full { border-radius: 9999px }
    .cursor-pointer { cursor: pointer }
    .relative { position: relative }
    .space-y-4 > * + * { margin-top: 1rem }
    [data-atom-icon] svg { width: 100%; height: 100% }
</style>
<div class="p-4">
    <livewire:atom-e2e-calendar />
</div>

@livewireScripts
</atom:html>
