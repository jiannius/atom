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
    <x-box class="rounded-md border bg-surface">A host component</x-box>
    <span class="rounded-md border bg-surface px-1">A badge</span>

    <atom:layouts.sidebar>
        <x-slot:brand>
            <div class="rounded-md border bg-surface">The host's own look</div>
        </x-slot:brand>
    </atom:layouts.sidebar>
</atom:page>
