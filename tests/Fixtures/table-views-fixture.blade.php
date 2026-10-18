<div>
    <div class="mb-3 flex gap-2">
        <atom:button size="sm" wire:click="markPaid(1)" data-testid="mark-paid">Mark INV-0401 paid</atom:button>
        <span data-testid="bound">{{ $filters['view'] }}</span>
    </div>

    <div class="min-w-0 overflow-hidden rounded-panel border bg-surface" data-testid="panel">
        <atom:table.views wire:model.live="filters.view" :current="$filters['view']" :counts="fn ($view) => $this->count($view)">
            <atom:table.views.item value="all" label="All" />
            <atom:table.views.item value="unpaid" label="Unpaid" />
            <atom:table.views.item value="overdue" label="Overdue" />
            <atom:table.views.item value="paid" label="Paid" />
        </atom:table.views>

        <atom:table frameless :empty="false">
            <x-slot:header>
                <atom:table.toolbar>
                    <atom:table.search wire:model.live.debounce.250ms="filters.search" placeholder="Search invoices" />

                    <x-slot:end>
                        <atom:button size="sm" variant="ghost" icon="download">Export</atom:button>
                    </x-slot:end>
                </atom:table.toolbar>
            </x-slot:header>

            <x-slot:checked>
                <atom:button size="sm" data-testid="bulk-action">Send reminders</atom:button>
            </x-slot:checked>

            <x-slot:columns>
                <atom:table.column checkbox />
                <atom:table.column>Number</atom:table.column>
                <atom:table.column>Customer</atom:table.column>
                <atom:table.column numeric>Amount (RM)</atom:table.column>
            </x-slot:columns>

            <x-slot:rows>
                @foreach ($this->rows as $row)
                    <atom:table.row wire:key="invoice-{{ $row['id'] }}">
                        <atom:table.cell :checkbox="$row['id']" />
                        <atom:table.cell><span class="font-id">{{ $row['number'] }}</span></atom:table.cell>
                        <atom:table.cell>
                            <div class="font-medium">{{ $row['customer'] }}</div>
                            <div class="mt-0.5 text-xs text-fg-subtle">{{ $row['state'] }}</div>
                        </atom:table.cell>
                        <atom:table.cell numeric>{{ number_format($row['amount'], 2) }}</atom:table.cell>
                    </atom:table.row>
                @endforeach
            </x-slot:rows>
        </atom:table>

        {{-- counts and totals go below the table, not in the header slot: that slot is an Alpine template, which a re-render does not refresh --}}
        <div class="border-t px-3.5 py-2.5 text-xs text-fg-subtle" data-testid="shown">{{ count($this->rows) }} shown</div>
    </div>
</div>
