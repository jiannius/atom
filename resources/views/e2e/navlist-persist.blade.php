<atom:html title="E2E: Navlist Persist" :vite="[]" :dark="false" class="min-h-screen bg-white">
{{-- Three groups: one persisted, one not, and one sharing the first's key, so the
     spec can prove the storage write happens, that it stays opt-in, and that two
     groups on one key sync (documented behaviour, not a guard). --}}
<div class="p-4 w-64">
    <atom:navlist>
        <atom:navlist.group heading="Purchase" expandable persist-key="nav.purchase" data-group="persisted">
            <atom:navlist.item href="#po" data-item="po">Purchase orders</atom:navlist.item>
        </atom:navlist.group>

        <atom:navlist.group heading="Sales" expandable data-group="plain">
            <atom:navlist.item href="#so" data-item="so">Sales orders</atom:navlist.item>
        </atom:navlist.group>

        <atom:navlist.group heading="Purchase (mirror)" expandable persist-key="nav.purchase" data-group="mirror">
            <atom:navlist.item href="#po2" data-item="po2">Purchase orders</atom:navlist.item>
        </atom:navlist.group>

        {{-- expanded=false + a key: the stored value must win over the prop --}}
        <atom:navlist.group heading="Reports" expandable :expanded="false" persist-key="nav.reports" data-group="collapsed-default">
            <atom:navlist.item href="#rep" data-item="rep">Reports</atom:navlist.item>
        </atom:navlist.group>
    </atom:navlist>
</div>

@livewireScripts
</atom:html>
