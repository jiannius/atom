{{-- On the consumer Tailwind (`node tests/consumer/build.mjs`), so the shell lays out as a host's does. The v4 shell: the trail is @persist-ed in the `crumbs` slot, and a page may or may not print its own page.header.
     /old and /plain have none, /new and /deep have one; /old and /new have one crumb, /plain and /deep two; the links between them are wire:navigate.
     ?shell=header draws the top-nav shell (its crumbs are a row of their own under the bar), ?actions=1 gives the bar an `actions` slot,
     ?persist=0 puts <atom:breadcrumbs> in the slot without @persist, ?heading=0 passes heading="false", ?extra=1 adds an element beside the trail in the slot, ?wrap=1 wraps the trail in a div, ?text=1 adds a text node beside it. --}}
@php
$shell = request()->query('shell') === 'header' ? 'header' : 'sidebar';
@endphp

<x-dynamic-component :component="'atom::layouts.'.$shell" title="Crumbs" :vite="[]" :dark="false" :styles="['/atom/e2e/consumer-css/latest']">
    <x-slot:crumbs>
        @if (request()->query('wrap'))
            <div data-testid="crumbs-wrap" class="min-w-0 flex-1">
                <atom:breadcrumbs />
            </div>
        @elseif (request()->query('persist') === '0')
            <atom:breadcrumbs :heading="request()->query('heading') !== '0'" />
        @else
            @persist('breadcrumbs')
                <atom:breadcrumbs :heading="request()->query('heading') !== '0'" />
            @endpersist
        @endif

        @if (request()->query('text'))
            Workspace A
        @endif

        @if (request()->query('extra'))
            <span data-testid="crumbs-extra">Extra</span>
        @endif
    </x-slot:crumbs>

    @if (request()->query('actions'))
        <x-slot:actions>
            <atom:button size="sm" data-testid="bar-action">Action</atom:button>
        </x-slot:actions>
    @endif

    <livewire:atom-e2e-breadcrumbs-header />

    @livewireScripts
</x-dynamic-component>
