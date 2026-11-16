{{-- raw-control: a button or link styled by hand. A line marked `expect:` is a finding; every other line must stay clean. --}}
<atom:page>
    <button class="rounded-md border px-3 py-1">Save</button> {{-- expect: raw-control --}}

    <a href="#" class="rounded-lg bg-accent px-4 py-2 text-accent-foreground">Open</a> {{-- expect: raw-control --}}

    <button type="button" class="inline-flex h-8 items-center rounded border-2 bg-surface" x-on:click="go()">Go</button> {{-- expect: raw-control --}}

    <a href="#" :class="['rounded-md bg-surface-sunken px-2 py-1' => $on]">Bound</a> {{-- expect: raw-control --}}

    {{-- expect: raw-control --}}
    <button
        type="button"
        class="rounded-full border p-2"
    >
        <atom:icon.plus />
    </button>

    {{-- look-alikes: none of these is flagged --}}
    <a href="#" class="text-accent-ink underline">A text link</a>
    <a href="#" class="px-2 py-1 hover:bg-surface-sunken">A nav item has no resting fill or rounding</a>
    <button class="rounded-md px-2 py-1">No border or fill</button>
    <button class="rounded-md border">No padding or height</button>
    <button class="rounded-md border p-0">Padding zero</button>
    <button class="rounded-md border border-border bg-transparent">Transparent, no padding</button>
    <button class="rounded-md bg-transparent px-2">Transparent is no fill</button>
    <button class="bg-surface px-2 py-1 hover:rounded-md">The rounding is a state</button>
    <button class="rounded-md border h-full">A full height is not a control height</button>
    <button class="rounded-md border px-2 py-1 {{ $extra }}">An echo among the classes hides only itself</button> {{-- expect: raw-control --}}
    <a href="#" class="block rounded-panel border bg-surface p-4"><div>A tile holds blocks</div></a>
    <button class="rounded-md border px-2 py-1" role="switch" aria-checked="true">A switch</button>
    <button class="rounded-md border px-2 py-1" role="tab">A tab</button>
    <atom:button variant="ghost">Atom's own</atom:button>
    <x-fancy-button class="rounded-md border px-2 py-1">A host component</x-fancy-button>
    <div class="rounded-md border px-2 py-1">Not a control</div>

    <atom:layouts.sidebar>
        <x-slot:brand>
            <a href="/" class="rounded-md border px-2 py-1">The host's own look</a>
        </x-slot:brand>
    </atom:layouts.sidebar>
</atom:page>
