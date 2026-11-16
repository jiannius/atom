{{-- a grid inside any of these boxes is the box's own: none is flagged --}}
<atom:page>
    <atom:modal name="a"><div class="grid grid-cols-[1fr_20rem]"><div>x</div></div></atom:modal>
    <atom:modal.trigger><div class="grid grid-cols-[1fr_20rem]"><div>x</div></div></atom:modal.trigger>
    <atom:dd label="a"><div class="grid grid-cols-[1fr_20rem]"><div>x</div></div></atom:dd>
    <atom:accordion><div class="grid grid-cols-[1fr_20rem]"><div>x</div></div></atom:accordion>
    <atom:table.toolbar><div class="grid grid-cols-[1fr_20rem]"><div>x</div></div></atom:table.toolbar>
    <dl><div class="grid grid-cols-[1fr_20rem]"><div>x</div></div></dl>
    <div><dd><div class="grid grid-cols-[1fr_20rem]"><div>x</div></div></dd></div>
    <atom:dd.group><div class="grid grid-cols-[1fr_20rem]"><div>x</div></div></atom:dd.group>
    <atom:form.section heading="a"><div class="grid grid-cols-[1fr_20rem]"><div>x</div></div></atom:form.section>
    <table><tr><td><div class="grid grid-cols-[1fr_20rem]"><div>x</div></div></td></tr></table>
    <dialog><div class="grid grid-cols-[1fr_20rem]"><div>x</div></div></dialog>
    <atom:card class="grid grid-cols-[1fr_20rem]"><div>x</div></atom:card>
    <atom:panel class="grid grid-cols-[1fr_20rem]"><div>x</div></atom:panel>
    <table class="grid grid-cols-[1fr_20rem]"><tr><td>x</td></tr></table>

    {{-- a key and its value, a grid with nothing in it, a grid sent to its children --}}
    <div class="grid grid-cols-[200px_1fr]"><span>Label</span><span>Value</span></div>
    <div class="grid grid-cols-[200px_1fr]"><dt>Label</dt><dd>Value</dd></div>
    <div class="grid grid-cols-[1fr_20rem]"></div>
    <div class="grid grid-cols-[1fr_20rem]">{{ $slot }}</div>
    <div class="grid *:grid-cols-[1fr_20rem]"><div>x</div></div>
    <div class="grid [&>div]:grid-cols-[1fr_20rem]"><div>x</div></div>

    {{-- what the grid holds decides: a block of any kind is a rail grid --}}
    <div class="grid grid-cols-[1fr_20rem]"><x-jobs.rail /></div> {{-- expect: hand-rail-grid --}}
    <div class="grid grid-cols-[1fr_20rem]"><livewire:jobs.rail /></div> {{-- expect: hand-rail-grid --}}
    <div class="grid grid-cols-[1fr_20rem]"><atom:callout>x</atom:callout></div> {{-- expect: hand-rail-grid --}}
    <div class="grid grid-cols-[1fr_20rem]"><span>a</span><ul><li>b</li></ul></div> {{-- expect: hand-rail-grid --}}
    <div class="grid grid-cols-[1fr_20rem]"><article>a</article></div> {{-- expect: hand-rail-grid --}}
</atom:page>
