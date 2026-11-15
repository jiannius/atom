@php
// a host's own layout on the card (?host=grid|flex|space3|space6), as the record and document patterns' rails write it: literals, so the consumer build sees them
$hostClass = ['grid' => 'grid gap-3', 'flex' => 'flex flex-col gap-3', 'space3' => 'space-y-3', 'space6' => 'space-y-6'][$host] ?? null;
@endphp

<div class="space-y-6">
    <button type="button" wire:click="bump" data-testid="bump">Bump {{ $ticks }}</button>

    <atom:card heading="Customer" data-testid="card" :class="$hostClass">
        <x-slot:actions>
            <atom:link href="#all">View all</atom:link>
        </x-slot:actions>

        @if ($menus)
            <x-slot:menu>
                <atom:menu.item wire:click="bump">Bump from the card</atom:menu.item>
                <atom:menu.item>Export</atom:menu.item>
            </x-slot:menu>
        @endif

        <p data-testid="card-body">Customer A</p>
    </atom:card>

    <atom:panel heading="Invoices" :count="$items->total()" data-testid="panel">
        <x-slot:actions>
            <atom:link href="#view">View all</atom:link>
        </x-slot:actions>

        @if ($menus)
            <x-slot:menu>
                <atom:menu.item wire:click="bump">Bump from the panel</atom:menu.item>
                <atom:menu.item>Export</atom:menu.item>
            </x-slot:menu>
        @endif

        <atom:table frameless :empty="false" :paginate="$items">
            <x-slot:columns>
                <atom:table.column>Name</atom:table.column>
                <atom:table.column>Status</atom:table.column>
                @if ($wide)
                    @for ($i = 1; $i <= 12; $i++)
                        <atom:table.column>Column {{ $i }}</atom:table.column>
                    @endfor
                @endif
            </x-slot:columns>

            <x-slot:rows>
                @foreach ($items as $row)
                    <atom:table.row wire:key="row-{{ $row['id'] }}">
                        <atom:table.cell>{{ $row['name'] }}</atom:table.cell>
                        <atom:table.cell>{{ $row['status'] }}</atom:table.cell>
                        @if ($wide)
                            @for ($i = 1; $i <= 12; $i++)
                                <atom:table.cell>A long value in column {{ $i }}</atom:table.cell>
                            @endfor
                        @endif
                    </atom:table.row>
                @endforeach
            </x-slot:rows>
        </atom:table>

        <x-slot:footer>
            <span>{{ $items->count() }} of {{ $items->total() }} invoices</span>
        </x-slot:footer>
    </atom:panel>
</div>
