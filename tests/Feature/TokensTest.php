<?php

/*
 * Static guards for the design tokens (resources/css/atom.tailwind.css). What a browser makes of them (computed sizes,
 * the derived accent colours, the density profiles) is measured by tests/e2e/tokens.spec.js and density.spec.js.
 */

/** The CSS file's source, comments stripped. */
function tokenCss(string $file = 'atom.tailwind.css'): string
{
    // a comment, but never the `/**/` inside a quoted @source glob
    return preg_replace_callback('~("[^"]*")|/\*.*?\*/~s', fn (array $m) => $m[1] ?? '', file_get_contents(__DIR__.'/../../resources/css/'.$file));
}

/**
 * The declarations of every rule whose selector is exactly $selector, merged: name => value.
 *
 * @return array<string, string>
 */
function tokenBlock(string $css, string $selector): array
{
    $pattern = str_replace(', ', ',\s*', preg_quote($selector, '/'));

    preg_match_all('/(?:^|[}\s])'.$pattern.'\s*\{([^{}]*)\}/m', $css, $blocks);

    $out = [];

    foreach ($blocks[1] as $body) {
        preg_match_all('/(--[\w-]+)\s*:\s*([^;]+);/', $body, $pairs, PREG_SET_ORDER);

        foreach ($pairs as $pair) {
            $out[$pair[1]] = trim(preg_replace('/\s+/', ' ', $pair[2]));
        }
    }

    return $out;
}

/** Relative luminance of #rrggbb. */
function tokenLuminance(string $hex): float
{
    $hex = ltrim($hex, '#');

    if (strlen($hex) === 3) {
        $hex = preg_replace('/(.)/', '$1$1', $hex);
    }

    [$r, $g, $b] = array_map(function (string $channel) {
        $c = hexdec($channel) / 255;

        return $c <= 0.04045 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
    }, str_split($hex, 2));

    return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
}

function tokenContrast(float $a, float $b): float
{
    [$hi, $lo] = $a >= $b ? [$a, $b] : [$b, $a];

    return ($hi + 0.05) / ($lo + 0.05);
}

/** Opaque colours in a role map as hex, by role name (--atom-x => #rrggbb). */
function tokenHex(array $block, string $role): string
{
    $value = $block['--atom-'.$role] ?? null;

    expect($value)->toMatch('/^#[0-9a-f]{6}$/i', $role.' is not a plain hex colour');

    return $value;
}

const TOKEN_ROLES = [
    'canvas', 'surface', 'surface-sunken', 'surface-raised', 'border', 'border-strong', 'fg', 'fg-muted', 'fg-subtle', 'focus',
    'neutral-bg', 'neutral-fg', 'info-bg', 'info-fg', 'success-bg', 'success-fg', 'warning-bg', 'warning-fg', 'danger-bg', 'danger-fg',
    'shadow-pop',
];

const TOKEN_TONES = ['neutral', 'info', 'success', 'warning', 'danger'];

describe('root size', function () {
    it('sets no font-size on html or :root in atom.css', function () {
        $css = tokenCss('atom.css');

        preg_match_all('/([^{}]+)\{([^{}]*)\}/', $css, $rules, PREG_SET_ORDER);

        // the rule reader sees the file's real rules, so an empty result cannot pass for a clean one
        expect(count($rules))->toBeGreaterThan(20);
        expect(array_filter($rules, fn ($rule) => str_contains($rule[2], 'font-size') || str_contains($rule[2], 'font-family')))->not->toBeEmpty();

        foreach ($rules as [, $selectors, $body]) {
            foreach (explode(',', $selectors) as $selector) {
                if (preg_match('/^\s*(html|:root)\s*$/', $selector)) {
                    expect($body)->not->toMatch('/font-size\s*:/', 'a root font-size shifts every rem in a host');
                }
            }
        }
    });

    it('sets none in atom.tailwind.css either', function () {
        preg_match_all('/([^{}]+)\{([^{}]*)\}/', tokenCss(), $rules, PREG_SET_ORDER);

        expect(count($rules))->toBeGreaterThan(10);

        foreach ($rules as [, $selectors, $body]) {
            foreach (explode(',', $selectors) as $selector) {
                if (preg_match('/^\s*(html|:root)\s*$/', $selector)) {
                    expect($body)->not->toMatch('/(?<![\w-])font-size\s*:/');
                }
            }
        }
    });
});

