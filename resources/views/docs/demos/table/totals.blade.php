{{-- Totals live in the table's own `footer` slot, one <atom:table.totals> a line: the label, the figure, and `total` for the grand total.
     The line spans every column that is drawn, so its figure ends on the table's right edge: under the amount column, which must be the
     last one. `column` names that column, so hiding it hides the lines too. Hide Qty or Unit price in the picker: the figures stay put. A blank
     `value` draws the "—" and a figure of your own (a badge, markup) goes in the slot. --}}
<div class="overflow-clip rounded-panel border bg-surface">
    <atom:table.toolbar strip :count="false" columns="lines-demo"/>

    <atom:table id="lines-demo" frameless :empty="false">
        <x-slot:columns>
            <atom:table.column name="item">Item</atom:table.column>
            <atom:table.column name="qty" hideable numeric>Qty</atom:table.column>
            <atom:table.column name="price" hideable numeric>Unit price (RM)</atom:table.column>
            <atom:table.column name="amount" numeric>Amount (RM)</atom:table.column>
        </x-slot:columns>

        <x-slot:rows>
            @foreach ([['Base module', 1, '1,000.00', '1,000.00'], ['Additional hours', 12, '95.00', '1,140.00']] as [$item, $qty, $price, $amount])
                <atom:table.row>
                    <atom:table.cell column="item">{{ $item }}</atom:table.cell>
                    <atom:table.cell column="qty" numeric>{{ $qty }}</atom:table.cell>
                    <atom:table.cell column="price" numeric>{{ $price }}</atom:table.cell>
                    <atom:table.cell column="amount" numeric>{{ $amount }}</atom:table.cell>
                </atom:table.row>
            @endforeach
        </x-slot:rows>

        <x-slot:footer>
            <atom:table.totals column="amount" label="Subtotal" value="2,140.00"/>
            <atom:table.totals column="amount" label="Discount" :value="null"/>
            <atom:table.totals column="amount" label="Service tax 8%" value="171.20"/>
            <atom:table.totals column="amount" label="Total (RM)" value="2,311.20" total/>
        </x-slot:footer>
    </atom:table>
</div>
