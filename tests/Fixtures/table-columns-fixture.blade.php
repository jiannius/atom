<div>
    <div class="flex gap-3 pb-3">
        <input type="text" wire:model.live.debounce.0ms="search" placeholder="Search people" data-testid="search" />
        <button type="button" wire:click="load" data-load>Load</button>
        <button type="button" wire:click="pushState" data-push-state>Push state</button>
        <button type="button" wire:click="resetLayout" data-reset-layout>Reset layout</button>
        <a href="/atom/e2e/table-columns-other" wire:navigate data-go-other>Other page</a>
        <span data-testid="saved">{{ json_encode($events) }}</span>
    </div>

    {{-- Every column is named; the fixture's cells follow them. `name` is locked, the rest hideable. --}}
    <div @if ($transition) wire:transition @endif>
    <atom:table
        :paginate="$items"
        :skeleton="! $loaded"
        :empty="$empty"
        :persist-key="$keyed ? 'people' : null"
        :columns-state="$state"
        wire:key="people-{{ $generation }}"
        data-testid="people">
        <x-slot:columns>
            <atom:table.column checkbox hideable />
            <atom:table.column name="name">Name</atom:table.column>
            <atom:table.column :name="$renamed ? 'mail' : 'email'" hideable>Email</atom:table.column>
            @unless ($removed)
                <atom:table.column name="phone" hideable hidden>Phone</atom:table.column>
            @endunless
            <atom:table.column name="city" hideable class="max-sm:hidden">City</atom:table.column>
            <atom:table.column name="plan" hideable class="hidden lg:table-cell">Plan</atom:table.column>
        </x-slot:columns>

        <x-slot:rows>
            @foreach ($items?->items() ?? [] as $row)
                <atom:table.row wire:key="row-{{ $row['id'] }}">
                    <atom:table.cell :checkbox="$row['id']" />
                    <atom:table.cell column="name">{{ $row['name'] }}</atom:table.cell>
                    <atom:table.cell :column="$renamed ? 'mail' : 'email'">person{{ $row['id'] }}@example.com</atom:table.cell>
                    @unless ($removed)
                        <atom:table.cell column="phone">555-01{{ $row['id'] }}</atom:table.cell>
                    @endunless
                    <atom:table.cell column="city" class="max-sm:hidden">Town {{ $row['id'] }}</atom:table.cell>
                    <atom:table.cell column="plan" class="hidden lg:table-cell">Plan {{ $row['id'] }}</atom:table.cell>
                </atom:table.row>
            @endforeach
        </x-slot:rows>

        <x-slot:footer>
            <atom:table.row>
                <atom:table.cell />
                <atom:table.cell column="name">Total</atom:table.cell>
                <atom:table.cell :column="$renamed ? 'mail' : 'email'">30 people</atom:table.cell>
            </atom:table.row>
        </x-slot:footer>
    </atom:table>
    </div>

    <div x-data x-on:atom-table-columns-changed.window="$wire.saveColumns($event.detail.columns)"></div>
</div>
