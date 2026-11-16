{{-- hand-panel: a box drawn by hand. A line marked `expect:` is a finding; every other line must stay clean. --}}
<atom:page>
    <div class="rounded-panel border bg-surface p-4"> {{-- expect: hand-panel --}}
        <p>Facts</p>
    </div>

    <div class="overflow-hidden rounded-lg border bg-surface"> {{-- expect: hand-panel --}}
        <div class="border-b px-4 py-3">Lines</div>
        <div>Body</div>
    </div>

    <section class="rounded-md border-2 bg-white"> {{-- expect: hand-panel --}}
        Settings
    </section>

    <ul class="divide-y rounded-xl border bg-surface-sunken"> {{-- expect: hand-panel --}}
        <li>One</li>
    </ul>

    <div :class="['rounded-2xl border bg-surface' => $open]"> {{-- expect: hand-panel --}}
        Bound
    </div>

    {{-- expect: hand-panel --}}
    <aside
        id="rail"
        class="rounded-panel border bg-surface"
    >
        A tag over several lines is read at its first line
    </aside>

    <article class="rounded-md border bg-surface"><p>x</p></article> {{-- expect: hand-panel --}}
    <aside class="rounded-md border bg-surface"><p>x</p></aside> {{-- expect: hand-panel --}}
    <ol class="rounded-md border bg-surface"><p>x</p></ol> {{-- expect: hand-panel --}}
    <dl class="rounded-md border bg-surface"><p>x</p></dl> {{-- expect: hand-panel --}}
    <figure class="rounded-md border bg-surface"><p>x</p></figure> {{-- expect: hand-panel --}}
    <fieldset class="rounded-md border bg-surface"><p>x</p></fieldset> {{-- expect: hand-panel --}}
    <details class="rounded-md border bg-surface"><p>x</p></details> {{-- expect: hand-panel --}}
    <form class="rounded-md border bg-surface"><p>x</p></form> {{-- expect: hand-panel --}}
    <nav class="rounded-md border bg-surface"><p>x</p></nav> {{-- expect: hand-panel --}}
    <header class="rounded-md border bg-surface"><p>x</p></header> {{-- expect: hand-panel --}}
    <div class="rounded-md border bg-surface/50 p-4">Half</div> {{-- expect: hand-panel --}}
    <div class="rounded-md border bg-white/80 p-4">White</div> {{-- expect: hand-panel --}}
    <div x-show="open" class="rounded-md border bg-surface p-4">A collapsible box in the flow</div> {{-- expect: hand-panel --}}

    {{-- look-alikes: none of these is flagged --}}
    <atom:panel heading="Lines">Body</atom:panel>
    <atom:card heading="Customer">Body</atom:card>
    <a href="#" class="rounded-md border bg-surface px-3 py-1">A link is raw-control, not a panel</a>
    <button class="rounded-md border bg-surface px-3 py-1">A button is raw-control</button>
    <div class="rounded-full border bg-surface px-2">A pill</div>
    <div class="rounded-panel border-b bg-surface">Only a rule under it</div>
    <div class="rounded-md border hover:bg-surface">The fill is a state</div>
    <div class="rounded-md border bg-surface-raised">The raised layer is not a box</div>
    <div class="rounded-md border {{ $fill }}">A fill from an echo is not read</div>
    <div class="rounded-control border bg-surface px-2">A control radius</div>
    <div class="rounded-md border bg-surface px-2">
        <input type="text" class="w-full" />
    </div>
    <div class="rounded-md border bg-surface px-2">
        <atom:input wire:model="search" />
    </div>
    <div popover class="rounded-md border bg-surface">A popover</div>
    <div role="dialog" class="rounded-md border bg-surface">A dialog</div>
    <div class="animate-pulse rounded-md border bg-surface-sunken">A placeholder</div>
    <div x-show="open" x-transition class="absolute right-0 z-10 mt-2 rounded-md border bg-surface p-1">An Alpine dropdown</div>
    <div x-anchor="$refs.button" class="fixed rounded-md border bg-surface p-1">An anchored panel</div>
    <div x-show="open" x-transition:enter="a" class="fixed rounded-md border bg-surface p-1">A transition</div>
    <div class="rounded-md border border-dashed bg-surface p-6">A dropzone</div>
    <div class="rounded-md border border-dotted bg-surface p-6">A placeholder</div>
    <div class="inline-flex rounded-md border bg-surface">A segmented control</div>
    <div class="inline-block rounded-md border bg-surface">Inline</div>
    <div class="inline rounded-md border bg-surface">Inline</div>
    <div class="w-fit rounded-md border bg-surface">Fit</div>
    <div class="rounded-md border bg-surface p-2"><label><input type="checkbox"> A labelled checkbox</label></div>
    <div class="rounded-md border bg-surface p-2"><div><atom:select wire:model="x" /></div></div>
    <x-box class="rounded-md border bg-surface">A host component</x-box>
    <span class="rounded-md border bg-surface px-1">A badge</span>

    <atom:layouts.sidebar>
        <x-slot:brand>
            <div class="rounded-md border bg-surface">The host's own look</div>
        </x-slot:brand>
    </atom:layouts.sidebar>
</atom:page>
