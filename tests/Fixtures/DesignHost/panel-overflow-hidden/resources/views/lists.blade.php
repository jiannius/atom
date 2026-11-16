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

    <div class="rounded-panel border overflow-y-auto hover:bg-surface-sunken"> {{-- expect: panel-overflow-hidden --}}
        <atom:table :paginate="$orders" />
        <atom:table :paginate="$returns" />
    </div>

    <div class="overflow-x-hidden"> {{-- expect: panel-overflow-hidden --}}
        <atom:table :paginate="$orders" />
    </div>

    <div class="overflow-x-scroll"> {{-- expect: panel-overflow-hidden --}}
        <atom:table :paginate="$orders" />
    </div>

    <div class="overflow-y-scroll"> {{-- expect: panel-overflow-hidden --}}
        <atom:table :paginate="$orders" />
    </div>

    <div class="overflow-y-hidden h-auto"> {{-- expect: panel-overflow-hidden --}}
        <atom:table :paginate="$orders" />
    </div>

    <atom:card> {{-- expect: panel-overflow-hidden --}}
        <atom:table :paginate="$orders" sticky-pager="false" />
    </atom:card>

    <atom:card> {{-- expect: panel-overflow-hidden --}}
        <atom:table :paginate="$orders" x-data="{}" :stickyPager="true" />
    </atom:card>

    <atom:card> {{-- expect: panel-overflow-hidden --}}
        <atom:table :paginate="$pages ? $rows : null" />
    </atom:card>

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
        <atom:table :paginate="$orders" :stickyPager="false" />
    </atom:card>

    <atom:card>
        <atom:table :paginate="$orders" :sticky-pager=" False " />
    </atom:card>

    <atom:card>
        <atom:table :paginate="$orders" x-bind:sticky-pager="false" />
    </atom:card>

    <atom:card>
        <atom:table :paginate="$orders" sticky-pager="" />
    </atom:card>

    <atom:card>
        <atom:table :paginate="null" />
        <atom:table :paginate=" false " />
        <atom:table :paginate="[]" />
        <atom:table paginate="" />
    </atom:card>

    <div class="max-h-96 overflow-y-auto">
        <atom:table :paginate="$orders" />
    </div>

    <div class="h-96 overflow-hidden">
        <atom:table :paginate="$orders" />
    </div>

    <div class="h-screen overflow-auto">
        <atom:table :paginate="$orders" />
    </div>

    <div class="h-full overflow-hidden">
        <atom:table :paginate="$orders" />
    </div>

    <div class="lg:h-[40rem] overflow-hidden">
        <atom:table :paginate="$orders" />
    </div>

    <div class="size-96 overflow-hidden">
        <atom:table :paginate="$orders" />
    </div>

    <div class="fixed overflow-hidden">
        <atom:table :paginate="$orders" />
    </div>

    <div class="absolute inset-0 overflow-auto">
        <atom:table :paginate="$orders" />
    </div>

    <dialog class="overflow-hidden">
        <atom:table :paginate="$orders" />
    </dialog>

    <div class="overflow-auto" popover>
        <atom:table :paginate="$orders" />
    </div>

    <div class="overflow-auto">
        <atom:dropdown>
            <atom:table :paginate="$orders" />
        </atom:dropdown>
    </div>

    <atom:card class="overflow-clip">
        <atom:table :paginate="$orders" />
    </atom:card>

    <div class="xoverflow-hidden overflow-hidden-x overflow-x-hidden-y foo-overflow-y-auto">
        <atom:table :paginate="$orders" />
    </div>

    <div class="{{ $wide ? overflow-hidden : '' }}">
        <atom:table :paginate="$orders" />
    </div>

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
