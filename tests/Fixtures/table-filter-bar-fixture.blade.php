<div>
    <button type="button" wire:click="bump" data-testid="bump">Bump {{ $ticks }}</button>

    <atom:table :paginate="$plain ? null : $items" :frameless="$frameless" :empty="false" :sticky-pager="$stickyPager">
        <x-slot:header>
            <atom:table.toolbar :count="$count">
                <atom:table.search wire:model.live.debounce.150ms="filters.search" placeholder="Search items" />

                <atom:table.filters chips="inline">
                    <atom:select variant="filter" add wire:model.live="filters.status" label="Status" :options="[
                        ['value' => 'open', 'label' => 'Open'],
                        ['value' => 'closed', 'label' => 'Closed'],
                        ['value' => 'hold', 'label' => 'On hold'],
                    ]" />
                    <atom:select variant="filter" add multiple wire:model.live="filters.tags" label="Tag" :options="[
                        ['value' => 'a', 'label' => 'Tag A'],
                        ['value' => 'b', 'label' => 'Tag B'],
                        ['value' => 'c', 'label' => 'Tag C'],
                        ['value' => 'd', 'label' => 'Tag D'],
                    ]" />
                    <atom:date-picker variant="range" add wire:model.live="filters.issued" placeholder="Issued" />

                    <x-slot:more>
                        <atom:select variant="filter" add wire:model.live="filters.region" label="Region" :options="[
                            ['value' => 'n', 'label' => 'North'],
                            ['value' => 's', 'label' => 'South'],
                        ]" />
                    </x-slot:more>
                </atom:table.filters>

                <x-slot:end>
                    @if ($iconEnd)
                        <atom:button size="sm" variant="ghost" icon="download" aria-label="Export"><span class="max-sm:sr-only">Export</span></atom:button>
                    @else
                        <atom:button size="sm" variant="ghost" icon="download">Export</atom:button>
                    @endif
                </x-slot:end>
            </atom:table.toolbar>
        </x-slot:header>

        <x-slot:columns>
            <atom:table.column>Name</atom:table.column>
            <atom:table.column>Status</atom:table.column>
            <atom:table.column>Tag</atom:table.column>
            @if ($wide)
                @for ($i = 1; $i <= 12; $i++)
                    <atom:table.column>Column {{ $i }}</atom:table.column>
                @endfor
            @endif
        </x-slot:columns>

        <x-slot:rows>
            @foreach ($items as $row)
                <atom:table.row wire:key="row-{{ $row['id'] }}">
                    <atom:table.cell data-name="{{ $row['name'] }}">{{ $row['name'] }}</atom:table.cell>
                    <atom:table.cell>{{ $row['status'] }}</atom:table.cell>
                    <atom:table.cell>{{ $row['tag'] }}</atom:table.cell>
                    @if ($wide)
                        @for ($i = 1; $i <= 12; $i++)
                            <atom:table.cell>A long value in column {{ $i }}</atom:table.cell>
                        @endfor
                    @endif
                </atom:table.row>
            @endforeach
        </x-slot:rows>
    </atom:table>
</div>
