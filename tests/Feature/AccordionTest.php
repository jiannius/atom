<?php

describe('accordion', function () {
    it('wires the accordion alpine factory around slot-based items', function () {
        $html = renderBlade(<<<'BLADE'
            <atom:accordion>
                <atom:accordion.item heading="First">Answer one</atom:accordion.item>
                <atom:accordion.item heading="Second">Answer two</atom:accordion.item>
            </atom:accordion>
        BLADE);

        expect($html)
            ->toContain('data-atom-accordion')
            ->toContain('x-data="accordion({ exclusive: false })"')
            ->toContain('data-atom-accordion-item')
            ->toContain('First')
            ->toContain('Answer one')
            ->toContain('Second')
            ->toContain("toggle('atom-accordion-")
            ->toContain("isOpen('atom-accordion-")
            // collapse driven by inline grid + object :style merge, not a plugin
            ->toContain('display: grid')
            ->toContain('overflow: hidden')
            ->toContain("? '1fr' : '0fr'");
    });

    it('forwards the exclusive prop into the factory config', function () {
        $html = renderBlade(<<<'BLADE'
            <atom:accordion exclusive>
                <atom:accordion.item heading="A">a</atom:accordion.item>
            </atom:accordion>
        BLADE);

        expect($html)->toContain('x-data="accordion({ exclusive: true })"');
    });

    it('opens an item on mount when expanded', function () {
        $html = renderBlade(<<<'BLADE'
            <atom:accordion>
                <atom:accordion.item heading="Open me" expanded>content</atom:accordion.item>
            </atom:accordion>
        BLADE);

        expect($html)->toContain("open('atom-accordion-");
    });

    it('does not emit an open() x-init when collapsed', function () {
        $html = renderBlade('<atom:accordion><atom:accordion.item heading="H">c</atom:accordion.item></atom:accordion>');

        expect($html)
            ->toContain('x-init=""')     // empty x-init, no open() call
            ->not->toContain("open('atom-accordion-");
    });

    it('derives the same item id on every render, so a re-render keeps an open item open', function () {
        $template = '<atom:accordion><atom:accordion.item heading="First" expanded>a</atom:accordion.item><atom:accordion.item heading="Second">b</atom:accordion.item></atom:accordion>';

        $ids = fn (string $html) => preg_match_all("/toggle\\('(atom-accordion-[^']+)'\\)/", $html, $m) ? array_values(array_unique($m[1])) : [];

        $first = $ids(renderBlade($template));

        expect($first)->toHaveCount(2)
            ->and($ids(renderBlade($template)))->toBe($first)
            ->and($ids(renderBlade($template)))->toBe($first);
    });

    it('numbers items that share a heading, and lets an explicit id win', function () {
        $html = renderBlade('<atom:accordion><atom:accordion.item heading="Same">a</atom:accordion.item><atom:accordion.item heading="Same">b</atom:accordion.item><atom:accordion.item heading="Own" id="faq-own">c</atom:accordion.item></atom:accordion>');

        preg_match_all("/toggle\\('([^']+)'\\)/", $html, $m);

        expect(array_unique($m[1]))->toHaveCount(3)
            ->and($m[1])->toContain('faq-own');
    });
});
