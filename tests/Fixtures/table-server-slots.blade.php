<div>
    <div class="flex gap-3 pb-3">
        <button type="button" wire:click="$set('foo', true)" data-set-foo>Set foo</button>
        <button type="button" wire:click="$set('note', 'changed note')" data-set-note>Change note</button>
        <button type="button" wire:click="$set('region', 'north')" data-set-region>Set region</button>
    </div>

    <atom:table :sticky-selection="$sticky" select-all :paginate="$this->pager">
        <x-slot:header>
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
