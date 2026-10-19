<?php

use Symfony\Component\Finder\Finder;

/*
 * Reads the utility classes out of atom's own markup: PaletteTest and RestyleGuardsTest both hold it to rules.
 */

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

            // Brackets inside a string literal are not structure: an unbalanced one
            // (`content-['[']`, a stray bracket in an arbitrary variant) would
            // otherwise carry the range past the array's real end and silently lend
            // unrelated elements each other's colours.
            $quote = null;

            for ($i = $start + strlen($literal) - 1, $length = strlen($contents); $i < $length; $i++) {
                $char = $contents[$i];

                if ($quote !== null) {
                    if ($char === '\\') {
                        $i++;
                    } elseif ($char === $quote) {
                        $quote = null;
                    }

                    continue;
                }

                if ($char === '"' || $char === "'") {
                    $quote = $char;
                } elseif ($char === '[') {
                    $depth++;
                } elseif ($char === ']') {
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
