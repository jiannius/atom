{{-- the panel is yours; the table inside it draws no frame of its own --}}
<div class="max-w-2xl overflow-hidden rounded-panel border bg-surface">
    <atom:table frameless :empty="false">
        <x-slot:columns>
            <atom:table.column>Customer</atom:table.column>
            <atom:table.column>Status</atom:table.column>
            <atom:table.column numeric>Amount (RM)</atom:table.column>
        </x-slot:columns>

        <x-slot:rows>
            <atom:table.row>
                <atom:table.cell>
                    <div class="font-medium">Customer A</div>
                    <div class="mt-0.5 text-xs text-fg-subtle">Monthly support, October</div>
                </atom:table.cell>
                <atom:table.cell><atom:status tone="danger" label="23 days overdue"/></atom:table.cell>
                <atom:table.cell numeric>1,300.00</atom:table.cell>
            </atom:table.row>

            <atom:table.row>
                <atom:table.cell>
                    <div class="font-medium">Customer B</div>
                    <div class="mt-0.5 text-xs text-fg-subtle">Website care plan</div>
                </atom:table.cell>
                <atom:table.cell><atom:status tone="success" label="Paid"/></atom:table.cell>
                <atom:table.cell numeric>2,000.00</atom:table.cell>
            </atom:table.row>
        </x-slot:rows>
    </atom:table>
</div>
