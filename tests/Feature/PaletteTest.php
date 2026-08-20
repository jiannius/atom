<?php

use Symfony\Component\Finder\Finder;

/**
 * Collect every .blade.php under components/, keyed by repo-relative path.
 *
 * @return array<string, string>
 */
function componentSources(): array
{
    $sources = [];

    foreach (Finder::create()->files()->in(__DIR__.'/../../components')->name('*.blade.php') as $file) {
        $sources['components/'.$file->getRelativePathname()] = $file->getContents();
    }

    return $sources;
}

// Tailwind v4 defaults border-color to currentColor (v3 used gray-200), and it
// drops an undefined colour token rather than erroring. So a `divide-y` with no
// light-mode colour — or one naming a token the package never defines — draws
// its dividers in the text colour instead. Dark mode looks right either way,
// which is how this keeps slipping through review.
describe('palette', function () {
    it('gives every divide-y a light-mode colour', function () {
        $offenders = [];

        foreach (componentSources() as $path => $contents) {
            foreach (explode("\n", $contents) as $i => $line) {
                if (! str_contains($line, 'divide-y')) {
                    continue;
                }

                // a colour utility not prefixed by a variant (dark:, hover:, ...)
                if (! preg_match('/(?<![-:\w])divide-[a-z]+-\d+/', $line)) {
                    $offenders[] = $path.':'.($i + 1);
                }
            }
        }

        expect($offenders)->toBe([]);
    });

    it('only names colour steps that exist on the tailwind scale', function () {
        // Tailwind's default palette. The package ships no @theme of its own,
        // so a step off this scale (divide-zinc-150) compiles away to nothing.
        $steps = [50, 100, 200, 300, 400, 500, 600, 700, 800, 900, 950];
        $utilities = 'text|bg|border|divide|ring|outline|from|via|to|fill|stroke|accent|caret|decoration|placeholder';
        $palette = 'zinc|gray|slate|neutral|stone|red|orange|amber|yellow|lime|green|emerald|teal|cyan|sky|blue|indigo|violet|purple|fuchsia|pink|rose';
        $offenders = [];

        foreach (componentSources() as $path => $contents) {
            foreach (explode("\n", $contents) as $i => $line) {
                preg_match_all('/\b(?:'.$utilities.')-(?:'.$palette.')-(\d+)\b/', $line, $matches);

                foreach ($matches[1] as $index => $step) {
                    if (! in_array((int) $step, $steps, true)) {
                        $offenders[] = $path.':'.($i + 1).' → '.$matches[0][$index];
                    }
                }
            }
        }

        expect($offenders)->toBe([]);
    });
});
