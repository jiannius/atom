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

/**
 * Every source that can emit a utility class, not just components/. Blade under
 * resources/views and PHP that builds markup by hand (GetOptions::getOptionHtml)
 * reach the browser exactly the same way, and scanning only components/ is how
 * a bare `text-muted` survived in both.
 *
 * @return array<string, string>
 */
function classSources(): array
{
    $sources = componentSources();

    foreach ([['resources/views', '*.blade.php'], ['src', '*.php']] as [$dir, $glob]) {
        foreach (Finder::create()->files()->in(__DIR__.'/../../'.$dir)->name($glob) as $file) {
            $sources[$dir.'/'.$file->getRelativePathname()] = $file->getContents();
        }
    }

    return $sources;
}

/**
 * Pull every `variant:variant:utility` token out of each class string, tagged with
 * the string it came from.
 *
 * Two passes, because a class list reaches the browser two ways: as a `class="..."`
 * attribute (including one built inside a PHP string, as GetOptions does — a plain
 * quoted-string scan yields the token `class="text-muted` there and silently matches
 * nothing), and as entries in an `@class([...])` / `Arr::toCssClasses([...])` array.
 *
 * The STRING is the grouping unit, not the line: two elements on one line must not
 * lend each other a dark variant neither has. The trade is that a pair split across
 * two array entries reads as unpaired — no current site does that, and it fails
 * loudly rather than silently if one ever does.
 *
 * @return list<array{line: int, group: int, variants: list<string>, utility: string}>
 */
function utilityTokens(string $contents): array
{
    $strings = [];

    // pass 1: class attributes, wherever they are embedded
    if (preg_match_all('/class=["\']([^"\'\n]*)["\']/', $contents, $m, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
        foreach ($m as $match) {
            $strings[] = [$match[1][0], $match[0][1]];
        }
    }

    // pass 2: bare quoted strings, for class arrays
    if (preg_match_all('/"([^"\n]*)"|\'([^\'\n]*)\'/', $contents, $m, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
        foreach ($m as $match) {
            // PCRE drops trailing unmatched groups, so group 2 is absent — not empty
            // — whenever the double-quoted alternative is the one that matched.
            $value = isset($match[2]) && $match[2][1] !== -1 ? $match[2][0] : $match[1][0];
            $strings[] = [$value, $match[0][1]];
        }
    }

    $tokens = [];

    foreach ($strings as [$string, $offset]) {
        $line = substr_count(substr($contents, 0, $offset), "\n") + 1;

        foreach (preg_split('/\s+/', $string, -1, PREG_SPLIT_NO_EMPTY) as $token) {
            // split on ":" that is not inside an arbitrary-variant bracket
            $parts = preg_split('/:(?![^\[]*\])/', $token);
            $utility = array_pop($parts);

            $tokens[] = ['line' => $line, 'group' => $offset, 'variants' => $parts, 'utility' => $utility];
        }
    }

    return $tokens;
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

    it('never puts the light-mode muted token in dark mode', function () {
        // `muted` is the LIGHT half of the pair and `muted-foreground` the dark half
        // — a consumer maps them zinc-500/zinc-400. tabs/item had them the wrong way
        // round for years, which put zinc-400 on a light strip (2.4:1) and zinc-500 on
        // a dark one (2.2:1). Both halves are "defined", both name a real token, and
        // the pair *looks* right at a glance, so nothing else here catches it.
        $offenders = [];

        foreach (classSources() as $path => $contents) {
            foreach (utilityTokens($contents) as $token) {
                if ($token['utility'] !== 'text-muted') {
                    continue;
                }

                if (in_array('dark', $token['variants'], true)) {
                    $offenders[] = $path.':'.$token['line'].' → '.implode(':', [...$token['variants'], $token['utility']]);
                }
            }
        }

        expect($offenders)->toBe([]);
    });

    it('pairs every resting text-muted with a dark-mode counterpart', function () {
        // --color-muted reads on a light ground and dies on a dark one: 3.67:1 on a
        // zinc-900 sidebar. The token is only half a colour. Fifteen sites shipped the
        // light half alone, four of them outside components/ — hence classSources().
        //
        // Only true STATE variants are exempt: `hover:text-muted` is a transient
        // colour, not the resting one. A layout or arbitrary variant (`md:`,
        // `[&_button]:`) IS the resting colour and still needs its dark half, which
        // is why the exemption is an allow-list rather than "has any prefix".
        //
        // Sites on a RAISED surface are absent by construction: muted misses AA on
        // zinc-100/zinc-600/zinc-700 even when paired, so those hard-code a zinc pair.
        $states = ['hover', 'focus', 'focus-visible', 'focus-within', 'active', 'disabled', 'group-hover', 'peer-hover'];
        $offenders = [];

        foreach (classSources() as $path => $contents) {
            $tokens = utilityTokens($contents);

            // the dark half may carry the same layout/arbitrary variant as the light
            // one (dark:[&_button]:text-muted-foreground), so match on the utility
            $hasDarkHalf = [];

            foreach ($tokens as $token) {
                if ($token['utility'] === 'text-muted-foreground' && in_array('dark', $token['variants'], true)) {
                    $hasDarkHalf[$token['group']] = true;
                }
            }

            foreach ($tokens as $token) {
                if ($token['utility'] !== 'text-muted') {
                    continue;
                }

                if (array_intersect($token['variants'], $states)) {
                    continue;
                }

                if (! isset($hasDarkHalf[$token['group']])) {
                    $offenders[] = $path.':'.$token['line'];
                }
            }
        }

        expect($offenders)->toBe([]);
    });
});
