<?php

/*
 * Geist and Geist Mono are self-hosted: the build puts the variable woff2 files in dist/assets, the /atom/{file}
 * route serves them as font/woff2, and <atom:html> preloads them. Any other `fonts` value keeps the CDN path.
 */
describe('self-hosted fonts', function () {
    it('serves both variable fonts as font/woff2 with a long-lived cache header', function (string $name) {
        $url = app('atom')->asset()->version($name);

        expect($url)->toStartWith('/atom/')->toEndWith('.woff2');

        $response = $this->get($url);

        $response->assertOk();
        expect($response->headers->get('Content-Type'))->toBe('font/woff2')
            ->and($response->headers->get('Cache-Control'))->toContain('immutable')
            ->and(str_starts_with(file_get_contents($response->getFile()->getPathname()), 'wOF2'))->toBeTrue();
    })->with(['Geist-Variable.woff2', 'GeistMono-Variable.woff2']);

    it('ships the licence beside the font sources', function () {
        expect(file_get_contents(__DIR__.'/../../resources/fonts/OFL.txt'))->toContain('SIL OPEN FONT LICENSE');
    });

    it('declares both faces in atom.css with a relative url the route can serve', function () {
        $css = file_get_contents(__DIR__.'/../../resources/css/atom.css');

        expect($css)->toContain('font-family: "Geist";')->toContain('font-family: "Geist Mono";');

        $built = file_get_contents(__DIR__.'/../../dist/'.str(app('atom')->asset()->version('atom.css'))->after('/atom/')->start('assets/'));

        expect($built)->toMatch('/url\(\.\/Geist-Variable-[\w-]+\.woff2\)/')
            ->toMatch('/url\(\.\/GeistMono-Variable-[\w-]+\.woff2\)/');
    });

    it('preloads both fonts and loads nothing from a CDN by default', function () {
        $html = renderBlade('<atom:html :vite="false">Hello</atom:html>');

        expect($html)
            ->toContain('rel="preload" href="'.app('atom')->asset()->version('Geist-Variable.woff2').'" as="font" type="font/woff2" crossorigin')
            ->toContain('rel="preload" href="'.app('atom')->asset()->version('GeistMono-Variable.woff2').'" as="font" type="font/woff2" crossorigin')
            ->not->toContain('bunny.net')
            ->not->toContain('--font-sans');
    });

    it('keeps the CDN path, and puts the family first in the stack, for any other font', function () {
        $html = renderBlade('<atom:html :vite="false" fonts="inter">Hello</atom:html>');

        expect($html)
            ->toContain('https://fonts.bunny.net/css?family=inter:400')
            ->toContain('--font-sans: "Inter", ui-sans-serif')
            ->not->toContain('rel="preload"');

        expect(renderBlade('<atom:html :vite="false" fonts="open-sans">Hello</atom:html>'))
            ->toContain('--font-sans: "Open Sans", ui-sans-serif');
    });

    it('loads no font at all for fonts=false', function () {
        expect(renderBlade('<atom:html :vite="false" :fonts="false">Hello</atom:html>'))
            ->not->toContain('bunny.net')
            ->not->toContain('rel="preload"')
            ->not->toContain('--font-sans');
    });

    it('does not let a font name break out of the style block', function () {
        $html = renderBlade('<atom:html :vite="false" fonts="x}</style><script>alert(1)</script>">Hello</atom:html>');

        expect($html)->not->toContain('--font-sans')->not->toContain('<script>alert(1)');
    });
});
