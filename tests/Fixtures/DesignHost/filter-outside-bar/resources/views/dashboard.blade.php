{{-- A dashboard: the range and the currency narrow the stats and the chart as well as the table, and sit beside the table, not around it. --}}
<atom:page>
    <atom:page.header title="Dashboard" />

    <atom:date-picker variant="range" wire:model.live="filters.range" />

    <div class="flex gap-2">
        <atom:button wire:click="$set('filters.currency', 'MYR')">MYR</atom:button>
        <atom:button wire:click="$set('filters.currency', 'USD')">USD</atom:button>
    </div>

    <atom:stat.group>
        <atom:stat label="Sales" />
    </atom:stat.group>

    <atom:panel heading="Latest">
        <input type="search" wire:model.live="filters.name" placeholder="Name"> {{-- expect: filter-outside-bar --}}

        <atom:table frameless>
            <x-slot:columns>
                <atom:table.column>Order</atom:table.column>
            </x-slot:columns>
        </atom:table>
    </atom:panel>

    <atom:page.columns>
        <atom:panel heading="Orders">
            <atom:table frameless>
                <x-slot:columns>
                    <atom:table.column>Order</atom:table.column>
                </x-slot:columns>
            </atom:table>
        </atom:panel>

        <x-slot:rail label="Region">
            <atom:select variant="filter" wire:model="filters.region" label="Region" :options="$regions" />
        </x-slot:rail>
    </atom:page.columns>

    <div>
        <atom:select variant="filter" wire:model="filters.owner" label="Owner" :options="$owners" />
    </div>
</atom:page>
