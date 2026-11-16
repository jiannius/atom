{{-- hand-rail-grid. A line marked `expect:` is a finding; every other line must stay clean. --}}
<atom:page>
    <atom:page.header title="Work order" />

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]"> {{-- expect: hand-rail-grid --}}
        <div>Main</div>
        <aside>Rail</aside>
    </div>

    <div class="grid grid-cols-[1fr_320px] gap-4"> {{-- expect: hand-rail-grid --}}
        <div>Main</div>
    </div>

    <div class="grid gap-6 xl:grid-cols-[20rem_minmax(0,1fr)]"> {{-- expect: hand-rail-grid --}}
        <aside>Rail</aside>
    </div>

    <section :class="'grid grid-cols-[1fr_24rem]'"> {{-- expect: hand-rail-grid --}}
        <div>Main</div>
    </section>

    {{-- the look-alikes --}}
    <atom:card heading="Totals">
        <div class="grid lg:grid-cols-[minmax(0,1fr)_20rem]"><div>Inside a card</div></div>
    </atom:card>

    <atom:panel heading="Lines">
        <div class="grid grid-cols-[1fr_20rem]"><div>Inside a panel</div></div>
    </atom:panel>

    <atom:table>
        <x-slot:rows>
            <div class="grid grid-cols-[1fr_20rem]"><div>Inside a table</div></div>
        </x-slot:rows>
    </atom:table>

    <atom:form.section heading="Billing">
        <div class="grid grid-cols-[1fr_20rem]"><div>Inside a form section</div></div>
    </atom:form.section>

    <atom:dd.group>
        <div class="grid grid-cols-[1fr_20rem]"><div>Inside a description list</div></div>
    </atom:dd.group>

    <div class="grid gap-6 lg:grid-cols-3"><div>Three equal columns</div></div>

    <div class="grid grid-cols-[minmax(0,1fr)_120px]"><div>A column of figures</div></div>

    <div class="grid grid-cols-[170px_130px]"><div>Two fixed tracks</div></div>

    <div class="grid grid-cols-[1fr_auto]"><div>A row with a button</div></div>

    <div class="grid grid-cols-[1fr_2fr]"><div>Two flexible tracks</div></div>

    <div class="grid grid-cols-[1fr_20rem_2rem]"><div>Three tracks</div></div>

    <div class="grid grid-cols-[minmax(0,1fr)_{{ $width }}]"><div>An echoed track</div></div>
</atom:page>

<div class="grid grid-cols-[1fr_20rem]"><div>Outside a page</div></div>
