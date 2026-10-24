{{-- On the consumer Tailwind (`node tests/consumer/build.mjs`), so the shell lays out as a host's does. The v4 shell: the trail is @persist-ed in the `crumbs` slot, and a page may or may not print its own page.header.
     /old and /plain have none, /new and /deep have one; /old and /new have one crumb, /plain and /deep two; the links between them are wire:navigate. --}}
<atom:layouts.sidebar title="Crumbs" :vite="[]" :dark="false" :styles="['/atom/e2e/consumer-css/latest']">
    <x-slot:crumbs>
        @persist('breadcrumbs')
            <atom:breadcrumbs />
        @endpersist
    </x-slot:crumbs>

    <livewire:atom-e2e-breadcrumbs-header />

    @livewireScripts
</atom:layouts.sidebar>
