@php
$slotGap = ['6' => 'gap-6'][$gap] ?? null;
@endphp

{{-- a host's own gap on the slot (`?gap=6`): literals, so the consumer build sees them --}}
<div>
    <span hidden data-renders>{{ $renders }}</span>
    <span data-deleted>{{ $deleted }}</span>

    <div @class(['overflow-hidden rounded-panel border bg-surface' => $frameless]) data-testid="panel">
        <atom:table :frameless="$frameless" :sticky-selection="$sticky" select-all :paginate="$this->pager">
            <x-slot:checked :tone="$tone" :class="$slotGap">
                @foreach (range(1, $buttons) as $n)
                    <atom:button size="sm" data-testid="bulk-action">Send reminders</atom:button>
                @endforeach

                @if ($withDelete)
                    <atom:button type="delete" size="sm">Delete</atom:button>
                @endif
            </x-slot:checked>

            @if ($menu)
                <x-slot:checked-menu>
                    <atom:menu.item data-testid="export-item">Export</atom:menu.item>
                    <atom:menu.separator />
                    <atom:menu.item variant="delete">Delete</atom:menu.item>
                </x-slot:checked-menu>
            @endif

            <x-slot:columns>
                <atom:table.column checkbox />
                <atom:table.column>Name</atom:table.column>
            </x-slot:columns>

            <x-slot:rows>
                @foreach (\Jiannius\Atom\Tests\Fixtures\TableCheckedFixture::ROWS as $row)
                    <atom:table.row wire:key="row-{{ $row['id'] }}">
                        <atom:table.cell :checkbox="$row['id']" />
                        <atom:table.cell>{{ $row['name'] }}</atom:table.cell>
                    </atom:table.row>
                @endforeach
            </x-slot:rows>
        </atom:table>
    </div>
</div>
