<div class="max-w-xs">
    <atom:navlist>
        {{-- collapse this one, reload the page, and it is still collapsed --}}
        <atom:navlist.group expandable heading="Purchase" persist-key="docs.purchase">
            <atom:navlist.item href="#">Purchase orders</atom:navlist.item>
            <atom:navlist.item href="#">Suppliers</atom:navlist.item>
        </atom:navlist.group>

        {{-- no key: collapse it, reload, and it is open again --}}
        <atom:navlist.group expandable heading="Sales">
            <atom:navlist.item href="#">Sales orders</atom:navlist.item>
            <atom:navlist.item href="#">Customers</atom:navlist.item>
        </atom:navlist.group>

        {{-- expanded=false is only the starting state: once you expand it and
             reload, the stored value wins and it stays open --}}
        <atom:navlist.group expandable heading="Analytics" :expanded="false" persist-key="docs.analytics">
            <atom:navlist.item href="#">Revenue</atom:navlist.item>
            <atom:navlist.item href="#">Retention</atom:navlist.item>
        </atom:navlist.group>
    </atom:navlist>
</div>
