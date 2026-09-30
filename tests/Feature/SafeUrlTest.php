<?php

use Illuminate\Support\Facades\Blade;

/**
 * A host that renders a user-supplied URL into an href with plain {{ }} is
 * HTML-escaped, but `javascript:` still runs on click. Every component that
 * takes a URL therefore runs it through safe_url() first.
 *
 * The corpus is one file, tests/Fixtures/safe-url-corpus.json, read here and by
 * tests/e2e/safe-url.spec.js, so the PHP helper and atom.safeUrl() are held to
 * the same answers. Every hostile URL carries the marker PWNED so a test can
 * prove the payload reached the output nowhere, not only that an href is gone.
 *
 *   hostile   URLs a browser would run or treat as a script/document scheme
 *   legit     URLs a host legitimately renders; each comes back unchanged
 *   oddities  allowed URLs a browser reads correctly after its own clean-up
 *             (helper only: the attribute bag trims an href, so a component would
 *             not print these back unchanged)
 *   parity    a sample of about 4800 strings (hand-written edge cases plus seeded
 *             fuzz of entities, tabs, controls, case and Unicode look-alikes),
 *             sorted by what a browser-side atom.safeUrl() answered when the file
 *             was generated; the helper must answer the same for every one
 */
function safeUrlCorpus(): array
{
    static $corpus;

    return $corpus ??= json_decode(file_get_contents(__DIR__.'/../Fixtures/safe-url-corpus.json'), true, flags: JSON_THROW_ON_ERROR);
}

function safeUrlHostile(): array
{
    return safeUrlCorpus()['hostile'];
}

function safeUrlLegit(): array
{
    return safeUrlCorpus()['legit'];
}

function safeUrlLegitOddities(): array
{
    return safeUrlCorpus()['oddities'];
}

describe('safe_url()', function () {
    it('blocks every hostile URL', function (string $url) {
        expect(safe_url($url))->toBeNull();
    })->with(safeUrlHostile());

    it('returns a legitimate URL unchanged', function (string $url) {
        expect(safe_url($url))->toBe($url);
    })->with(safeUrlLegit());

    it('reads an allowed URL the way a browser does', function (string $url) {
        expect(safe_url($url))->toBe($url);
    })->with(safeUrlLegitOddities());

    it('treats a colon in the first path segment as a scheme', function () {
        expect(safe_url('foo:bar'))->toBeNull()
            ->and(safe_url('Meeting: notes'))->toBeNull()
            ->and(safe_url('./foo:bar'))->toBe('./foo:bar')
            ->and(safe_url('/foo:bar'))->toBe('/foo:bar');
    });

    it('returns null for empty and non-string input', function () {
        expect(safe_url(null))->toBeNull()
            ->and(safe_url(''))->toBeNull()
            ->and(safe_url([]))->toBeNull()
            ->and(safe_url(new stdClass))->toBeNull()
            ->and(safe_url(false))->toBeNull()
            ->and(safe_url(true))->toBeNull();
    });

    it('accepts a Stringable and a number', function () {
        expect(safe_url(str('/x')))->toBe('/x')
            ->and(safe_url(str('javascript:PWNED(1)')))->toBeNull()
            ->and(safe_url(12))->toBe('12');
    });

    it('blocks a deeply nested entity encoding', function () {
        // Blocked because it decodes down to a `javascript:` URL. The pass limit in
        // safe_url() is a fail-closed bound on top of that; this test does not prove
        // it (raising the limit leaves this passing), so nothing here claims to.
        $url = '&#106;avascript:PWNED(1)';

        for ($i = 0; $i < 12; $i++) {
            $url = str_replace('&', '&amp;', $url);
        }

        expect(safe_url($url))->toBeNull();
    });

    it('answers the same as atom.safeUrl() for every string in the shared sample', function () {
        // tests/e2e/safe-url.spec.js checks the browser side against the same lists.
        $corpus = safeUrlCorpus()['parity'];

        $blockedButAllowedInTheBrowser = array_values(array_filter($corpus['allowed'], fn ($url) => safe_url($url) === null));
        $allowedButBlockedInTheBrowser = array_values(array_filter($corpus['blocked'], fn ($url) => safe_url($url) !== null));

        expect(count($corpus['allowed']))->toBeGreaterThan(2000)
            ->and(count($corpus['blocked']))->toBeGreaterThan(2000)
            ->and($blockedButAllowedInTheBrowser)->toBe([])
            ->and($allowedButBlockedInTheBrowser)->toBe([])
            // an allowed URL comes back as the very same string
            ->and(array_values(array_filter($corpus['allowed'], fn ($url) => safe_url($url) !== $url)))->toBe([]);
    });

    it('decodes the legacy named entities a browser reads without a semicolon', function () {
        expect(safe_url('&amp#106;avascript:PWNED(1)'))->toBeNull()
            ->and(safe_url('javascript&amp#58;PWNED(1)'))->toBeNull()
            ->and(safe_url('&AMP#106;avascript:PWNED(1)'))->toBeNull()
            ->and(safe_url('&ampamp;#106;avascript:PWNED(1)'))->toBeNull()
            ->and(safe_url('&amp;amp;#106;avascript:PWNED(1)'))->toBeNull()
            // a legacy name glued onto more text still leaves an allowed URL allowed
            ->and(safe_url('https://example.com/?a=1&ampb=2'))->toBe('https://example.com/?a=1&ampb=2');
    });

    it('blocks invalid UTF-8 rather than passing it through', function () {
        expect(safe_url("\xff\xfejavascript:PWNED(1)"))->toBeNull();
    });
});

