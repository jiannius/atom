<div>
    <div class="flex gap-3 pb-3">
        <button type="button" wire:click="$set('foo', true)" data-set-foo>Set foo</button>
        <button type="button" wire:click="$set('note', 'changed note')" data-set-note>Change note</button>
        <button type="button" wire:click="$set('region', 'north')" data-set-region>Set region</button>
        <button type="button" wire:click="$set('bigger', true)" data-set-bigger>Grow</button>
    </div>

    <atom:table :sticky-selection="$sticky" select-all :paginate="$this->pager">
        <x-slot:header>
            @if ($menu)
                <atom:table.toolbar :count="false" data-testid="menu-toolbar">
                    <x-slot:actions>
                        <atom:button variant="ghost" icon="download" aria-label="Export" />
                    </x-slot:actions>
                    <x-slot:menu>
                        @if ($foo)
                            <atom:menu.item icon="check" data-testid="menu-foo">Foo is on</atom:menu.item>
                        @endif
                        <atom:menu.item icon="archive" wire:click="$set('note', 'from the menu')" data-testid="menu-note">Set the note</atom:menu.item>
                    </x-slot:menu>
                </atom:table.toolbar>
            @endif

            <atom:table.filters>
                <atom:table.search wire:model.live.debounce.0ms="search" placeholder="Search fruit..." data-search />
            </atom:table.filters>

            <atom:input wire:model.live="note" aria-label="Note" data-testid="header-input" />

            @if ($foo)
                <span id="x" data-testid="foo-marker">foo is on</span>
            @endif

            <atom:select wire:model.live="region" aria-label="Region" data-select="region" :options="[
                ['value' => 'north', 'label' => 'North'],
                ['value' => 'south', 'label' => 'South'],
            ]" />

            @if ($region)
                <atom:select wire:model.live="city" aria-label="City" data-select="city" :options="$this->cities" />
            @endif
        </x-slot:header>

        <x-slot:checked>
            <span data-testid="checked-note">{{ $note }}</span>

            @if ($foo)
                <span data-testid="checked-foo">foo in the bar</span>
            @endif
        </x-slot:checked>

        <x-slot:columns>
            <atom:table.column checkbox />
            <atom:table.column>Name</atom:table.column>
        </x-slot:columns>

        <x-slot:rows>
            @foreach (\Jiannius\Atom\Tests\Fixtures\TableServerSlotsFixture::ROWS as $row)
                <atom:table.row wire:key="row-{{ $row['id'] }}">
                    <atom:table.cell :checkbox="$row['id']" />
                    <atom:table.cell>{{ $row['name'] }}</atom:table.cell>
                </atom:table.row>
            @endforeach
        </x-slot:rows>
    </atom:table>
</div>
