<?php

// The empty-value mark: a muted dash that is aria-hidden, with an sr-only "Not set". <atom:dd>, <atom:table.cell> and
// <atom:page.header> draw it for a value that is not there, so a host never types a "—" or "--".

function missingMarks(string $html): array
{
    return domQuery($html, '//*[@data-atom-missing]');
}

describe('the mark', function () {
    it('is a muted aria-hidden dash with an sr-only "Not set" beside it', function () {
        $mark = missingMarks(renderBlade('<atom:dd label="Email" />'))[0];
        $parts = $mark->getElementsByTagName('span');

        expect(domClasses($mark))->toContain('text-fg-subtle')
            ->and($parts)->toHaveCount(2)
            ->and($parts[0]->getAttribute('aria-hidden'))->toBe('true')
            ->and($parts[0]->textContent)->toBe('—')
            ->and(domClasses($parts[1]))->toContain('sr-only')
            ->and($parts[1]->textContent)->toBe('Not set');
    });
});

describe('dd', function () {
    it('draws the mark for an empty slot or one of whitespace and comments', function (string $slot) {
        $html = renderBlade('<atom:dd label="A">'.$slot.'</atom:dd>');

        expect(missingMarks($html))->toHaveCount(1);
    })->with(['empty' => [''], 'whitespace' => ['   '], 'a comment' => ['{{-- later --}}<!-- x -->']]);

    it('prints a value, including 0, and no mark', function (string $slot) {
        $html = renderBlade('<atom:dd label="A">'.$slot.'</atom:dd>');

        expect(missingMarks($html))->toBeEmpty()
            ->and(trim(domQuery($html, '//dd')[0]->textContent))->toBe($slot);
    })->with(['text' => ['Jane'], 'zero' => ['0']]);

    it('prints a custom filler as text, and nothing for an empty or false one', function () {
        $custom = renderBlade('<atom:dd label="A" filler="n/a" />');
        $blank = renderBlade('<atom:dd label="A" filler="" />');
        $off = renderBlade('<atom:dd label="A" :filler="false" />');

        expect(trim(domQuery($custom, '//dd')[0]->textContent))->toBe('n/a')
            ->and(missingMarks($custom))->toBeEmpty()
            ->and(trim(domQuery($blank, '//dd')[0]->textContent))->toBe('')
            ->and(trim(domQuery($off, '//dd')[0]->textContent))->toBe('')
            ->and(missingMarks($blank))->toBeEmpty()
            ->and(missingMarks($off))->toBeEmpty();
    });

    it('prints a dash a host typed as typed, and is not read as the mark', function () {
        $html = renderBlade('<atom:dd label="A">--</atom:dd>');

        expect(missingMarks($html))->toBeEmpty()
            ->and(trim(domQuery($html, '//dd')[0]->textContent))->toBe('--');
    });

    it('reads markup with no text as content: the docs say to wrap optional markup in @if', function () {
        $html = renderBlade('<atom:dd label="A"><span class="font-id"></span></atom:dd>');

        expect(missingMarks($html))->toBeEmpty();
    });
});

describe('table.cell', function () {
    function cell(string $attributes = '', string $slot = ''): string
    {
        return renderBlade('<table><tbody><tr><atom:table.cell '.$attributes.'>'.$slot.'</atom:table.cell></tr></tbody></table>');
    }

    it('draws the mark in an empty cell, and no mark in one with content or a 0', function () {
        expect(missingMarks(cell()))->toHaveCount(1)
            ->and(missingMarks(cell('', '   ')))->toHaveCount(1)
            ->and(missingMarks(cell('', 'Jane')))->toBeEmpty()
            ->and(trim(domQuery(cell('', '0'), '//td')[0]->textContent))->toBe('0')
            ->and(missingMarks(cell('', '0')))->toBeEmpty();
    });

    it('right-aligns the mark in a numeric cell, like the figures', function () {
        $td = domQuery(cell('numeric'), '//td')[0];

        expect(domClasses($td))->toContain('text-right', 'tabular-nums')
            ->and(missingMarks(cell('numeric')))->toHaveCount(1)
            ->and($td->getElementsByTagName('span')->length)->toBe(3);
    });

    it('prints a custom filler as text and nothing for :filler="false" or an empty one', function () {
        expect(trim(domQuery(cell('filler="n/a"'), '//td')[0]->textContent))->toBe('n/a')
            ->and(missingMarks(cell('filler="n/a"')))->toBeEmpty()
            ->and(missingMarks(cell(':filler="false"')))->toBeEmpty()
            ->and(trim(domQuery(cell(':filler="false"'), '//td')[0]->textContent))->toBe('')
            ->and(missingMarks(cell('filler=""')))->toBeEmpty();
    });

    it('draws no mark in an empty spacer cell that has colspan, nor in a checkbox cell', function () {
        expect(missingMarks(cell('colspan="3"')))->toBeEmpty()
            ->and(missingMarks(cell('colspan="3"', 'Total')))->toBeEmpty()
            ->and(domQuery(cell('colspan="3"'), '//td[@colspan="3"]'))->toHaveCount(1);
    });

    it('draws no mark in a checkbox cell', function () {
        $html = renderBlade('<atom:table.cell checkbox="7" />');

        expect(missingMarks($html))->toBeEmpty()
            ->and(domQuery($html, '//td'))->toHaveCount(1);
    });
});

describe('page.header facts', function () {
    it('keeps a labelled fact with no value as "Label —"', function () {
        $html = renderBlade('<atom:page.header title="T" :facts="$f" />', ['f' => [['label' => 'Checked by', 'value' => '']]]);
        $fact = domQuery($html, '//*[@data-atom-page-meta]/span')[0];

        expect($fact->textContent)->toContain('Checked by')
            ->and(missingMarks($html))->toHaveCount(1);
    });

    it('still drops a bare blank string, and keeps "0" and 0', function () {
        $html = renderBlade('<atom:page.header title="T" :facts="$f" />', ['f' => ['', '0', 0]]);

        expect(domQuery($html, '//*[@data-atom-page-meta]/span'))->toHaveCount(2)
            ->and(missingMarks($html))->toBeEmpty();
    });
});
