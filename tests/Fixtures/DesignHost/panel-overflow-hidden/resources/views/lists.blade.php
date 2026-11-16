{{-- panel-overflow-hidden: a box that hides or scrolls its overflow around a paginated table. A line marked `expect:` is a finding; every other line must stay clean. --}}
<atom:page>
    <atom:page.header title="Orders" />

    <div class="rounded-panel border overflow-hidden"> {{-- expect: panel-overflow-hidden --}}
        <atom:table frameless :paginate="$orders">
            <x-slot:columns><atom:table.column>Order</atom:table.column></x-slot:columns>
        </atom:table>
    </div>

    <atom:card> {{-- expect: panel-overflow-hidden --}}
        <atom:table :paginate="$orders">
            <x-slot:columns><atom:table.column>Order</atom:table.column></x-slot:columns>
        </atom:table>
    </atom:card>

    <section class="rounded-panel border overflow-x-auto"> {{-- expect: panel-overflow-hidden --}}
        <atom:panel>
            <atom:table :paginate="$orders" :sticky-pager="true">
                <x-slot:columns><atom:table.column>Order</atom:table.column></x-slot:columns>
            </atom:table>
        </atom:panel>
    </section>

    <div class="max-h-96 overflow-y-auto"> {{-- expect: panel-overflow-hidden --}}
        <atom:table :paginate="$orders" />
        <atom:table :paginate="$returns" />
    </div>

    <div class="overflow-auto"> {{-- expect: panel-overflow-hidden --}}
        <atom:table :paginate="$orders" />
    </div>

    <div class="overflow-scroll"> {{-- expect: panel-overflow-hidden --}}
        <atom:table paginate />
    </div>

    {{-- Look-alikes: none of these is flagged. --}}
    <div class="rounded-panel border overflow-hidden">
        <atom:table frameless>
            <x-slot:columns><atom:table.column>No paginator, no pager to pin</atom:table.column></x-slot:columns>
        </atom:table>
    </div>

    <atom:card>
        <atom:table :paginate="$orders" :sticky-pager="false" />
    </atom:card>

    <atom:card>
        <atom:table :paginate="$orders" :sticky-pager="$pin" />
    </atom:card>

    <atom:panel>
        <atom:table frameless :paginate="$orders" />
    </atom:panel>

    <div class="overflow-hidden">
        <atom:modal name="orders">
            <atom:table :paginate="$orders" />
        </atom:modal>
    </div>

    <div class="overflow-hidden md:overflow-visible">
        <atom:table :paginate="$orders" />
    </div>

    <atom:card class="overflow-visible">
        <atom:table :paginate="$orders" />
    </atom:card>

    <div :class="{ 'overflow-hidden': collapsed }">
        <atom:table :paginate="$orders" />
    </div>

    <div class="{{ $box }} overflow-hidden-not-a-class">
        <atom:table :paginate="$orders" />
    </div>

    <atom:card>
        <p>A card with no table in it.</p>
    </atom:card>
</atom:page>
