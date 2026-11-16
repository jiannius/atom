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
        <div class="grid lg:grid-cols-[minmax(0,1fr)_20rem]">Inside a card</div>
    </atom:card>

    <atom:panel heading="Lines">
        <atom:table>
            <x-slot:rows>
                <div class="grid grid-cols-[1fr_20rem]">Inside a table</div>
            </x-slot:rows>
        </atom:table>
    </atom:panel>

    <atom:form.section heading="Billing">
        <div class="grid grid-cols-[1fr_20rem]">Inside a form section</div>
    </atom:form.section>

    <atom:dd.group>
        <div class="grid grid-cols-[1fr_20rem]">Inside a description list</div>
    </atom:dd.group>

    <div class="grid gap-6 lg:grid-cols-3">Three equal columns</div>

    <div class="grid grid-cols-[minmax(0,1fr)_120px]">A column of figures</div>

    <div class="grid grid-cols-[170px_130px]">Two fixed tracks</div>

    <div class="grid grid-cols-[1fr_auto]">A row with a button</div>

    <div class="grid grid-cols-[1fr_2fr]">Two flexible tracks</div>

    <div class="grid grid-cols-[1fr_20rem_2rem]">Three tracks</div>

    <div class="grid grid-cols-[minmax(0,1fr)_{{ $width }}]">An echoed track</div>
</atom:page>

<div class="grid grid-cols-[1fr_20rem]">Outside a page</div>
