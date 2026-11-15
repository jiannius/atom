@php
// Totals under a lines table (<atom:table.totals>), on the consumer's Tailwind: ?tw=floor|latest, ?density=compact|comfortable, ?dark=1.
// `lines` is the framed table, with the columns the user can hide; `frameless` is the same inside a panel; `blank` has a figure with no value; `plain` has no named
// column at all. Invented, generic data. tests/e2e/table-totals.spec.js hides columns through window.atom.tableColumns.
$tw = in_array(request()->query('tw'), ['floor', 'latest'], true) ? request()->query('tw') : 'latest';
$density = in_array(request()->query('density'), ['compact', 'comfortable'], true) ? request()->query('density') : 'compact';
$lines = [['Base module', 1, '1,000.00', '80.00', '1,080.00'], ['Additional hours', 12, '95.00', '91.20', '1,231.20'], ['Travel, 2 days', 2, '350.00', '56.00', '756.00']];
@endphp

<atom:html title="E2E: Table totals" :vite="[]" :dark="request()->query('dark') === '1'" :density="$density" :styles="['/atom/e2e/consumer-css/'.$tw]">
<div class="space-y-8 p-6">
    <atom:table :empty="false" id="lines" data-testid="lines">
        <x-slot:columns>
            <atom:table.column name="item">Item</atom:table.column>
            <atom:table.column name="qty" hideable numeric>Qty</atom:table.column>
            <atom:table.column name="price" hideable numeric>Unit price (RM)</atom:table.column>
            <atom:table.column name="tax" hideable numeric>Tax (RM)</atom:table.column>
            <atom:table.column name="amount" hideable numeric>Amount (RM)</atom:table.column>
        </x-slot:columns>

        <x-slot:rows>
            @foreach ($lines as [$item, $qty, $price, $tax, $amount])
                <atom:table.row>
                    <atom:table.cell column="item">{{ $item }}</atom:table.cell>
                    <atom:table.cell column="qty" numeric>{{ $qty }}</atom:table.cell>
                    <atom:table.cell column="price" numeric>{{ $price }}</atom:table.cell>
                    <atom:table.cell column="tax" numeric>{{ $tax }}</atom:table.cell>
                    <atom:table.cell column="amount" numeric>{{ $amount }}</atom:table.cell>
                </atom:table.row>
            @endforeach
        </x-slot:rows>

        <x-slot:footer>
            <atom:table.totals column="amount" label="Subtotal" value="3,067.20"/>
            <atom:table.totals column="amount" label="Service tax 8%" value="245.38"/>
            <atom:table.totals column="amount" label="Total (RM)" value="3,312.58" total/>
        </x-slot:footer>
    </atom:table>

    <div class="rounded-panel border bg-surface overflow-clip" data-testid="panel">
        <atom:table frameless :empty="false" id="framed-lines">
            <x-slot:columns>
                <atom:table.column name="item">Item</atom:table.column>
                <atom:table.column name="amount" numeric>Amount (RM)</atom:table.column>
            </x-slot:columns>

            <x-slot:rows>
                <atom:table.row>
                    <atom:table.cell column="item">Base module</atom:table.cell>
                    <atom:table.cell column="amount" numeric>1,000.00</atom:table.cell>
                </atom:table.row>
            </x-slot:rows>

            <x-slot:footer>
                <atom:table.totals column="amount" label="Discount" :value="null"/>
                <atom:table.totals column="amount" label="Zero rated" :value="0"/>
                <atom:table.totals column="amount" label="Total (RM)" total>1,000.00</atom:table.totals>
            </x-slot:footer>
        </atom:table>
    </div>

    <atom:table :empty="false" id="plain" data-testid="plain">
        <x-slot:columns>
            <atom:table.column>Item</atom:table.column>
            <atom:table.column numeric>Amount (RM)</atom:table.column>
        </x-slot:columns>

        <x-slot:rows>
            <atom:table.row>
                <atom:table.cell>Base module</atom:table.cell>
                <atom:table.cell numeric>1,000.00</atom:table.cell>
            </atom:table.row>
        </x-slot:rows>

        <x-slot:footer>
            <atom:table.totals label="Subtotal" value="1,000.00"/>
            <atom:table.totals label="A rather long label that goes on and on about the nature of this charge" value="80.00"/>
        </x-slot:footer>
    </atom:table>

    {{-- `table-layout: fixed` with a width is the one layout a totals line cannot sit in: the cell's 99 spare columns take their share of the
         width, so every real column is squeezed (documented as "auto layout only"; the spec pins it). The table is the same with and without the line. --}}
    <div class="[&_table]:w-full [&_table]:table-fixed" data-testid="fixed">
        @foreach ([true, false] as $withTotals)
            <atom:table :empty="false" id="{{ $withTotals ? 'fixed-with' : 'fixed-bare' }}">
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
                </x-slot:rows>

                @if ($withTotals)
                    <x-slot:footer>
                        <atom:table.totals label="Total (RM)" value="1,000.00" total/>
                    </x-slot:footer>
                @endif
            </atom:table>
        @endforeach
    </div>

    {{-- A column after the amount (the row's ⋯ cell) is the usual way to break "the amount is the last column": the line still ends on the table's edge. --}}
    <atom:table :empty="false" id="actions" data-testid="actions">
        <x-slot:columns>
            <atom:table.column>Item</atom:table.column>
            <atom:table.column numeric>Amount (RM)</atom:table.column>
            <atom:table.column><span class="sr-only">Actions</span></atom:table.column>
        </x-slot:columns>

        <x-slot:rows>
            <atom:table.row>
                <atom:table.cell>Base module</atom:table.cell>
                <atom:table.cell numeric>1,000.00</atom:table.cell>
                <atom:table.actions><atom:menu.item>Edit</atom:menu.item></atom:table.actions>
            </atom:table.row>
        </x-slot:rows>

        <x-slot:footer>
            <atom:table.totals label="Total (RM)" value="1,000.00" total/>
        </x-slot:footer>
    </atom:table>
</div>
</atom:html>
