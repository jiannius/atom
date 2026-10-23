<?php

use Illuminate\Support\Facades\Blade;

describe('empty', function () {
    it('sizes the default icon to size-14, not the icon size-5 fallback', function () {
        // Regression: the size class sat on the wrapper div, so icon._wrapper
        // fell back to its size-5 default and rendered a tiny glyph in a big box.
        $html = Blade::render('<atom:empty />');

        expect($html)
            ->toContain('data-atom-empty')
            ->toContain('No Results')
            ->toContain('We could not find anything.')
            ->toContain('size-14')
            ->not->toContain('size-5');
    });

    it('sizes the sm-variant icon to size-10', function () {
        $html = Blade::render('<atom:empty size="sm" />');

        expect($html)
            ->toContain('size-10')
            ->not->toContain('size-5');
    });

    it('renders the subtle variant as a single heading box', function () {
        $html = Blade::render('<atom:empty subtle heading="Nothing here" />');

        // the marker lets a caller's class and attributes land on a findable element
        expect($html)
            ->toContain('Nothing here')
            ->toContain('data-atom-empty')
            ->not->toContain('We could not find anything.');
    });

    it('has three kinds, each with its own icon, heading and next-step slot', function (string $kind, string $heading, string $path) {
        $html = renderBlade('<atom:empty kind="'.$kind.'"><x-slot:action><a id="next" href="/x">Next step</a></x-slot:action></atom:empty>');
        $root = domQuery($html, '//*[@data-atom-empty]')[0];

        expect($root->getAttribute('data-kind'))->toBe($kind)
            ->and($root->textContent)->toContain($heading)
            ->and(domQuery($html, '//*[@data-atom-empty-action]//a[@id="next"]'))->toHaveCount(1)
            ->and($html)->toContain($path);
    })->with([
        'first use' => ['first-use', 'Nothing here yet', 'M9.813 15.904'],
        'no results' => ['no-results', 'No Results', 'M2.25 13.5h3.86'],
        'error' => ['error', 'Something went wrong', 'M12 9v3.75'],
    ]);

    it('takes an unknown kind for no-results', function () {
        expect(domQuery(renderBlade('<atom:empty kind="bogus" />'), '//*[@data-atom-empty]')[0]->getAttribute('data-kind'))->toBe('no-results');
    });

    it('lets the caller override the kind icon, heading and subheading', function () {
        $html = renderBlade('<atom:empty kind="first-use" icon="search" heading="No jobs" subheading="Create one." />');

        expect($html)->toContain('No jobs')->toContain('Create one.')->toContain('m21 21-5.197')->not->toContain('Nothing here yet');
    });

    it('tints only the first-use icon with the accent', function () {
        $first = domQuery(renderBlade('<atom:empty kind="first-use" />'), '//*[@data-atom-empty-icon]')[0];
        $none = domQuery(renderBlade('<atom:empty kind="no-results" />'), '//*[@data-atom-empty-icon]')[0];
        $error = domQuery(renderBlade('<atom:empty kind="error" />'), '//*[@data-atom-empty-icon]')[0];

        expect(domClasses($first))->toContain('text-accent-ink')
            ->and(domClasses($none))->toContain('text-fg-subtle')->not->toContain('text-accent-ink')
            ->and(domClasses($error))->toContain('text-fg-subtle');
    });

    it('gives every shape of the large icon pathLength 1, for the draw-in', function () {
        $html = renderBlade('<atom:empty kind="first-use" />');
        $icon = domQuery($html, '//*[@data-atom-empty-icon]')[0];

        expect($icon->getElementsByTagName('path')->length)->toBeGreaterThan(0);

        foreach ($icon->getElementsByTagName('path') as $path) {
            expect($path->getAttribute('pathlength'))->toBe('1');
        }
    });

    it('draws a multi-shape lucide-style icon in whole, every circle and line included', function () {
        // `more` is three circles: none may be left without pathLength (it would sit dashed at 1px)
        $icon = domQuery(renderBlade('<atom:empty icon="more" />'), '//*[@data-atom-empty-icon]')[0];

        expect($icon->getElementsByTagName('circle')->length)->toBe(3);

        foreach ($icon->getElementsByTagName('circle') as $circle) {
            expect($circle->getAttribute('pathlength'))->toBe('1');
        }
    });

    it('never animates at sm or subtle: no draw-in marker', function (string $props) {
        $html = renderBlade('<atom:empty '.$props.' />');

        expect(domQuery($html, '//*[@data-atom-empty-icon]'))->toBeEmpty()
            ->and($html)->not->toContain('pathLength');
    })->with(['sm' => ['size="sm"'], 'subtle' => ['subtle']]);

    it('keeps the draw-in out of prefers-reduced-motion in atom.css', function () {
        $css = file_get_contents(dirname(__DIR__, 2).'/resources/css/atom.css');

        expect($css)->toMatch('/@media \(prefers-reduced-motion: no-preference\)\s*\{\s*\[data-atom-empty-icon\] :is\(path, circle, line, polyline, polygon, ellipse, rect\)\s*\{\s*animation: atom-draw 1s/')
            // the dash is dropped at the end of the animation
            ->and($css)->toMatch('/@keyframes atom-draw\s*\{[^@]*to\s*\{\s*stroke-dasharray: none;\s*stroke-dashoffset: 0;/')
            // and the rule exists once, so nothing outside the media query animates it
            ->and(substr_count($css, 'animation: atom-draw'))->toBe(1);
    });

    it('prints heading, subheading and the kind text as text, not markup', function () {
        $html = renderBlade('<atom:empty :heading="$e" :subheading="$e" />', ['e' => '<img src=x onerror=alert(1)>']);

        expect(domQuery($html, '//*[@data-atom-empty]//img'))->toBeEmpty()
            ->and(substr_count($html, '&lt;img src=x onerror=alert(1)&gt;'))->toBe(2);
    });

    it('lets a default slot replace the heading and subheading, with the action under it', function () {
        $html = renderBlade('<atom:empty><b id="own">Custom</b><x-slot:action><i id="act">go</i></x-slot:action></atom:empty>');

        expect(domQuery($html, '//*[@data-atom-empty]//b[@id="own"]'))->toHaveCount(1)
            ->and(domQuery($html, '//*[@data-atom-empty]//i[@id="act"]'))->toHaveCount(1)
            ->and($html)->not->toContain('We could not find anything.');
    });

    it('puts the next step in sm and subtle too', function (string $props) {
        $html = renderBlade('<atom:empty '.$props.'><x-slot:action><i id="act">go</i></x-slot:action></atom:empty>');

        expect(domQuery($html, '//*[@data-atom-empty]//i[@id="act"]'))->toHaveCount(1);
    })->with(['sm' => ['size="sm"'], 'subtle' => ['subtle']]);
});

