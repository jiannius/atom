<?php

/*
 * Static rules for the components restyled onto the v4 tokens (V4-3). Each batch adds its components to
 * restyledComponents(); a component not in the list is not held to the rules yet (its v3 classes are still the
 * `zinc` ramp, which is atom's warm neutral palette now, so it is not broken, only not in the new design).
 *
 * The rules, for every component in the list:
 *  - no raw palette name (`zinc-*`, `red-*`, `white`, `black`...), and none of the v3 aliases (`muted`, `muted-foreground`,
 *    `primary`, `primary-foreground`): a role token, which flips with the theme on its own;
 *  - so no `dark:` colour pair either: a token has one name for both themes;
 *  - no `shadow-*`: controls and in-page parts are drawn with a fill and a hairline (a raised layer is not in the list);
 *  - no `focus:outline-*` or `focus:ring-*`: the ring is `:focus-visible`, atom's one base rule (a mouse click on a
 *    button must not draw it);
 *  - the v3.27.6 currentColor guard, adapted to tokens: Tailwind 4 draws a border in `currentColor`, so a border colour
 *    that exists in one theme only draws the text colour in the other. With no `dark:` colour at all in these
 *    components that cannot happen, so the rule is that every border and divider colour is a token (or transparent), and
 *    that a divider names its colour (it has no base rule to fall back on).
 */

use Symfony\Component\Finder\Finder;

require_once __DIR__.'/../Support/ClassTokens.php';

/**
 * The components held to the rules, as paths under components/ (a directory is every file under it).
 *
 * @return list<string>
 */
function restyledComponents(): array
{
    return [
        // V4-3a, the controls
        'button',
        'input',
        'select',
        'textarea',
        'date-picker',
        'time-picker',
    ];
}

/**
 * Each utility class the restyled components can emit, with the file and line it came from, by element.
 *
 * @return array<string, list<array{line: int, group: int|string, variants: list<string>, utility: string}>>
 */
function restyledTokens(): array
{
    $out = [];

    foreach (restyledComponents() as $entry) {
        $path = __DIR__.'/../../components/'.$entry;
        $files = is_dir($path)
            ? iterator_to_array(Finder::create()->files()->in($path)->name('*.blade.php'), false)
            : [new SplFileInfo($path.'.blade.php')];

        foreach ($files as $file) {
            $relative = 'components/'.ltrim(str_replace(realpath(__DIR__.'/../../components'), '', $file->getRealPath()), '/');
            $out[$relative] = elementTokens(file_get_contents($file->getRealPath()));
        }
    }

    return $out;
}

/**
 * The offenders of one rule, as `path:line → class`, once each.
 *
 * @param  callable(array{variants: list<string>, utility: string}): bool  $matches
 * @param  array<string, list<string>>  $allow  utilities a file may keep, by path
 * @return list<string>
 */
function restyleOffenders(callable $matches, array $allow = []): array
{
    $offenders = [];

    foreach (restyledTokens() as $path => $tokens) {
        foreach ($tokens as $token) {
            if (in_array($token['utility'], $allow[$path] ?? [], true)) {
                continue;
            }

            if ($matches($token)) {
                $offenders[] = $path.':'.$token['line'].' → '.implode(':', [...$token['variants'], $token['utility']]);
            }
        }
    }

    return array_values(array_unique($offenders));
}

const RESTYLE_PALETTE = 'zinc|gray|slate|neutral|stone|red|orange|amber|yellow|lime|green|emerald|teal|cyan|sky|blue|indigo|violet|purple|fuchsia|pink|rose';
const RESTYLE_COLOUR_UTILITIES = 'text|bg|border|divide|ring|ring-offset|outline|from|via|to|fill|stroke|accent|caret|decoration|placeholder|shadow|inset-shadow';

