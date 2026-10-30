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
        'checkbox',
        'radio',
        'toggle',
        'slider',
        'rating',
        'uploader',
        // V4-3b, the data display
        'badge',
        'navlist/badge',
        'card',
        'callout',
        'dd',
        'list',
        'avatar',
        'profile',
        'progress',
        'skeleton',
        'placeholder-bar',
        'kbd',
        'pagination',
        'table',
        // V4-3c, the overlays and the navigation
        'modal',
        'dropdown',
        'menu',
        'tooltip',
        'toast',
        'alert',
        'confirm',
        'command',
        'context-menu',
        'lightbox',
        'tabs',
        'breadcrumbs',
        'accordion',
        // V4-3d, the text, the editor, the calendar, the chart and the docs chrome
        'heading',
        'subheading',
        'caption',
        'label',
        'error',
        'link',
        'separator',
        'sharer',
        'copy',
        'dev-login',
        'embed',
        'whatsapp',
        'tiptap',
        'calendar',
        'chart',
        'docs',
    ];
}

/**
 * The raised layers among them, and so the only components that may cast a shadow: the dialogs, the panels a trigger opens, the
 * toast and the palette, a floating button, the editor's bubble menus and mention list, and the calendar's lifted item and day list. Tabs, breadcrumbs, accordion and the tooltip (a label, not a panel) are drawn with a fill and a hairline.
 * Even a raised layer has one shadow, the `shadow-pop` token, never a size from Tailwind's scale.
 *
 * @return list<string>
 */
