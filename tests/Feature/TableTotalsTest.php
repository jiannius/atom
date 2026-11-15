<?php

// <atom:table.totals>: one line of figures (subtotal, tax, total) in the table's own tfoot. The cell spans every column, so it follows
// the columns the user hides; the alignment itself is measured in tests/e2e/table-totals.spec.js.

function totalsLines(string $html): array
{
    return domQuery('<table><tfoot>'.$html.'</tfoot></table>', '//tr[@data-atom-table-totals]');
}

function totalsPart(DOMElement $row, string $part): DOMElement
{
    return (new DOMXPath($row->ownerDocument))->query('.//*[@data-atom-totals-'.$part.']', $row)->item(0);
}

describe('a line', function () {
    it('is a row of one cell that spans more columns than a table has, with the label and the figure in it', function () {
        $row = totalsLines(renderBlade('<atom:table.totals label="Subtotal" value="1,000.00" />'))[0];
        $cells = $row->getElementsByTagName('td');

        expect($cells)->toHaveCount(1)
            ->and($cells[0]->getAttribute('colspan'))->toBe('100')
            ->and(trim(totalsPart($row, 'label')->textContent))->toBe('Subtotal')
            ->and(trim(totalsPart($row, 'value')->textContent))->toBe('1,000.00')
            ->and($row->hasAttribute('data-atom-table-totals-total'))->toBeFalse();
    });

    it('draws the label muted and the figure in the text colour', function () {
        $row = totalsLines(renderBlade('<atom:table.totals label="Subtotal" value="1" />'))[0];

        expect(domClasses(totalsPart($row, 'label')))->toContain('text-fg-muted')
            ->and(domClasses($row->getElementsByTagName('td')[0]))->toContain('text-fg', 'px-row')
            ->and(domClasses($row->getElementsByTagName('td')[0]))->not->toContain('font-semibold', 'border-t');
    });

    it('draws the grand total semibold with a hairline above and an unmuted label', function () {
        $row = totalsLines(renderBlade('<atom:table.totals label="Total (RM)" value="3,312.58" total />'))[0];
        $cell = $row->getElementsByTagName('td')[0];

        expect($row->hasAttribute('data-atom-table-totals-total'))->toBeTrue()
            ->and(domClasses($cell))->toContain('font-semibold', 'border-t')
            ->and(domClasses(totalsPart($row, 'label')))->not->toContain('text-fg-muted');
    });

    it('escapes the label and the figure', function () {
        $html = renderBlade('<atom:table.totals :label="$label" :value="$value" />', ['label' => '<b onclick="x()">Tax</b>', 'value' => '<i>1</i>']);

        expect($html)->not->toContain('<b onclick')->not->toContain('<i>1</i>')
            ->and($html)->toContain('&lt;b onclick=&quot;x()&quot;&gt;Tax&lt;/b&gt;')->toContain('&lt;i&gt;1&lt;/i&gt;');
    });

    it('prints 0 and a number as figures, never the mark', function (mixed $value, string $printed) {
        $html = renderBlade('<atom:table.totals label="Zero rated" :value="$value" />', ['value' => $value]);
        $row = totalsLines($html)[0];

        expect(trim(totalsPart($row, 'value')->textContent))->toBe($printed)
            ->and(domQuery($html, '//*[@data-atom-missing]'))->toBeEmpty();
    })->with(['int zero' => [0, '0'], 'string zero' => ['0', '0'], 'a float' => [12.5, '12.5']]);

    it('draws the missing-value mark for a blank figure', function (mixed $value) {
        $html = renderBlade('<atom:table.totals label="Discount" :value="$value" />', ['value' => $value]);
        $row = totalsLines($html)[0];
        $marks = domQuery($html, '//*[@data-atom-missing]');

        expect($marks)->toHaveCount(1)
            ->and(trim(totalsPart($row, 'value')->textContent))->toBe('—Not set');
    })->with(['null' => [null], 'empty' => [''], 'spaces' => ['  '], 'false' => [false]]);

    it('draws the mark with no value at all', function () {
        expect(domQuery(renderBlade('<atom:table.totals label="Discount" />'), '//*[@data-atom-missing]'))->toHaveCount(1);
    });

    it('takes the slot as the figure, over the value', function () {
        $row = totalsLines(renderBlade('<atom:table.totals label="Paid" value="1"><b>2</b></atom:table.totals>'))[0];

        expect(totalsPart($row, 'value')->getElementsByTagName('b'))->toHaveCount(1)
            ->and(trim(totalsPart($row, 'value')->textContent))->toBe('2');
    });

    it('passes the label through t()', function () {
        app('translator')->setLoaded(['*' => ['*' => ['ms' => ['Subtotal' => 'Jumlah kecil']]]]);
        app()->setLocale('ms');

        try {
            $row = totalsLines(renderBlade('<atom:table.totals label="Subtotal" value="1" />'))[0];
        } finally {
            app()->setLocale('en');
        }

        expect(trim(totalsPart($row, 'label')->textContent))->toBe('Jumlah kecil');
    });

    it('merges the caller attributes onto the cell', function () {
        $cell = totalsLines(renderBlade('<atom:table.totals label="A" value="1" class="uppercase" data-testid="sub" />'))[0]->getElementsByTagName('td')[0];

        expect(domClasses($cell))->toContain('uppercase', 'px-row')
            ->and($cell->getAttribute('data-testid'))->toBe('sub');
    });
});