describe('components keep a hostile URL inert', function () {
    $components = [
        'link' => '<atom:link :href="$h">Site</atom:link>',
        'link without a slot' => '<atom:link :href="$h" />',
        'button' => '<atom:button :href="$h">Go</atom:button>',
        'tabs.item' => '<atom:tabs.item :href="$h">Go</atom:tabs.item>',
        'tabs tab array' => '<atom:tabs :tabs="[[\'label\' => \'Go\', \'href\' => $h]]" />',
        'menu.item' => '<atom:menu.item :href="$h">Go</atom:menu.item>',
        'list.item' => '<atom:list.item :href="$h">Go</atom:list.item>',
        'command.item' => '<atom:command.item :href="$h">Go</atom:command.item>',
        'navlist.item' => '<atom:navlist.item :href="$h">Go</atom:navlist.item>',
        'navlist.item as anchor' => '<atom:navlist.item as="a" :href="$h">Go</atom:navlist.item>',
        'logo' => '<atom:logo._wrapper :href="$h">x</atom:logo._wrapper>',
    ];

    foreach ($components as $name => $template) {
        it("renders {$name} without a navigable target", function (string $url) use ($template) {
            $html = renderBlade($template, ['h' => $url]);

            expect($html)
                ->not->toMatch('/\bhref\s*=/i')
                ->not->toContain('PWNED')
                ->not->toContain('javascript');
        })->with(safeUrlHostile());

        it("renders {$name} with a legitimate URL untouched", function (string $url) use ($template) {
            $html = renderBlade($template, ['h' => $url]);

            expect($html)->toContain('href="'.e($url).'"');
        })->with(safeUrlLegit());
    }

    it('turns a blocked link button into a plain button', function () {
        $html = renderBlade('<atom:button :href="$h">Go</atom:button>', ['h' => 'javascript:PWNED(1)']);

        expect($html)->toContain('<button')->toContain('type="button"')->not->toContain('<a ');
    });

    it('keeps the anchor, rel and target on a legitimate button link', function () {
        $html = renderBlade('<atom:button href="https://example.com" newtab>Go</atom:button>');

        expect($html)
            ->toContain('<a ')
            ->toContain('href="https://example.com"')
            ->toContain('target="_blank"')
            ->toContain('rel="noopener noreferrer"');
    });

    it('gives a blocked link no pointer cursor, target or rel', function () {
        $html = renderBlade('<atom:link :href="$h" newtab>Site</atom:link>', ['h' => 'javascript:PWNED(1)']);

        expect($html)
            ->not->toContain('cursor-pointer')
            ->not->toMatch('/\b(href|target|rel)\s*=/i');
    });

    it('keeps the pointer cursor, target and rel on a link that was allowed', function () {
        $html = renderBlade('<atom:link href="https://example.com" newtab>Site</atom:link>');

        expect($html)
            ->toContain('cursor-pointer')
            ->toContain('href="https://example.com"')
            ->toContain('target="_blank"')
            ->toContain('rel="noopener noreferrer nofollow"');
    });

    it('keeps the pointer cursor on a link with no href at all, which may carry wire:click', function () {
        expect(renderBlade('<atom:link wire:click="open">Open</atom:link>'))->toContain('cursor-pointer');
    });

    it('adds no target or rel to a blocked component when newtab is set', function (string $template) {
        $html = renderBlade($template, ['h' => 'javascript:PWNED(1)']);

        expect($html)->not->toMatch('/\b(href|target|rel)\s*=/i');
    })->with([
        'link' => '<atom:link :href="$h" newtab>Go</atom:link>',
        'button' => '<atom:button :href="$h" newtab>Go</atom:button>',
        'tabs.item' => '<atom:tabs.item :href="$h" newtab>Go</atom:tabs.item>',
        'menu.item' => '<atom:menu.item :href="$h" newtab>Go</atom:menu.item>',
        'list.item' => '<atom:list.item :href="$h" newtab>Go</atom:list.item>',
    ]);

    it('gives a blocked list item no pointer cursor', function () {
        expect(renderBlade('<atom:list.item :href="$h">Go</atom:list.item>', ['h' => 'javascript:PWNED(1)']))
            ->not->toContain('cursor-pointer');
    });

    it('does not navigate a table row with a blocked href', function (string $url) {
        $html = renderBlade('<table><tbody><atom:table.row :href="$h"><td>x</td></atom:table.row></tbody></table>', ['h' => $url]);

        expect($html)
            ->not->toContain('x-on:click')
            ->not->toContain('window.location')
            ->not->toContain('Livewire.navigate')
            ->not->toContain('cursor-pointer')
            ->not->toContain('PWNED');
    })->with(safeUrlHostile());

    it('does not navigate a wire:navigate table row with a blocked href', function (string $url) {
        $html = renderBlade('<table><tbody><atom:table.row wire:navigate :href="$h"><td>x</td></atom:table.row></tbody></table>', ['h' => $url]);

        expect($html)
            ->not->toContain('x-on:click')
            ->not->toContain('Livewire.navigate')
            ->not->toContain('PWNED');
    })->with(safeUrlHostile());

    it('navigates a table row with a legitimate href', function (string $url) {
        $plain = renderBlade('<table><tbody><atom:table.row :href="$h"><td>x</td></atom:table.row></tbody></table>', ['h' => $url]);
        $spa = renderBlade('<table><tbody><atom:table.row wire:navigate :href="$h"><td>x</td></atom:table.row></tbody></table>', ['h' => $url]);

        expect($plain)->toContain('window.location.href = ')->toContain('cursor-pointer')
            ->and($spa)->toContain('Livewire.navigate(');
    })->with(safeUrlLegit());

    it('gates the client-side navigation sinks with atom.safeUrl', function () {
        // These read their URL at click time, from data the server cannot see, so
        // the gate is in the markup: breadcrumbs binds the href, the toast follows
        // navigate/url, the lightbox opens item.url.
        expect(renderBlade('<atom:breadcrumbs />'))->toContain('x-bind:href="atom.safeUrl(item.href)"');

        expect(renderBlade('<atom:toast />'))
            ->toContain('atom.safeUrl(this.config.navigate)')
            ->toContain('atom.safeUrl(this.config.url)');

        expect(renderBlade('<atom:lightbox />'))->toContain('atom.safeUrl(item.url) && window.open(item.url');
    });
});

describe('the generic mail template', function () {
    // the Markdown renderer is what registers the `mail::` components, as it does for a real send
    $render = fn (array $cta) => (string) app(\Illuminate\Mail\Markdown::class)->render('atom::mail.generic', ['content' => 'Hello there', 'cta' => $cta]);

    it('renders the call-to-action button for a legitimate URL', function () use ($render) {
        $html = $render(['url' => 'https://example.com/invoice/1?a=1', 'label' => 'View invoice']);

        expect($html)
            ->toContain('button-primary')
            ->toContain('href="https://example.com/invoice/1?a=1"')
            ->toContain('View invoice');
    });

    it('omits the button for a hostile URL and keeps the message', function (string $url) use ($render) {
        $html = $render(['url' => $url, 'label' => 'View invoice']);

        expect($html)
            ->toContain('Hello there')
            ->not->toContain('View invoice')
            ->not->toContain('PWNED')
            // the layout carries its own links; the button is the one with this class
            ->not->toContain('button-primary');
    })->with(safeUrlHostile());

    it('omits the button when there is no URL, as before', function () use ($render) {
        expect($render(['label' => 'View invoice']))->not->toContain('View invoice');
    });
});