describe('role tokens', function () {
    it('defines every role in a light and a dark value, and nothing in only one', function () {
        $light = tokenBlock(tokenCss(), ':root');
        $dark = tokenBlock(tokenCss(), '.dark');

        foreach (TOKEN_ROLES as $role) {
            expect($light)->toHaveKey('--atom-'.$role);
            expect($dark)->toHaveKey('--atom-'.$role);
        }

        // the dark block is the theme-dependent part of the light one: the same keys, not a subset, not a superset
        $themed = array_filter(array_keys($light), fn ($key) => in_array($key, array_map(fn ($r) => '--atom-'.$r, TOKEN_ROLES), true) || str_starts_with($key, '--atom-ink-'));
        sort($themed);
        $darkKeys = array_keys($dark);
        sort($darkKeys);

        expect($darkKeys)->toBe(array_values($themed));
    });

    it('gives the dark value a different colour from the light one wherever the theme should change it', function () {
        $light = tokenBlock(tokenCss(), ':root');
        $dark = tokenBlock(tokenCss(), '.dark');

        // the focus colour is the same derived accent-ink in both themes; every other role changes
        foreach (array_diff(TOKEN_ROLES, ['focus']) as $role) {
            if ($role === 'surface-raised' || $role === 'surface') {
                continue; // light surfaces are white by design; dark ones differ below
            }
            expect($dark['--atom-'.$role])->not->toBe($light['--atom-'.$role], $role);
        }

        expect($dark['--atom-surface'])->not->toBe($light['--atom-surface']);
        expect($dark['--atom-surface-raised'])->not->toBe($light['--atom-surface-raised']);
    });

    it('maps every colour utility token to a role that exists', function () {
        $css = tokenCss();
        $light = tokenBlock($css, ':root');
        $dark = tokenBlock($css, '.dark');

        preg_match_all('/(--(?:color|shadow)-[\w-]+)\s*:\s*var\((--atom-[\w-]+)\)/', $css, $maps, PREG_SET_ORDER);

        expect($maps)->not->toBeEmpty();

        foreach ($maps as [, $color, $role]) {
            expect(array_key_exists($role, $light))->toBeTrue($color.' reads a role the light theme lacks');
            expect(array_key_exists($role, $dark))->toBeTrue($color.' reads a role the dark theme lacks');
        }

        // every role has a utility token
        $mapped = array_column($maps, 2);

        foreach (TOKEN_ROLES as $role) {
            expect($mapped)->toContain('--atom-'.$role);
        }
    });

    it('keeps the muted aliases equal, and primary on the foreground ramp', function () {
        $css = tokenCss();

        expect($css)
            ->toMatch('/--color-muted:\s*var\(--atom-fg-muted\)/')
            ->toMatch('/--color-muted-foreground:\s*var\(--atom-fg-muted\)/')
            ->toMatch('/--color-primary:\s*var\(--atom-fg\)/');
    });

    it('declares the same warm ramp for zinc and gray', function () {
        preg_match_all('/--color-(zinc|gray)-(\d+):\s*(#[0-9a-f]{6});/i', tokenCss(), $m, PREG_SET_ORDER);

        $ramps = [];

        foreach ($m as [, $name, $step, $hex]) {
            $ramps[$name][$step] = $hex;
        }

        expect(array_map('strval', array_keys($ramps['zinc'])))->toBe(['50', '100', '200', '300', '400', '500', '600', '700', '800', '900', '950'])
            ->and($ramps['gray'])->toBe($ramps['zinc']);
    });
});