describe('column', function () {
    it('links the line to a named column, the way a cell does', function () {
        $cell = totalsLines(renderBlade('<atom:table.totals column="amount" label="A" value="1" />'))[0]->getElementsByTagName('td')[0];

        expect($cell->getAttribute('data-atom-column'))->toBe('amount');
    });

    it('is not linked without it', function () {
        $cell = totalsLines(renderBlade('<atom:table.totals label="A" value="1" />'))[0]->getElementsByTagName('td')[0];

        expect($cell->hasAttribute('data-atom-column'))->toBeFalse();
    });

    it('throws for a name that would not be a CSS token', function () {
        try {
            renderBlade('<atom:table.totals column="a]{x" label="A" value="1" />');
        } catch (Throwable $e) {
            while ($e->getPrevious()) {
                $e = $e->getPrevious();
            }
        }

        expect($e ?? null)->toBeInstanceOf(InvalidArgumentException::class)
            ->and($e->getMessage())->toContain('<atom:table.totals column>');
    });
});

describe('in a table', function () {
    $table = <<<'BLADE'
        <atom:table :empty="false">
            <x-slot:columns>
                <atom:table.column name="item">Item</atom:table.column>
                <atom:table.column name="amount" hideable :hidden="$hidden" numeric>Amount</atom:table.column>
            </x-slot:columns>
            <x-slot:rows>
                <atom:table.row><atom:table.cell column="item">A</atom:table.cell><atom:table.cell column="amount" numeric>1</atom:table.cell></atom:table.row>
            </x-slot:rows>
            <x-slot:footer>
                <atom:table.totals column="amount" label="Subtotal" value="1" />
                <atom:table.totals column="amount" label="Total" value="1" total />
            </x-slot:footer>
        </atom:table>
        BLADE;

    it('sits in the footer, after the rows, and each line follows the amount column', function () use ($table) {
        $html = renderBlade($table, ['hidden' => false]);
        $lines = domQuery($html, '//tfoot[@data-atom-table-footer]/tr[@data-atom-table-totals]');

        expect($lines)->toHaveCount(2)
            ->and(domQuery($html, '//tfoot/tr/td[@data-atom-column="amount"]'))->toHaveCount(2)
            ->and(strpos($html, '<tfoot'))->toBeGreaterThan(strpos($html, 'data-atom-table-rows'));
    });

    it('is hidden by the table\'s own column rule when the amount column is', function () use ($table) {
        $html = renderBlade($table, ['hidden' => true]);

        expect($html)->toContain('[data-atom-column-hide~="amount"]>table>:is(thead,tbody,tfoot)>tr>[data-atom-column="amount"]{display:none!important}')
            ->and($html)->toContain('data-atom-column-hide="amount"');
    });
});
