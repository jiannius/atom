<?php

use Symfony\Component\Finder\Finder;

/**
 * `x-cloak` is only a directive to Alpine: it strips the attribute once it
 * initialises an element's tree, but hiding the element before that is entirely
 * the stylesheet's job. Tailwind ships no `[x-cloak]` rule — apps are expected
 * to write one — so until atom.css carried it, every x-cloak in this package was
 * inert in any consumer that hadn't written it themselves.
 *
 * Two things have to stay true, and neither is visible from reading a component.
 */
describe('x-cloak', function () {
    it('is actually defined in atom.css, and with !important', function () {
        $css = file_get_contents(__DIR__.'/../../resources/css/atom.css');

        expect($css)->toMatch('/\[x-cloak\]\s*\{[^}]*display:\s*none\s*!important/');
    });

    it('only ever sits on an element Alpine will initialise', function () {
        // An x-cloak with no x-data on it or above it is never stripped, so with
        // the rule in place that element is hidden forever rather than briefly.
        // Adding the CSS turned a dormant no-op into a real failure mode; this is
        // the guard for it.
        $offenders = [];

        foreach (Finder::create()->files()->in(__DIR__.'/../../components')->name('*.blade.php') as $file) {
            $contents = $file->getContents();

            if (!str_contains($contents, 'x-cloak')) {
                continue;
            }

            // the whole file must establish an Alpine scope somewhere at or above
            // every x-cloak it contains — components are small enough that file
            // scope is the right granularity here
            if (!str_contains($contents, 'x-data')) {
                $offenders[] = 'components/'.$file->getRelativePathname();
            }
        }

        expect($offenders)->toBe([]);
    });
});
