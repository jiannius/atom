<atom:html title="E2E: Calendar (two on a page)" :vite="[]" :dark="false" class="min-h-screen bg-white">
{{-- Two components with a calendar each, both bound to a property of the same name (`date`): what each builds its ids from is the component as well,
     so nothing on the page is an id twice. The rig ships no Tailwind, so what the calendar needs of it is stood in for by atom.css and the fixture's own CSS. --}}
<style>
    *, ::before, ::after { box-sizing: border-box }
    .flex { display: flex }
    .inline-flex { display: inline-flex }
    .items-center { align-items: center }
    .justify-between { justify-content: space-between }
    .flex-wrap { flex-wrap: wrap }
    .min-w-0 { min-width: 0 }
    .w-full { width: 100% }
    .h-5 { height: 1.25rem }
    .truncate { overflow: hidden; text-overflow: ellipsis; white-space: nowrap }
    [data-atom-icon] svg { width: 100%; height: 100% }
</style>
<livewire:atom-e2e-calendar />
<livewire:atom-e2e-calendar />

@livewireScripts
</atom:html>
