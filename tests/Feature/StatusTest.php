<?php

describe('status', function () {
    it('prints the word for every tone, so the colour is never the only signal', function (string $tone) {
        $html = renderBlade('<atom:status tone="'.$tone.'" label="Word '.$tone.'" />');
        $root = domQuery($html, '//*[@data-atom-status]')[0];

        expect($root->getAttribute('data-tone'))->toBe($tone)
            ->and(trim($root->textContent))->toBe('Word '.$tone)
            // the dot is decoration: the word carries the meaning
            ->and(domQuery($html, '//*[@data-atom-status-dot][@aria-hidden="true"]'))->toHaveCount(1);
    })->with(['neutral', 'info', 'success', 'warning', 'danger', 'accent']);

    it('reads each tone from its role token pair, whole-token', function (string $tone, string $bg, string $fg) {
        $root = domQuery(renderBlade('<atom:status tone="'.$tone.'" label="x" />'), '//*[@data-atom-status]')[0];

        expect(domClasses($root))->toContain($bg, $fg, 'rounded-pill');
    })->with([
        ['neutral', 'bg-neutral-bg', 'text-neutral-fg'],
        ['info', 'bg-info-bg', 'text-info-fg'],
        ['success', 'bg-success-bg', 'text-success-fg'],
        ['warning', 'bg-warning-bg', 'text-warning-fg'],
        ['danger', 'bg-danger-bg', 'text-danger-fg'],
        ['accent', 'bg-accent-soft', 'text-accent-ink'],
    ]);

    it('falls back to neutral for a tone it does not know', function () {
        $root = domQuery(renderBlade('<atom:status tone="bright pink" label="x" />'), '//*[@data-atom-status]')[0];

        expect($root->getAttribute('data-tone'))->toBe('neutral')
            ->and(domClasses($root))->toContain('bg-neutral-bg');
    });

    it('marks a running status for the pulse, and only then', function () {
        expect(domQuery(renderBlade('<atom:status running label="Building" />'), '//*[@data-atom-status][@data-running]'))->toHaveCount(1)
            ->and(domQuery(renderBlade('<atom:status label="Live" />'), '//*[@data-atom-status][@data-running]'))->toHaveCount(0);
    });

    it('lets a slot replace the label', function () {
        $root = domQuery(renderBlade('<atom:status label="ignored"><b>Slot word</b></atom:status>'), '//*[@data-atom-status]')[0];

        expect(trim($root->textContent))->toBe('Slot word');
    });

    it('prints the label as text, not markup', function () {
        $html = renderBlade('<atom:status :label="$label" />', ['label' => '<img src=x onerror=alert(1)>']);

        expect(domQuery($html, '//*[@data-atom-status]//img'))->toBeEmpty()
            ->and($html)->toContain('&lt;img src=x onerror=alert(1)&gt;');
    });

    it('prints the bag', function () {
        $root = domQuery(renderBlade('<atom:status id="s" class="ms-2" label="x" x-show="ready" />'), '//*[@data-atom-status]')[0];

        expect($root->getAttribute('id'))->toBe('s')
            ->and($root->getAttribute('x-show'))->toBe('ready')
            ->and(domClasses($root))->toContain('ms-2');
    });

    it('pulses the dot only under no-preference motion, in atom.css', function () {
        $css = file_get_contents(dirname(__DIR__, 2).'/resources/css/atom.css');

        expect($css)->toMatch('/@media \(prefers-reduced-motion: no-preference\)\s*\{\s*\[data-atom-status\]\[data-running\] > \[data-atom-status-dot\]\s*\{\s*animation: atom-status-pulse/');
    });
});
