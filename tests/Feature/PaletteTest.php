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

/**
 * The same tokens as utilityTokens(), but grouped by ELEMENT rather than by string.
 *
 * A component writes one element's classes as an `@class([...])` /
 * `Arr::toCssClasses([...])` array whose entries are separate strings — card puts
 * `border` on one line and `border-zinc-200` five lines below it. Grouping by
 * string reads that as an uncoloured border; grouping by the enclosing array
 * literal reads it as the pair it is. A class attribute outside such an array is
 * still its own group.
 *
 * @return list<array{line: int, group: int|string, variants: list<string>, utility: string}>
 */
function elementTokens(string $contents): array
{
    $ranges = [];

    if (preg_match_all('/(?:Arr::toCssClasses|@class|->class)\s*\(\s*\[/', $contents, $matches, PREG_OFFSET_CAPTURE)) {
        foreach ($matches[0] as [$literal, $start]) {
            $depth = 0;

            for ($i = $start + strlen($literal) - 1; $i < strlen($contents); $i++) {
                if ($contents[$i] === '[') {
                    $depth++;
                } elseif ($contents[$i] === ']') {
                    $depth--;

                    if ($depth === 0) {
                        $ranges[] = [$start, $i];
                        break;
                    }
                }
            }
        }
    }

    $tokens = utilityTokens($contents);

    foreach ($tokens as $index => $token) {
        foreach ($ranges as [$start, $end]) {
            if ($token['group'] > $start && $token['group'] < $end) {
                $tokens[$index]['group'] = 'array:'.$start;
                break;
            }
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

        // the tokenizer makes two passes (class attributes, then quoted strings),
        // so one token can surface twice — dedupe for a readable failure
        expect(array_values(array_unique($offenders)))->toBe([]);
    });

    it('never leaves the dark-mode muted token running in light mode', function () {
        // The other half of the contract. `muted-foreground` means ONE thing — the
        // dark half — so a resting use of it with no `dark:` applies in light mode
        // too, where a consumer's zinc-400 is 2.63:1 on white: below AA for text and
        // below even the 3:1 graphical floor. It was standalone at ~20 sites, which
        // is what made the token look like it needed splitting in two; pairing them
        // resolves it with no new token for consumers to define.
        $states = ['hover', 'focus', 'focus-visible', 'focus-within', 'active', 'disabled', 'group-hover', 'peer-hover'];
        $offenders = [];

        foreach (classSources() as $path => $contents) {
            foreach (utilityTokens($contents) as $token) {
                if ($token['utility'] !== 'text-muted-foreground') {
                    continue;
                }

                if (in_array('dark', $token['variants'], true) || array_intersect($token['variants'], $states)) {
                    continue;
                }

                $offenders[] = $path.':'.$token['line'];
            }
        }

        // the tokenizer makes two passes (class attributes, then quoted strings),
        // so one token can surface twice — dedupe for a readable failure
        expect(array_values(array_unique($offenders)))->toBe([]);
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

        // the tokenizer makes two passes (class attributes, then quoted strings),
        // so one token can surface twice — dedupe for a readable failure
        expect(array_values(array_unique($offenders)))->toBe([]);
    });
    it('gives every light-mode border and divider a light-mode colour', function () {
        // The divide-y check above, generalised to the whole border/divide family.
        // A `border-t` whose only colour is `dark:border-zinc-700` draws its
        // light-mode line in the TEXT colour — near-black on a white card. Dark mode
        // is correct, which is why nine components shipped it, and why two consuming
        // apps then copied the shape across ~30 sites of their own.
        //
        // The flagged shape is the asymmetric one: a border that EXISTS in light mode
        // (its width utility carries no `dark:`), a dark colour, and no light colour.
        // A width that is itself dark-only draws nothing in light mode and needs no
        // colour — docs/example and tooltip/content both do that deliberately.
        //
        // "Light" means any variant that is not `dark`, not "no variant at all": the
        // `(?<![-:\w])` shape the divide-y check uses would reject a good
        // `md:divide-zinc-200`.
        //
        // Colours are matched by an allow-list, never by "border-<word>". `border`
        // also prefixes styles (`border-dashed`) and two CSS property names that
        // appear in inline-style strings (`border-color`, `border-radius`) — counting
        // any of those as a colour would silently satisfy the light half.
        $palette = 'zinc|gray|slate|neutral|stone|red|orange|amber|yellow|lime|green|emerald|teal|cyan|sky|blue|indigo|violet|purple|fuchsia|pink|rose';
        $named = 'white|black|transparent|current|inherit|muted|muted-foreground';

        $widths = [
            'border' => '/^border(?:-[trblxyse])?(?:-\d+)?$/',
            'divide' => '/^divide-[xy](?:-\d+)?$/',
        ];

        $colours = [
            'border' => '/^border(?:-[trblxyse])?-(?:(?:'.$palette.')-\d+|'.$named.')(?:\/\d+)?$|^border(?:-[trblxyse])?-\[/',
            'divide' => '/^divide(?:-[xy])?-(?:(?:'.$palette.')-\d+|'.$named.')(?:\/\d+)?$|^divide(?:-[xy])?-\[/',
        ];

        $offenders = [];

        foreach (classSources() as $path => $contents) {
            $elements = [];

            foreach (elementTokens($contents) as $token) {
                $isDark = in_array('dark', $token['variants'], true);

                foreach ($widths as $property => $width) {
                    if (preg_match($width, $token['utility'])) {
                        // border-0 / border-y-0 draw no line, so they need no colour
                        if (! $isDark && ! str_ends_with($token['utility'], '-0')) {
                            $elements[$token['group']][$property]['width'] ??= $token['line'];
                        }
                    } elseif (preg_match($colours[$property], $token['utility'])) {
                        $elements[$token['group']][$property][$isDark ? 'dark' : 'light'] = $token['line'];
                    }
                }
            }

            foreach ($elements as $element) {
                foreach ($element as $property => $seen) {
                    if (isset($seen['width'], $seen['dark']) && ! isset($seen['light'])) {
                        $offenders[] = $path.':'.$seen['width'].' → '.$property;
                    }
                }
            }
        }

        // the tokenizer makes two passes (class attributes, then quoted strings),
        // so one element can surface twice — dedupe for a readable failure
        expect(array_values(array_unique($offenders)))->toBe([]);
    });
});
