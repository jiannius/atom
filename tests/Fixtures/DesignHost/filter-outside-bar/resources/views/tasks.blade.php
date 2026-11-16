{{-- filter-outside-bar: a control that narrows a list sits outside <atom:table.filters>. A line marked `expect:` is a finding; every other line must stay clean. --}}
<atom:page>
    <atom:page.header title="Tasks" />

    <atom:panel>
        <atom:table.views label="Task views" wire:model.live="filters.view" :current="$view">
            <atom:table.views.item value="all" label="All" />
        </atom:table.views>

        <atom:table.toolbar strip>
            <atom:table.search wire:model="filters.search" placeholder="Search tasks" />

            <atom:select variant="filter" add wire:model="filters.owner" label="Owner" :options="$owners" /> {{-- expect: filter-outside-bar --}}

            <atom:table.filters chips="inline">
                <atom:select variant="filter" add wire:model.live="filters.status" label="Status" :options="$statuses" />
                <atom:date-picker variant="range" add x-model="filters.due" label="Due" />

                <x-slot:more>
                    <atom:select label="Category" variant="listbox" wire:model="filters.category" table-filter :options="$categories" />
                </x-slot:more>
            </atom:table.filters>

            <x-slot:actions>
                <atom:button variant="ghost" wire:click="$set('filters.is_active', false)">Show inactive</atom:button> {{-- expect: filter-outside-bar --}}
                <atom:button variant="ghost" x-on:click="$wire.set('filters.priority', 'high')">High priority</atom:button> {{-- expect: filter-outside-bar --}}
                <atom:button variant="ghost" @click="$wire.filters.state = 'open'">Open</atom:button> {{-- expect: filter-outside-bar --}}
                <atom:button variant="ghost" wire:click="$wire.$set('filters.mine', true)">Mine</atom:button> {{-- expect: filter-outside-bar --}}
                <atom:button variant="ghost" wire:click="export">Export</atom:button>
                <atom:button variant="ghost" wire:click="$set('perPage', 50)">Fifty rows</atom:button>
                <atom:button variant="ghost" wire:click="$set('form.filters.x', 1)">Not the filters property</atom:button>
                <atom:button variant="ghost" x-on:click="open = $wire.filters.state == 'open'">Compares, never assigns</atom:button>
                <atom:button variant="ghost" wire:click="$set('filters.{{ $key }}', 1)">Built from an echo</atom:button>
                <atom:button variant="ghost" wire:click="$set('filters.status', '{{ $status }}')">Value from an echo</atom:button> {{-- expect: filter-outside-bar --}}
                <atom:button variant="ghost" wire:click="$set('filters.a{{ $key }}', 1)">Key ends in an echo</atom:button>
                <atom:button variant="ghost" x-on:click="$wire.filters.a{{ $key }} = 1">Assigned key ends in an echo</atom:button>
                <atom:button variant="ghost" wire:click="$set('filters.a{!! $key !!}', 1)">Key ends in a raw echo</atom:button>
                <atom:button variant="ghost" x-on:click="$wire.filters.a{!! $key !!} = 1">Assigned key ends in a raw echo</atom:button>
            </x-slot:actions>

            <x-slot:end>
                <atom:table.trashed />
            </x-slot:end>
        </atom:table.toolbar>

        <input type="search" wire:model.live.debounce.300ms="filters.name" placeholder="Name"> {{-- expect: filter-outside-bar --}}
        <input type="text" x-model.debounce="filters.sku" placeholder="Sku"> {{-- expect: filter-outside-bar --}}
        <input type="search" wire:model="filters.search" placeholder="Hand-rolled search"> {{-- expect: filter-outside-bar --}}
        <x-field wire:modelable="filters.sku" />
        <x-field x-modelable="filters.sku" />
        <input type="text" wire:model="filters.sku extra">
        <input type="text" wire:model="form.name" placeholder="Not filters.">
        <input type="text" wire:model="query" placeholder="Not a filter">
        <input type="text" wire:model="filters_note" placeholder="Not filters.">
        <input type="text" wire:model="{{ $field }}">

        <atom:table frameless>
            <x-slot:columns>
                <atom:table.column>Task</atom:table.column>
            </x-slot:columns>

            <x-slot:rows>
                <atom:table.row>
                    <atom:table.cell>Paint the fence</atom:table.cell>
                </atom:table.row>
            </x-slot:rows>

            <x-slot:empty>
                <atom:empty heading="No tasks match">
                    <x-slot:action>
                        <atom:button wire:click="$set('filters.status', null)">Clear status</atom:button>
                    </x-slot:action>
                </atom:empty>
                <button type="button" wire:click="$set('filters.owner', null)">Clear owner</button>
            </x-slot:empty>
        </atom:table>
    </atom:panel>

    <atom:tabs>
        <atom:tabs.item wire:click="$set('filters.status', 'open')">Open</atom:tabs.item> {{-- expect: filter-outside-bar --}}
    </atom:tabs>

    <atom:card heading="Not the table">
        <x-slot:empty>
            <button wire:click="$set('filters.owner', null)">Clear owner</button> {{-- expect: filter-outside-bar --}}
        </x-slot:empty>
    </atom:card>

    @if ($tasks->isEmpty())
        <atom:empty heading="Nothing here">
            <atom:button wire:click="$set('filters.status', null)">Clear status</atom:button>
        </atom:empty>
    @endif
</atom:page>
