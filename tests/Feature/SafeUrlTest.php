<?php

use Illuminate\Support\Facades\Blade;

/**
 * A host that renders a user-supplied URL into an href with plain {{ }} is
 * HTML-escaped, but `javascript:` still runs on click. Every component that
 * takes a URL therefore runs it through safe_url() first.
 *
 * Every hostile URL carries the marker PWNED so a test can prove the payload
 * reached the output nowhere, not only that an href attribute is gone.
 */

/** URLs a browser would run or treat as a script/document scheme. */
function safeUrlHostile(): array
{
    return [
        'javascript' => 'javascript:PWNED(1)',
        'mixed case' => 'JaVaScRiPt:PWNED(1)',
        'upper case' => 'JAVASCRIPT:PWNED(1)',
        'leading space' => '   javascript:PWNED(1)',
        'leading control' => "\x01\x02javascript:PWNED(1)",
        'leading NUL' => "\x00javascript:PWNED(1)",
        'tab split' => "java\tscript:PWNED(1)",
        'newline split' => "java\nscript:PWNED(1)",
        'carriage return split' => "jav\r\nascript:PWNED(1)",
        'tab before colon' => "javascript\t:PWNED(1)",
        'decimal entity' => '&#106;avascript:PWNED(1)',
        'decimal entity, no semicolon' => '&#106avascript:PWNED(1)',
        'hex entity' => '&#x6A;avascript:PWNED(1)',
        'padded entity' => '&#0000106;avascript:PWNED(1)',
        'entity colon' => 'javascript&colon;PWNED(1)',
        'numeric entity colon' => 'javascript&#58;PWNED(1)',
        'entity tab' => 'java&Tab;script:PWNED(1)',
        'entity newline' => 'java&NewLine;script:PWNED(1)',
        'double encoded' => '&amp;#106;avascript:PWNED(1)',
        'triple encoded' => '&amp;amp;#106;avascript:PWNED(1)',
        'vbscript' => 'vbscript:PWNED(1)',
        'data html' => 'data:text/html,<script>PWNED(1)</script>',
        'data base64' => 'data:text/html;base64,PHNjcmlwdD5QV05FRDwvc2NyaXB0Pg==',
        'blob' => 'blob:https://example.com/PWNED',
        'file' => 'file:///etc/PWNED',
        'not a scheme char' => 'java script:PWNED(1)',
    ];
}

/** URLs a host legitimately renders; each must come back unchanged. */
function safeUrlLegit(): array
{
    return [
        'root relative' => '/dashboard',
        'relative' => 'invoices/12',
        'dot relative' => './foo:bar',
        'parent relative' => '../up/one',
        'fragment' => '#section-2',
        'query only' => '?page=2&sort=name',
        'protocol relative' => '//cdn.example.com/a.js',
        'http' => 'http://example.com',
        'https' => 'https://example.com/a/b',
        'upper case scheme' => 'HTTPS://EXAMPLE.COM/A',
        'port' => 'https://example.com:8443/admin',
        'localhost port' => 'http://127.0.0.1:8000/atom/docs',
        'query and fragment' => 'https://example.com/search?q=a+b&lang=en#results',
        'colon after slash' => '/time/10:30',
        'colon in query' => '/x?redirect=https://other.example/a',
        'colon in fragment' => '#a:b',
        'idn host' => 'https://bücher.example/päth',
        'punycode host' => 'https://xn--bcher-kva.example/',
        'mailto' => 'mailto:hello@example.com?subject=Hi',
        'tel' => 'tel:+60123456789',
        'sms' => 'sms:+60123456789?body=Hi',
        'encoded query ampersand' => 'https://example.com/?a=1&amp;b=2',
        'percent encoded colon' => '/a%3Ab',
    ];
}

/**
 * Allowed URLs a browser still reads correctly after it drops the tabs, newlines
 * and leading control characters. Without the same clean-up here they would look
 * like an unknown scheme and be blocked. Helper only: the attribute bag trims an
 * href, so a component would not print these back unchanged.
 */
function safeUrlLegitOddities(): array
{
    return [
        'tab inside the scheme' => "ht\ttps://example.com/a",
        'newline inside the scheme' => "ma\nilto:hello@example.com",
        'newline inside the path' => "https://example.com/a\nb",
        'leading space' => '  https://example.com/a',
        'leading control' => "\x01\x02https://example.com/a",
        'leading newline' => "\nhttps://example.com/a",
        'entity-encoded scheme' => 'ht&#116;ps://example.com/a',
        'entity-encoded colon' => 'https&colon;//example.com/a',
        'entity tab inside the scheme' => 'ht&Tab;tps://example.com/a',
    ];
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
            ->and(safe_url(false))->toBeNull();
    });

    it('accepts a Stringable and a number', function () {
        expect(safe_url(str('/x')))->toBe('/x')
            ->and(safe_url(str('javascript:PWNED(1)')))->toBeNull()
            ->and(safe_url(12))->toBe('12');
    });

    it('blocks a value that never settles under entity decoding', function () {
        $url = '&#106;avascript:PWNED(1)';

        for ($i = 0; $i < 12; $i++) {
            $url = str_replace('&', '&amp;', $url);
        }

        expect(safe_url($url))->toBeNull();
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
