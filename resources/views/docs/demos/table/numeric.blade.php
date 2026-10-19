<div class="max-w-md">
    <atom:table :empty="false">
        <x-slot:columns>
            <atom:table.column>Item</atom:table.column>
            <atom:table.column numeric>Qty</atom:table.column>
            <atom:table.column numeric>Amount (RM)</atom:table.column>
        </x-slot:columns>

        <x-slot:rows>
            <atom:table.row>
                <atom:table.cell>Base module</atom:table.cell>
                <atom:table.cell numeric>1</atom:table.cell>
                <atom:table.cell numeric>1,000.00</atom:table.cell>
            </atom:table.row>

            <atom:table.row>
                <atom:table.cell>Additional hours</atom:table.cell>
                <atom:table.cell numeric>12</atom:table.cell>
                <atom:table.cell numeric>1,140.00</atom:table.cell>
            </atom:table.row>
        </x-slot:rows>
    </atom:table>
</div>
