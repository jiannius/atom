<div>
    @if ($record)
        <a href="/atom/e2e/url-filters" wire:navigate data-listing-link>{{ t('Listing') }}</a>
        <button type="button" onclick="window.dispatchEvent(new CustomEvent('navigate-back'))" data-back>Back</button>
    @else
        <atom:table :empty="false">
            <x-slot:header>
                <atom:table.filters>
                    <atom:table.search wire:model.live.debounce.250ms="filters.search" placeholder="Search fruit..." data-search />

                    <atom:select variant="filter" wire:model.live="filters.status" label="Status" multiple :options="[
                        ['value' => 'fresh', 'label' => 'Fresh'],
                        ['value' => 'dried', 'label' => 'Dried'],
                    ]" />
                </atom:table.filters>
            </x-slot:header>

            <x-slot:columns>
                <atom:table.column>Name</atom:table.column>
                <atom:table.column>Status</atom:table.column>
            </x-slot:columns>

            <x-slot:rows>
                @foreach ($this->rows as $row)
                    <atom:table.row wire:key="row-{{ $row['name'] }}">
                        <atom:table.cell data-name="{{ $row['name'] }}">{{ $row['name'] }}</atom:table.cell>
                        <atom:table.cell>{{ $row['status'] }}</atom:table.cell>
                    </atom:table.row>
                @endforeach
            </x-slot:rows>
        </atom:table>

        <a href="/atom/e2e/url-filters/record" wire:navigate data-open-record>{{ t('Open record') }}</a>
    @endif
</div>