describe('contrast', function () {
    it('keeps the foreground roles above 4.5:1 on every ground in both themes', function (string $selector) {
        $block = tokenBlock(tokenCss(), $selector);

        foreach (['fg', 'fg-muted', 'fg-subtle'] as $text) {
            foreach (['canvas', 'surface', 'surface-sunken', 'surface-raised'] as $ground) {
                $ratio = tokenContrast(tokenLuminance(tokenHex($block, $text)), tokenLuminance(tokenHex($block, $ground)));

                expect($ratio)->toBeGreaterThanOrEqual(4.5, "$text on $ground ($selector) is {$ratio}:1");
            }
        }
    })->with([':root', '.dark']);

    it('keeps every tone pair above 4.5:1 in both themes', function (string $selector, string $tone) {
        $block = tokenBlock(tokenCss(), $selector);

        $ratio = tokenContrast(tokenLuminance(tokenHex($block, $tone.'-bg')), tokenLuminance(tokenHex($block, $tone.'-fg')));

        expect($ratio)->toBeGreaterThanOrEqual(4.5, "$tone ($selector) is {$ratio}:1");
    })->with([':root', '.dark'])->with(TOKEN_TONES);

    it('derives an accent foreground that reaches 4.5:1 on every pilot accent', function (string $accent) {
        $light = tokenBlock(tokenCss(), ':root');

        // the CSS picks white or near-black from the accent's luminance; this is the same rule on the same two constants
        $threshold = (float) $light['--atom-on-accent-threshold'];
        $dark = (float) $light['--atom-on-accent-dark'];
        $y = tokenLuminance($accent);
        $foreground = $y > $threshold ? $dark : 1.0;

        $ratio = tokenContrast($y, $foreground);

        expect($ratio)->toBeGreaterThanOrEqual(4.5, "$accent gets {$ratio}:1");
    })->with(['#f5c518', '#e8a011', '#1c1b18', '#e8632b', '#9f5afd', '#4c55c9', '#ffffff']);

    it('leaves no accent between the two foregrounds', function () {
        $light = tokenBlock(tokenCss(), ':root');
        $threshold = (float) $light['--atom-on-accent-threshold'];
        $dark = (float) $light['--atom-on-accent-dark'];

        // white wins up to the threshold, near-black from it: both must hold at it, or a mid-luminance accent has neither
        expect(tokenContrast($threshold, 1.0))->toBeGreaterThanOrEqual(4.5)
            ->and(tokenContrast($threshold, $dark))->toBeGreaterThanOrEqual(4.5);
    });

    it('derives the accent foreground, hover, soft and ink from --color-accent, all of them host-overridable', function () {
        $css = tokenCss();

        foreach (['accent-foreground', 'accent-hover', 'accent-soft', 'accent-ink'] as $name) {
            expect($css)->toMatch('/--color-'.$name.':[^;]*var\(--color-accent\)/s');
        }

        // they sit in a plain @theme block, not an inline one, so a host's own @theme value replaces them
        expect($css)->toMatch('/@theme static \{[^@]*--color-accent-foreground:/s');
    });
});

