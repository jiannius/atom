<?php

describe('stat', function () {
    it('prints a label, a tabular value and a small prefix', function () {
        $html = renderBlade('<atom:stat label="Outstanding" prefix="RM" value="12,480.00" />');
        $stat = domQuery($html, '//*[@data-atom-stat]')[0];
        $value = domQuery($html, '//*[@data-atom-stat-value]')[0];

        expect($stat->nodeName)->toBe('div')
            ->and($stat->textContent)->toContain('Outstanding')
            ->and(domClasses($value))->toContain('tabular-nums', 'font-semibold')
            ->and(domQuery($html, '//*[@data-atom-stat-value]/small')[0]->textContent)->toBe('RM')
            ->and(trim(preg_replace('/\s+/', ' ', $value->textContent)))->toBe('RM 12,480.00');
    });

    it('leaves the prefix out when there is none, and takes a slot for the value', function () {
        $html = renderBlade('<atom:stat label="Open"><b id="v">7</b></atom:stat>');

        expect(domQuery($html, '//*[@data-atom-stat-value]/small'))->toBeEmpty()
            ->and(domQuery($html, '//*[@data-atom-stat-value]/b[@id="v"]'))->toHaveCount(1);
    });

    it('has no fixed height', function () {
        $stat = domQuery(renderBlade('<atom:stat label="x" value="1" />'), '//*[@data-atom-stat]')[0];

        expect(array_values(array_filter(domClasses($stat), fn ($c) => preg_match('/^(h|min-h|max-h)-/', $c))))->toBeEmpty();
    });

    it('prints label, value, prefix, breakdown and delta as text, never markup', function () {
        $evil = '<img src=x onerror=alert(1)>';
        $html = renderBlade('<atom:stat :label="$e" :value="$e" :prefix="$e" :breakdown="$e" :delta="$d" />', ['e' => $evil, 'd' => '+<b>5</b>']);

        expect(domQuery($html, '//*[@data-atom-stat]//img | //*[@data-atom-stat]//b'))->toBeEmpty()
            ->and($html)->toContain('&lt;img src=x onerror=alert(1)&gt;')
            ->and(substr_count($html, '&lt;img src=x onerror=alert(1)&gt;'))->toBe(4);
    });

    describe('href', function () {
        it('is a link when the url is safe', function (string $href) {
            $stat = domQuery(renderBlade('<atom:stat label="x" value="1" :href="$href" />', ['href' => $href]), '//*[@data-atom-stat]')[0];

            expect($stat->nodeName)->toBe('a')->and($stat->getAttribute('href'))->toBe($href);
        })->with(['/jobs?status=late', 'https://example.com/x', 'mailto:a@example.com']);

        it('degrades to a plain block when safe_url() refuses it', function (string $href) {
            $stat = domQuery(renderBlade('<atom:stat label="x" value="1" :href="$href" />', ['href' => $href]), '//*[@data-atom-stat]')[0];

            expect($stat->nodeName)->toBe('div')->and($stat->hasAttribute('href'))->toBeFalse();
        })->with(['javascript:alert(1)', ' JaVaScRiPt:alert(1)', 'data:text/html,x', 'vbscript:x']);

        it('only a link gets the hover surface', function () {
            $link = domQuery(renderBlade('<atom:stat label="x" value="1" href="/a" />'), '//*[@data-atom-stat]')[0];
            $plain = domQuery(renderBlade('<atom:stat label="x" value="1" />'), '//*[@data-atom-stat]')[0];

            expect(domClasses($link))->toContain('hover:bg-surface-sunken')
                ->and(domClasses($plain))->not->toContain('hover:bg-surface-sunken');
        });
    });

    describe('delta', function () {
        it('reads its direction from the sign, and says it to a screen reader', function (string $delta, string $direction, string $word) {
            $html = renderBlade('<atom:stat label="x" value="1" :delta="$delta" />', ['delta' => $delta]);
            $el = domQuery($html, '//*[@data-atom-stat-delta]')[0];

            expect($el->getAttribute('data-direction'))->toBe($direction)
                ->and($el->textContent)->toContain($delta);

            if ($word !== '') {
                expect(domQuery($html, '//*[@data-atom-stat-delta]//*[contains(@class, "sr-only")]')[0]->textContent)->toBe($word)
                    ->and(domQuery($html, '//*[@data-atom-stat-delta]//*[@data-atom-icon]'))->toHaveCount(1);
            }
        })->with([
            ['+12%', 'up', 'Up'],
            ['12%', 'up', 'Up'],
            ['-3.5%', 'down', 'Down'],
            ["\u{2212}4", 'down', 'Down'],
            ['0%', 'flat', ''],
            ['+0.0', 'flat', ''],
        ]);

        it('colours a rise as good, a fall as bad, and inverse flips them', function (string $delta, bool $inverse, string $class) {
            $el = domQuery(renderBlade('<atom:stat label="x" value="1" :delta="$delta" :inverse="$inverse" />', ['delta' => $delta, 'inverse' => $inverse]), '//*[@data-atom-stat-delta]')[0];

            expect(domClasses($el))->toContain($class);
        })->with([
            ['+5%', false, 'text-success-fg'],
            ['-5%', false, 'text-danger-fg'],
            ['+5%', true, 'text-danger-fg'],
            ['-5%', true, 'text-success-fg'],
            ['0%', false, 'text-fg-subtle'],
            ['0%', true, 'text-fg-subtle'],
        ]);

        it('is absent without one', function () {
            expect(domQuery(renderBlade('<atom:stat label="x" value="1" />'), '//*[@data-atom-stat-delta] | //*[@data-atom-stat-sub]'))->toBeEmpty();
        });
    });

    it('prints a breakdown line', function () {
        $html = renderBlade('<atom:stat label="x" value="1" breakdown="3 jobs" />');

        expect(domQuery($html, '//*[@data-atom-stat-sub]')[0]->textContent)->toContain('3 jobs');
    });

    it('tints the value by tone, and only for a known one', function () {
        $danger = domQuery(renderBlade('<atom:stat label="x" value="1" tone="danger" />'), '//*[@data-atom-stat-value]')[0];
        $other = domQuery(renderBlade('<atom:stat label="x" value="1" tone="pink" />'), '//*[@data-atom-stat-value]')[0];

        expect(domClasses($danger))->toContain('text-danger-fg')
            ->and(array_filter(domClasses($other), fn ($c) => str_starts_with($c, 'text-') && str_ends_with($c, '-fg')))->toBeEmpty();
    });

    it('prints the bag', function () {
        $stat = domQuery(renderBlade('<atom:stat id="s" class="ms-2" label="x" value="1" x-show="ready" />'), '//*[@data-atom-stat]')[0];

        expect($stat->getAttribute('id'))->toBe('s')->and($stat->getAttribute('x-show'))->toBe('ready')->and(domClasses($stat))->toContain('ms-2');
    });
});

describe('stat.group', function () {
    it('holds its stats in one outlined, wrapping strip', function () {
        $html = renderBlade('<atom:stat.group class="mt-2"><atom:stat label="a" value="1" /><atom:stat label="b" value="2" /></atom:stat.group>');
        $group = domQuery($html, '//*[@data-atom-stat-group]')[0];

        expect(domQuery($html, '//*[@data-atom-stat-group]/*[@data-atom-stat]'))->toHaveCount(2)
            ->and(domClasses($group))->toContain('flex', 'flex-wrap', 'gap-px', 'rounded-panel', 'overflow-hidden', 'mt-2');
    });

    it('takes the stats chrome off in atom.css, where a utility cannot undo it', function () {
        $css = file_get_contents(dirname(__DIR__, 2).'/resources/css/atom.css');

        expect($css)->toMatch('/\[data-atom-stat-group\] > \[data-atom-stat\]\s*\{\s*flex: 1 1 10\.5rem;\s*border: 0;\s*border-radius: 0;/');
    });
});