describe('empty done', function () {
    it('is a cleared queue: a success-toned check, All caught up, and no action needed', function () {
        $html = renderBlade('<atom:empty kind="done" />');
        $root = domQuery($html, '//*[@data-atom-empty]')[0];

        expect($root->getAttribute('data-kind'))->toBe('done')
            ->and($root->textContent)->toContain('All caught up')->toContain('Nothing is waiting for you.')
            ->and(domClasses(domQuery($html, '//*[@data-atom-empty-icon]')[0]))->toContain('text-success-fg')
            ->and(domQuery($html, '//*[@data-atom-empty-action]'))->toBeEmpty()
            // drawn in like the others: every shape of the icon carries pathLength
            ->and($html)->toContain('pathLength="1"');
    });

    it('takes a heading, a subheading and an action like the other kinds', function () {
        $html = renderBlade('<atom:empty kind="done" heading="Inbox zero" subheading="Go and have a coffee."><x-slot:action><a id="next" href="/x">Review history</a></x-slot:action></atom:empty>');

        expect($html)->toContain('Inbox zero')->toContain('Go and have a coffee.')
            ->and(domQuery($html, '//*[@data-atom-empty-action]//a[@id="next"]'))->toHaveCount(1);
    });

    it('is success-toned in the small size too, and does not animate there', function () {
        $html = renderBlade('<atom:empty kind="done" size="sm" />');

        expect($html)->toContain('text-success-fg')->toContain('size-10')->not->toContain('data-atom-empty-icon')->not->toContain('pathLength');
    });

    it('is in the docs demo of the kinds', function () {
        expect(view('atom::docs.demos.empty.kinds')->render())->toContain('data-kind="done"');
    });
});
