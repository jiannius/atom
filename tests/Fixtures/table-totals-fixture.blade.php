<div>
    <button type="button" wire:click="bump" data-bump>Re-render</button>

    <atom:table :empty="false" id="lines" wire:key="lines" data-testid="lines">
        <x-slot:columns>
            <atom:table.column name="item">Item</atom:table.column>
            <atom:table.column name="qty" hideable numeric>Qty</atom:table.column>
            <atom:table.column name="amount" hideable numeric>Amount (RM)</atom:table.column>
        </x-slot:columns>

        <x-slot:rows>
            <atom:table.row wire:key="row-1">
                <atom:table.cell column="item">Base module</atom:table.cell>
                <atom:table.cell column="qty" numeric>1</atom:table.cell>
                <atom:table.cell column="amount" numeric>1,000.00</atom:table.cell>
            </atom:table.row>
        </x-slot:rows>

        <x-slot:footer>
            <atom:table.totals column="amount" label="Subtotal" :value="number_format(1000 + $bumps, 2)"/>
            <atom:table.totals column="amount" label="Total (RM)" :value="number_format(1080 + $bumps, 2)" total/>
        </x-slot:footer>
    </atom:table>
</div>
