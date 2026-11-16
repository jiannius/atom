{{-- filters-chips: a toolbar's filters keep the default chips row. A line marked `expect:` is a finding; every other line must stay clean. --}}
<atom:page>
    <atom:page.header title="Contacts" />

    <atom:panel>
        <atom:table.toolbar strip>
            <atom:table.search wire:model="filters.search" placeholder="Search" />

            <atom:table.filters> {{-- expect: filters-chips --}}
                <atom:select variant="filter" wire:model="filters.region" label="Region" :options="$regions" />
            </atom:table.filters>
        </atom:table.toolbar>

        <atom:table.toolbar strip>
            <atom:table.filters chips="inline">
                <atom:select variant="filter" add wire:model="filters.region" label="Region" :options="$regions" />
            </atom:table.filters>
        </atom:table.toolbar>

        <atom:table.toolbar strip>
            <atom:table.filters chips="below">
                <atom:select variant="filter" wire:model="filters.region" label="Region" :options="$regions" />
            </atom:table.filters>
        </atom:table.toolbar>

        <atom:table.toolbar strip>
            <atom:table.filters :chips="$chips">
                <atom:select variant="filter" wire:model="filters.region" label="Region" :options="$regions" />
            </atom:table.filters>
        </atom:table.toolbar>

        <atom:table frameless>
            <x-slot:header>
                <atom:table.filters>
                    <atom:select variant="filter" wire:model="filters.region" label="Region" :options="$regions" />
                </atom:table.filters>
            </x-slot:header>
        </atom:table>
    </atom:panel>
</atom:page>
