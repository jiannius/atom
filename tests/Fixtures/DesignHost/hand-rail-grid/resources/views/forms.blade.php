{{-- a form around the page is not a box of the page's layout, a form that is the grid is one --}}
<form wire:submit="save">
    <atom:page>
        <atom:page.header title="Work order" />

        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]"> {{-- expect: hand-rail-grid --}}
            <div>Main</div>
        </div>
    </atom:page>
</form>

<atom:form wire:submit="save">
    <atom:page>
        <section class="grid grid-cols-[1fr_20rem]"> {{-- expect: hand-rail-grid --}}
            <div>Main</div>
        </section>
    </atom:page>
</atom:form>

<atom:page>
    <form class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]"> {{-- expect: hand-rail-grid --}}
        <div>Main</div>
        <aside>Rail</aside>
    </form>

    <atom:form class="grid grid-cols-[1fr_20rem]"> {{-- expect: hand-rail-grid --}}
        <div>Main</div>
    </atom:form>

    <form>
        <div class="grid grid-cols-[1fr_20rem]"><div>A field and its unit</div></div>
    </form>

    <atom:form>
        <div class="grid grid-cols-[1fr_20rem]"><div>A field and its unit</div></div>
    </atom:form>
</atom:page>
