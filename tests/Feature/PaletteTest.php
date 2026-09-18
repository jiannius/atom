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

    it('only names semantic colour tokens that a consumer actually defines', function () {
        // The package ships no @theme, so its semantic tokens are a contract with
        // the consuming app: it defines --color-muted and --color-muted-foreground,
        // and those are the only two. `text-muted-more` was written at two sites and
        // matched nothing — Tailwind drops an undefined token silently, so both
        // elements simply inherited their parent's colour for as long as they existed.
        // The step check above cannot see this: `muted-more` names no numeric step.
        $defined = ['muted', 'muted-foreground'];
        $utilities = 'text|bg|border|divide|ring|outline|from|via|to|fill|stroke|accent|caret|decoration|placeholder';
        $offenders = [];

        foreach (componentSources() as $path => $contents) {
            foreach (explode("\n", $contents) as $i => $line) {
                preg_match_all('/\b(?:'.$utilities.')-(muted[a-z-]*)/', $line, $matches);

                foreach ($matches[1] as $token) {
                    if (! in_array($token, $defined, true)) {
                        $offenders[] = $path.':'.($i + 1).' → '.$token;
                    }
                }
            }
        }

        expect($offenders)->toBe([]);
    });

    it('pairs every unprefixed text-muted with a dark-mode counterpart', function () {
        // A consumer maps --color-muted to a mid step (zinc-500) that reads on a
        // light ground and dies on a dark one — 3.67:1 on a zinc-900 sidebar. The
        // token is only half a colour; the other half is `dark:text-muted-foreground`.
        // Fifteen components shipped the light half alone. Variant-prefixed uses
        // (hover:text-muted) are states, not the resting colour, so they are exempt.
        //
        // Sites on a RAISED surface are absent from this rule by construction: muted
        // is tuned to the page ground and misses AA on zinc-100/zinc-600/zinc-700
        // even when paired, so those hard-code a zinc pair and never match here.
        $offenders = [];

        foreach (componentSources() as $path => $contents) {
            foreach (explode("\n", $contents) as $i => $line) {
                // unprefixed `text-muted`, not the `-foreground` token itself
                if (! preg_match('/(?<![-:\w])text-muted\b(?!-)/', $line)) {
                    continue;
                }

                if (! str_contains($line, 'dark:text-muted-foreground')) {
                    $offenders[] = $path.':'.($i + 1);
                }
            }
        }

        expect($offenders)->toBe([]);
    });
});