function raisedLayers(): array
{
    return [
        'modal', 'menu', 'toast', 'alert', 'confirm', 'command', 'lightbox',
        // V4-3d: the floating whatsapp button, the editor's bubble menus and mention list, and the calendar's lifted item and day list
        'whatsapp', 'tiptap/menu', 'tiptap/mention', 'calendar',
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

    it('draws no shadow, except the shadow-pop token on a raised layer', function () {
        $isShadow = fn ($token) => preg_match('/^(?:inset-|drop-)?shadow(?:-(?!none$).+)?$/', $token['utility']) === 1;

        // a raised layer's file may name `shadow-pop` and nothing else
        $allow = [];
        foreach (raisedLayers() as $entry) {
            foreach (restyledTokens() as $path => $_) {
                if (str_starts_with($path, 'components/'.$entry.'/') || $path === 'components/'.$entry.'.blade.php') {
                    $allow[$path] = ['shadow-pop'];
                }
            }
        }

        expect(restyleOffenders($isShadow, $allow))->toBe([]);
    });

    it('lets the raised layers cast the shadow token, and the others nothing', function () {
        foreach (['modal/index', 'menu/index', 'toast/index', 'command/index', 'lightbox'] as $file) {
            expect(file_get_contents(__DIR__.'/../../components/'.$file.'.blade.php'))->toContain('shadow-pop');
        }

        foreach (['tooltip/content', 'tabs/index', 'tabs/item', 'breadcrumbs', 'accordion/index', 'accordion/item', 'tiptap/index', 'tiptap/chat', 'tiptap/toolbar/button'] as $file) {
            expect(file_get_contents(__DIR__.'/../../components/'.$file.'.blade.php'))->not->toMatch('/\bshadow-/');
        }

        foreach (['whatsapp', 'tiptap/menu/link', 'tiptap/menu/table', 'tiptap/mention', 'calendar/index'] as $file) {
            expect(file_get_contents(__DIR__.'/../../components/'.$file.'.blade.php'))->toContain('shadow-pop');
        }
    });

    it('keeps the calendar itself a fill and a hairline: only its lifted item and its day list are raised', function () {
        $root = collect(explode("\n", file_get_contents(__DIR__.'/../../components/calendar/index.blade.php')))
            ->first(fn ($line) => str_contains($line, 'relative min-w-0 overflow-hidden'));

        expect($root)->toContain('rounded-panel')->toContain('border-border')->not->toMatch('/\bshadow-/');
    });

    it('rings a control on :focus-visible only, never :focus', function () {
        // Four shapes, each of which draws on a mouse click: `focus:outline-*` / `focus:ring-*`, the same behind a
        // group, peer or has variant (`group-focus:`, `peer-focus:`, `has-focus:`), a `:focus` inside an arbitrary variant
        // (`has-[:focus]:`, `group-has-[:focus]:`, `[&:focus]:`) with any utility, and `focus-within:` with an outline or ring.
        // `:focus-visible` is the one that belongs to a keyboard, and `:focus-visible` inside a bracket is not matched.
        $offenders = restyleOffenders(function ($token) {
            $isRing = preg_match('/^(?:outline|ring)(?:-|$)/', $token['utility']) === 1;

            foreach ($token['variants'] as $variant) {
                if (str_contains($variant, '[') && preg_match('/:focus(?!-visible)(?!\w)/', $variant)) {
                    return true;
                }

                if ($isRing && preg_match('/(?:^|-)(?:focus|focus-within)$/', $variant)) {
                    return true;
                }
            }

            return false;
        });

        expect($offenders)->toBe([]);
    });

    it('gives every border and divider colour a token, and a divider a colour of its own', function () {
        $token = 'border|border-strong|control-border|fg|fg-muted|fg-subtle|focus|surface|canvas|accent|accent-ink|accent-soft|accent-hover|accent-content|neutral-bg|neutral-fg|info-bg|info-fg|success-bg|success-fg|warning-bg|warning-fg|danger-bg|danger-fg|transparent|current|inherit';
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

describe('structural CSS of the restyled controls', function () {
    /**
     * The rules of atom.css whose selector names one of the given hooks, one string per rule, with comments removed.
     *
     * @param  list<string>  $hooks
     * @return list<string>
     */
    function cssRulesFor(array $hooks): array
    {
        $css = preg_replace('#/\*.*?\*/#s', '', file_get_contents(__DIR__.'/../../resources/css/atom.css'));
        preg_match_all('/([^{}]+)\{([^{}]*)\}/', $css, $matches, PREG_SET_ORDER);

        $rules = [];

        foreach ($matches as [$whole, $selector]) {
            foreach ($hooks as $hook) {
                if (str_contains($selector, $hook)) {
                    $rules[] = trim($whole);
                    break;
                }
            }
        }

        return $rules;
    }

    it('draws the slider and the rating with role tokens: no hex or rgb colour, no shadow, no html.dark pair', function () {
        $rules = cssRulesFor(['data-atom-slider', 'data-atom-rating']);

        expect($rules)->not->toBeEmpty();

        foreach ($rules as $rule) {
            expect($rule)->not->toMatch('/#[0-9a-fA-F]{3,8}\b|rgba?\(|:\s*(?:white|black)\s*[;}]|box-shadow:\s*(?!none)|html\.dark/');
        }
    });

    it('reads the fill, the track and the ring of the slider and the rating from the tokens', function () {
        $css = implode("\n", cssRulesFor(['data-atom-slider', 'data-atom-rating']));

        expect($css)
            ->toContain('var(--atom-accent-ink)')
            ->toContain('var(--atom-border-strong)')
            ->toContain('var(--atom-surface)')
            ->toContain('var(--atom-focus)')
            ->toContain('var(--atom-fg-subtle)');
    });

    it('keeps the slider\'s geometry: a 4px track and a 16px thumb with a 2px border', function () {
        $css = implode("\n", cssRulesFor(['data-atom-slider-input']));

        expect($css)
            ->toMatch('/\[data-atom-slider-input\]\s*\{[^}]*height:\s*4px/')
            ->toMatch('/::-webkit-slider-thumb\s*\{[^}]*width:\s*16px;[^}]*height:\s*16px;[^}]*border:\s*2px solid currentColor/');
    });
});

describe('review round 1', function () {
    beforeEach(fn () => view()->share('errors', new \Illuminate\Support\ViewErrorBag));

    it('keeps the danger border on an invalid field that has the focus: the outline carries the focus', function (string $tag) {
        $html = renderBlade($tag);

        expect($html)
            ->toMatch('/(?:has-\[:focus-visible\]|focus-visible):border-danger-fg/')
            ->not->toMatch('/(?:has-\[:focus-visible\]|focus-visible):border-focus/')
            // an error from the field's wrapper keeps it too, whichever of the two ways the field is focused
            ->toMatch('/group-has-\[\[data-atom-error\]\]\/field:(?:has-\[:focus-visible\]|focus-visible):border-danger-fg/');
    })->with([
        'input' => '<atom:input label="x" invalid />',
        'textarea' => '<atom:textarea label="x" invalid />',
        'tel' => '<atom:input label="x" type="tel" invalid />',
        'colour' => '<atom:input label="x" type="color" invalid />',
        'email' => '<atom:input label="x" type="email" multiple invalid />',
        'otp' => '<atom:input.otp length="4" invalid />',
        'date' => '<atom:date-picker label="x" invalid />',
        'range' => '<atom:date-picker variant="range" label="x" invalid />',
        'time' => '<atom:time-picker label="x" invalid />',
        'native select' => '<atom:select label="x" :options="[]" invalid />',
        'listbox' => '<atom:select label="x" variant="listbox" :options="[]" invalid />',
    ]);

    it('gives a valid field the focus colour on its border', function () {
        expect(renderBlade('<atom:input label="x" />'))
            ->toContain('focus-visible:border-focus')
            ->not->toContain(' focus-visible:border-danger-fg');
    });

    it('toggles the dropzone border by what is dragged over it, so the two colours never compete', function () {
        $html = renderBlade('<atom:uploader.dropzone />');

        expect($html)
            ->toContain("'border-accent-ink bg-accent-soft': dragging")
            ->toContain("'border-control-border': !dragging")
            // the static class list no longer carries the resting colour, which compiled after the accent and always won
            ->not->toMatch('/\sclass="[^"]*\bborder-control-border\b/');
    });

    it('sizes the pikaday arrows with a class Tailwind 4.0.7 compiles', function () {
        $html = renderBlade('<atom:date-picker.calendar />');

        expect($html)->toContain('pika-next]:bg-[length:16px]')
            ->and($html)->not->toContain('bg-size-');
    });

    it('prints the check mark svg with one class attribute', function () {
        $html = renderBlade('<atom:checkbox label="x" />');

        preg_match('/<svg[^>]*>/', $html, $svg);

        expect(substr_count($svg[0], 'class='))->toBe(1);
    });
});

describe('V4-3d text, editor and chart', function () {
    it('draws the editor content with role tokens: no hex or rgb colour and no named colour, in tiptap.css', function () {
        $css = preg_replace('#/\*.*?\*/#s', '', file_get_contents(__DIR__.'/../../resources/css/tiptap.css'));

        // a colour is a var(--atom-*): any literal one is a pair somebody has to keep in step with the dark theme
        expect($css)->not->toMatch('/#[0-9a-fA-F]{3,8}\b|rgba?\(|hsla?\(|oklch\(|:\s*(?:white|black|gray|grey|red|blue|green|sky)\s*[;}]/');
        expect($css)->not->toMatch('/box-shadow:\s*(?!none)/');
        expect($css)->not->toContain('.dark');
    });

    it('reads the editor content from the reading size and the accent ink', function () {
        $css = file_get_contents(__DIR__.'/../../resources/css/tiptap.css');

        expect($css)
            ->toContain('font-size: var(--atom-text-base);')
            ->toMatch('/\.editor-content a \{[^}]*color: var\(--atom-accent-ink\)/')
            ->toMatch('/\.editor-content \.mention \{[^}]*var\(--atom-info-bg\)[^}]*var\(--atom-info-fg\)/s');
    });

    it('draws a link in the accent ink, whatever the variant', function (string $tag) {
        expect(renderBlade($tag))->toContain('text-accent-ink')->not->toMatch('/\btext-(?:sky|accent|zinc)(?![\w-])/');
    })->with([
        'default' => '<atom:link href="/x">x</atom:link>',
        'accent variant' => '<atom:link href="/x" variant="accent">x</atom:link>',
        'with an icon' => '<atom:link href="/x" icon="link">x</atom:link>',
    ]);

    it('maps the stats card onto tones, not colour names', function () {
        foreach ([[5, 'success'], [-5, 'danger'], [0, 'neutral']] as [$indicator, $tone]) {
            $html = renderBlade('<atom:card variant="stats" heading="Sales" data="12" :indicator="$indicator" :trend="[1, 2, 3]" />', ['indicator' => $indicator]);

            expect($html)->toContain("color: '".$tone."',");
        }
    });

    it('takes the theme listener off the document when a chart goes, and reads the tokens from the chart\'s own element', function () {
        foreach (['bar', 'area', 'trend'] as $name) {
            $js = file_get_contents(__DIR__.'/../../resources/js/alpinejs/chart/'.$name.'.js');

            expect($js, $name)->toContain('destroy ()')->toContain("removeEventListener('darkmode-changed'")->toContain('isConnected')->toContain('this.$el');
        }

        expect(file_get_contents(__DIR__.'/../../resources/js/alpinejs/chart/colors.js'))->toContain('el.append(probe)')->not->toContain('documentElement.append')->toContain('var(${name}, ${FALLBACK[name]');
    });

    it('draws the chart with the tokens: no palette hex left in the factories', function () {
        foreach (glob(__DIR__.'/../../resources/js/alpinejs/chart/*.js') as $file) {
            $js = file_get_contents($file);

            // colors.js alone holds hex: the greys a token falls back to on a page that does not define it (its FALLBACK table)
            if (basename($file) === 'colors.js') {
                $js = preg_replace('/const FALLBACK = \{.*?\n\}/s', '', $js);
            }

            expect($js)->not->toMatch('/#[0-9a-fA-F]{6}\b|\'(?:black|white)\'|bg-zinc|shadow-lg/', basename($file));
        }
    });
});

describe('every component', function () {
    it('names no raw palette colour, no v3 alias and no dark: colour pair, whatever list it is on (V4-3d: nothing is left to restyle)', function () {
        // a scrim over the page is not a role: black at 20% is a dimmer in either theme (the shells' mobile nav)
        $allow = ['components/layouts/sidebar.blade.php' => ['bg-black/20']];
        $offenders = [];

        foreach (componentSources() as $path => $contents) {
            foreach (elementTokens($contents) as $token) {
                if (in_array($token['utility'], $allow[$path] ?? [], true)) {
                    continue;
                }

                $raw = preg_match('/^(?:'.RESTYLE_COLOUR_UTILITIES.')-(?:(?:'.RESTYLE_PALETTE.')-\d+|white|black|muted|muted-foreground|primary|primary-foreground)(?:\/[\d.]+)?$/', $token['utility']) === 1;
                $pair = in_array('dark', $token['variants'], true) && preg_match('/^(?:'.RESTYLE_COLOUR_UTILITIES.')-/', $token['utility']) === 1;

                if ($raw || $pair) {
                    $offenders[] = $path.':'.$token['line'].' → '.implode(':', [...$token['variants'], $token['utility']]);
                }
            }
        }

        expect(array_values(array_unique($offenders)))->toBe([]);
    });
});