describe('density', function () {
    it('has a compact and a comfortable profile for every sized token, and the coarse pointer overrides both', function () {
        $css = tokenCss();
        $comfortable = tokenBlock($css, ':root, [data-atom-density="comfortable"]');
        $compact = tokenBlock($css, '[data-atom-density="compact"]');

        expect(array_keys($compact))->toEqual(array_keys($comfortable));

        $coarse = tokenBlock($css, ':root, [data-atom-density]');

        expect($css)->toContain('@media (pointer: coarse)')
            ->and($coarse)->toHaveKeys(['--atom-control-h', '--atom-row-min-h']);
    });

    it('holds the benchmark sizes', function () {
        $css = tokenCss();
        $comfortable = tokenBlock($css, ':root, [data-atom-density="comfortable"]');
        $compact = tokenBlock($css, '[data-atom-density="compact"]');
        $coarse = tokenBlock($css, ':root, [data-atom-density]');

        expect($compact['--atom-text-xs'])->toBe('0.75rem')
            ->and($compact['--atom-text-sm'])->toBe('0.8125rem')
            ->and($compact['--atom-text-base'])->toBe('0.875rem')
            ->and($compact['--atom-control-h'])->toBe('28px')
            ->and($comfortable['--atom-control-h'])->toBe('32px')
            ->and($coarse['--atom-control-h'])->toBe('40px')
            ->and($compact['--atom-row-min-h'])->toBe('40px')
            ->and($comfortable['--atom-row-min-h'])->toBe('44px')
            ->and($coarse['--atom-row-min-h'])->toBe('48px');
    });

    it('never gives a table row a fixed height with no padding', function () {
        $compact = tokenBlock(tokenCss(), '[data-atom-density="compact"]');

        expect($compact['--atom-row-pad-y'])->toBe('9px')
            ->and($compact['--atom-row-pad-x'])->toBe('12px')
            ->and($compact['--atom-row-leading'])->toBe('1.35');
    });

    it('prints data-atom-density on <body>: comfortable for a bare page, compact for the sidebar layout', function (string $tag, string $expected) {
        view()->share('errors', new \Illuminate\Support\ViewErrorBag);

        $html = renderBlade('<atom:'.$tag.' :vite="false" :fonts="false">x</atom:'.$tag.'>');

        expect($html)->toMatch('/<body[^>]*data-atom-density="'.$expected.'"/');
    })->with([
        'bare html' => ['html', 'comfortable'],
        'layouts.auth' => ['layouts.auth', 'comfortable'],
        'layouts.sidebar' => ['layouts.sidebar', 'compact'],
    ]);

    it('lets a caller pick the density, and ignores a value it does not know', function () {
        view()->share('errors', new \Illuminate\Support\ViewErrorBag);

        expect(renderBlade('<atom:html :vite="false" :fonts="false" density="compact">x</atom:html>'))->toMatch('/<body[^>]*data-atom-density="compact"/');
        expect(renderBlade('<atom:layouts.sidebar :vite="false" density="comfortable">x</atom:layouts.sidebar>'))->toMatch('/<body[^>]*data-atom-density="comfortable"/');
        expect(renderBlade('<atom:html :vite="false" :fonts="false" density="huge">x</atom:html>'))->toMatch('/<body[^>]*data-atom-density="comfortable"/');
    });
});

describe('type, radius and the integration file', function () {
    it('maps the type scale and weights from the plan', function () {
        $css = tokenCss();

        expect($css)
            ->toContain('--text-xs: var(--atom-text-xs);')
            ->toContain('--text-sm: var(--atom-text-sm);')
            ->toContain('--text-base: var(--atom-text-base);')
            ->toContain('--text-lg: 1rem;')
            ->toContain('--text-xl: 1.125rem;')
            ->toContain('--text-2xl: 1.375rem;')
            ->toContain('--text-2xl--letter-spacing: -0.02em;')
            ->toContain('--font-weight-medium: 510;')
            ->toContain('--font-weight-semibold: 590;')
            ->toContain('--font-sans: "Geist"')
            ->toContain('--font-mono: "Geist Mono"');
    });

    it('declares the radii', function () {
        expect(tokenCss())
            ->toContain('--radius-control: 7px;')
            ->toContain('--radius-nav: 6px;')
            ->toContain('--radius-panel: 10px;')
            ->toContain('--radius-modal: 12px;')
            ->toContain('--radius-pill: 999px;');
    });

    it('scans atom for utilities, follows the .dark class, and focuses with a 2px accent-ink outline', function () {
        $css = tokenCss();

        expect($css)
            ->toContain('@source "../../components/**/*.blade.php";')
            ->toContain('@source "../views/**/*.blade.php";')
            ->toContain('@source "../../src/Actions/*.php";')
            ->toContain('@custom-variant dark (&:where(.dark, .dark *));')
            ->toMatch('/:focus-visible\s*\{\s*outline:\s*2px solid var\(--atom-focus\);\s*outline-offset:\s*1px;/')
            ->toMatch('/--atom-focus:\s*var\(--color-accent-ink\)/');
    });

    it('points every @source at a directory that exists', function () {
        preg_match_all('/@source "([^"]+)"/', tokenCss(), $m);

        foreach ($m[1] as $path) {
            expect(glob(__DIR__.'/../../resources/css/'.dirname($path)))->not->toBeEmpty($path);
        }
    });

    it('is not loaded by <atom:html>: a host imports it through its own Tailwind build', function () {
        expect(renderBlade('<atom:html :vite="false">x</atom:html>'))->not->toContain('atom.tailwind');
    });
});