describe('restyled components', function () {
    it('has components to check, and every one of them exists', function () {
        expect(restyledComponents())->not->toBeEmpty();
        expect(restyledTokens())->not->toBeEmpty();

        foreach (restyledComponents() as $entry) {
            expect(file_exists(__DIR__.'/../../components/'.$entry.'.blade.php') || is_dir(__DIR__.'/../../components/'.$entry))->toBeTrue($entry);
        }
    });

    it('names no raw palette colour and no v3 alias: a role token instead', function () {
        // `dark:`-less white/black are raw too (`bg-white` is not the surface in a dark host). The aliases `muted`,
        // `muted-foreground`, `primary` and `primary-foreground` stay for host markup written before the roles; the
        // components do not use them.
        $allow = [];

        $offenders = restyleOffenders(fn ($token) => (bool) preg_match(
            '/^(?:'.RESTYLE_COLOUR_UTILITIES.')-(?:(?:'.RESTYLE_PALETTE.')-\d+|white|black|muted|muted-foreground|primary|primary-foreground)(?:\/[\d.]+)?$/',
            $token['utility'],
        ), $allow);

        expect($offenders)->toBe([]);
    });

    it('has no dark: colour pair: a role token is one name for both themes', function () {
        // `dark:invert` and the like are not colours. A colour with a dark: variant is a pair somebody has to keep in step,
        // and a dark-only border colour is the v3.27.6 defect (the light theme draws the text colour).
        $allow = [];

        $offenders = restyleOffenders(fn ($token) => in_array('dark', $token['variants'], true)
            && preg_match('/^(?:'.RESTYLE_COLOUR_UTILITIES.')-/', $token['utility']) === 1, $allow);

        expect($offenders)->toBe([]);
    });

    it('draws no shadow', function () {
        $offenders = restyleOffenders(fn ($token) => preg_match('/^(?:inset-|drop-)?shadow(?:-(?!none$).+)?$/', $token['utility']) === 1);

        expect($offenders)->toBe([]);
    });

    it('rings a control on :focus-visible only, never :focus', function () {
        $offenders = restyleOffenders(fn ($token) => in_array('focus', $token['variants'], true)
            && preg_match('/^(?:outline|ring)(?:-|$)/', $token['utility']) === 1);

        expect($offenders)->toBe([]);
    });

    it('gives every border and divider colour a token, and a divider a colour of its own', function () {
        $token = 'border|border-strong|fg|fg-muted|fg-subtle|focus|surface|canvas|accent|accent-ink|accent-soft|accent-hover|accent-content|neutral-bg|neutral-fg|info-bg|info-fg|success-bg|success-fg|warning-bg|warning-fg|danger-bg|danger-fg|transparent|current|inherit';
        $offenders = [];

        foreach (restyledTokens() as $path => $tokens) {
            $elements = [];

            foreach ($tokens as $t) {
                if (preg_match('/^(border(?:-[trblxyse])?|divide(?:-[xy])?)-(.+)$/', $t['utility'], $m) !== 1) {
                    continue;
                }

                // a width, a style, or a colour: only a colour is checked against the token list
                $rest = $m[2];

                if (preg_match('/^(?:[trblxyse](?:-\d+)?|\d+|\[[\d.]+(?:px|rem|em)?\]|solid|dashed|dotted|double|none|hidden|collapse|separate|spacing.*|reverse)$/', $rest)) {
                    continue;
                }

                if (preg_match('/^(?:'.$token.')(?:\/[\d.]+)?$/', $rest) !== 1) {
                    $offenders[] = $path.':'.$t['line'].' → '.$t['utility'];
                }
            }

            foreach ($tokens as $t) {
                $isWidth = preg_match('/^divide-[xy](?:-\d+)?$/', $t['utility']) === 1;
                $isColour = preg_match('/^divide(?:-[xy])?-(?:'.$token.')(?:\/[\d.]+)?$/', $t['utility']) === 1;

                if ($isWidth && ! str_ends_with($t['utility'], '-0')) {
                    $elements[$t['group']]['width'] ??= $t['line'];
                }

                if ($isColour) {
                    $elements[$t['group']]['colour'] = true;
                }
            }

            foreach ($elements as $seen) {
                if (isset($seen['width']) && ! isset($seen['colour'])) {
                    $offenders[] = $path.':'.$seen['width'].' → a divider with no colour';
                }
            }
        }

        expect(array_values(array_unique($offenders)))->toBe([]);
    });
});
