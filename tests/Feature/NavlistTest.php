<?php

use Illuminate\Support\Facades\Blade;

describe('navlist', function () {
    it('renders a nav wrapper', function () {
        $html = Blade::render('<atom:navlist><atom:navlist.item href="/a">A</atom:navlist.item></atom:navlist>');

        expect($html)->toContain('data-atom-navlist')->toContain('<nav');
    });

    it('renders an item as a link with an icon and current-route flag', function () {
        $html = Blade::render('<atom:navlist.item href="/dash" icon="dashboard" :current="true">Dashboard</atom:navlist.item>');

        expect($html)
            ->toContain('data-atom-navlist-item')
            ->toContain('<a')
            ->toContain('href="/dash"')
            ->toContain('Dashboard')
            ->toContain('data-current')
            ->toContain('data-atom-icon');
    });

    it('vertically centers the icon in its wrapper (alignment fix)', function () {
        // Regression: the icon wrapper was a plain <div> whose inline line-box
        // ran taller than the 20px glyph, so items-center pushed the icon high.
        $html = Blade::render('<atom:navlist.item href="/a" icon="dashboard">A</atom:navlist.item>');

        expect($html)->toContain('relative flex shrink-0 items-center');
    });

    it('renders a static heading group', function () {
        $html = Blade::render('<atom:navlist.group heading="Navigation"><atom:navlist.item href="/a">A</atom:navlist.item></atom:navlist.group>');

        expect($html)->toContain('Navigation')->toContain('A');
    });

    // The heading reads a role token, which flips with the theme by itself, so it needs no dark: pair. The old
    // bare `text-muted` was zinc-500 on a zinc-900 sidebar, 3.67:1.
    it('colours the plain group heading with a role token that follows the theme', function () {
        $html = Blade::render('<atom:navlist.group heading="Navigation"><atom:navlist.item href="/a">A</atom:navlist.item></atom:navlist.group>');

        expect($html)->toContain('text-xs font-medium text-fg-subtle')->not->toContain('uppercase');
    });

    it('renders an expandable group with Alpine, not a ui-disclosure web component', function () {
        $html = Blade::render('<atom:navlist.group expandable heading="Section"><atom:navlist.item href="/a">A</atom:navlist.item></atom:navlist.group>');

        expect($html)
            ->toContain('data-atom-navlist-group')
            ->toContain('x-data=')
            ->toContain('open:')
            ->toContain('aria-expanded')
            ->not->toContain('ui-disclosure');
    });
});

describe('navlist.group persist-key', function () {
    it('stays inert without the prop — no key, no storage access', function () {
        $html = Blade::render('<atom:navlist.group expandable heading="Section"><atom:navlist.item href="/a">A</atom:navlist.item></atom:navlist.group>');

        expect($html)
            ->toContain('key: null')
            ->toContain('open: true');
    });

    it('namespaces the storage key so a caller cannot collide with the app', function () {
        $html = Blade::render('<atom:navlist.group expandable heading="Purchase" persist-key="nav.purchase"><atom:navlist.item href="/a">A</atom:navlist.item></atom:navlist.group>');

        // a caller passing "sidebar" must not read or write a bare "sidebar" entry
        expect($html)->toContain('atom:navlist-group:nav.purchase');
    });

    it('guards both reads and writes, so a throwing localStorage cannot kill the component', function () {
        $html = Blade::render('<atom:navlist.group expandable heading="S" persist-key="k"><atom:navlist.item href="/a">A</atom:navlist.item></atom:navlist.group>');

        // Safari private mode throws on access; an uncaught throw in init() would
        // take the whole Alpine component down and stop the group toggling at all
        expect(substr_count($html, 'try {'))->toBe(2)
            ->and($html)->toContain('window.localStorage.getItem')
            ->and($html)->toContain('window.localStorage.setItem');
    });

    it('persists via $watch, so any other flip of open is stored too', function () {
        $html = Blade::render('<atom:navlist.group expandable heading="S" persist-key="k"><atom:navlist.item href="/a">A</atom:navlist.item></atom:navlist.group>');

        // not hooked to the click handler — a keyboard shortcut or a programmatic
        // collapse-all persists for free, and x-on:click stays as it was
        expect($html)
            ->toContain("\$watch('open'")
            ->toContain('x-on:click="open = !open"');
    });

    it('still renders expanded as the initial value; storage overrides at runtime', function () {
        $html = Blade::render('<atom:navlist.group expandable heading="S" :expanded="false" persist-key="k"><atom:navlist.item href="/a">A</atom:navlist.item></atom:navlist.group>');

        expect($html)->toContain('open: false');
    });

    it('ignores the prop on a non-expandable group', function () {
        $html = Blade::render('<atom:navlist.group heading="S" persist-key="k"><atom:navlist.item href="/a">A</atom:navlist.item></atom:navlist.group>');

        expect($html)->not->toContain('atom:navlist-group:k');
    });
});

describe('navlist.group flash guard', function () {
    // A group that can start collapsed is corrected by Alpine, not by the server, so
    // without x-cloak the browser is free to paint it open first. Measured at 655ms with
    // the Alpine script held; locally Alpine wins the race, which is why this needs a
    // structural assertion as well as tests/e2e/navlist-persist.spec.js.
    it('cloaks a group whose open state is only settled once Alpine runs', function (string $template) {
        $html = Blade::render($template);

        expect($html)->toContain('x-cloak');
    })->with([
        'persisted, so the stored value is unknown server-side' => ['<atom:navlist.group expandable heading="S" persist-key="k"><atom:navlist.item href="/a">A</atom:navlist.item></atom:navlist.group>'],
        'expanded=false, applied by x-show' => ['<atom:navlist.group expandable heading="S" :expanded="false"><atom:navlist.item href="/a">A</atom:navlist.item></atom:navlist.group>'],
    ]);

    // Cloaking costs a render: the group stays invisible until Alpine boots. A group that
    // just starts open needs no correction, so it must not pay that.
    it('does not cloak a group that simply starts open', function () {
        $html = Blade::render('<atom:navlist.group expandable heading="S"><atom:navlist.item href="/a">A</atom:navlist.item></atom:navlist.group>');

        expect($html)->not->toContain('x-cloak');
    });
});

// One group markup for both shells: a label (or disclosure) over its items in the sidebar, a dropdown in layouts.header.
describe('navlist.group in the header shell', function () {
    it('carries a dropdown trigger and a panel alongside the sidebar label', function () {
        $html = Blade::render('<atom:navlist.group heading="Reports"><atom:navlist.item href="/a">A</atom:navlist.item></atom:navlist.group>');

        expect($html)
            ->toContain('data-atom-navlist-group')
            ->toContain('data-atom-navlist-group-label')
            ->toContain('data-atom-navlist-group-trigger')
            ->toContain('data-atom-navlist-group-items')
            ->toContain('x-bind:aria-expanded="menu');
    });

    // A disclosure, not a menu: it keeps aria-expanded, points at its panel, and does not claim a popup menu role.
    it('is a disclosure: aria-expanded and aria-controls with a derived id, no aria-haspopup', function () {
        $render = fn () => Blade::render('<atom:navlist.group heading="Reports"><atom:navlist.item href="/a">A</atom:navlist.item></atom:navlist.group>');

        $html = $render();
        $trigger = domQuery($html, '//button[@data-atom-navlist-group-trigger]')[0];
        $panel = domQuery($html, '//*[@data-atom-navlist-group-items]')[0];

        expect($trigger->hasAttribute('aria-haspopup'))->toBeFalse()
            ->and($trigger->hasAttribute('x-bind:aria-expanded'))->toBeTrue()
            ->and($trigger->getAttribute('aria-controls'))->not->toBe('')
            ->and($panel->getAttribute('id'))->toBe($trigger->getAttribute('aria-controls'))
            ->and($render())->toBe($html);

        $other = Blade::render('<atom:navlist.group heading="Settings"><atom:navlist.item href="/a">A</atom:navlist.item></atom:navlist.group>');

        expect(domQuery($other, '//*[@data-atom-navlist-group-items]')[0]->getAttribute('id'))->not->toBe($panel->getAttribute('id'));
    });

    // Two groups with the same heading (and no persist-key) share a seed, and an id that two panels share points two
    // triggers at one panel. The seed is numbered per render, never randomised: an id is a morph key.
    it('gives two groups with the same heading different ids, the same on every render', function () {
        $page = '<atom:navlist>'
            .'<atom:navlist.group heading="Reports"><atom:navlist.item href="/a">A</atom:navlist.item></atom:navlist.group>'
            .'<atom:navlist.group heading="Reports"><atom:navlist.item href="/b">B</atom:navlist.item></atom:navlist.group>'
            .'<atom:navlist.group heading="Reports"><atom:navlist.item href="/c">C</atom:navlist.item></atom:navlist.group>'
            .'</atom:navlist>';

        $ids = function (string $html): array {
            $triggers = domQuery($html, '//button[@data-atom-navlist-group-trigger]');
            $panels = domQuery($html, '//*[@data-atom-navlist-group-items]');

            // each trigger controls the panel beside it
            foreach ($triggers as $i => $trigger) {
                expect($panels[$i]->getAttribute('id'))->toBe($trigger->getAttribute('aria-controls'));
            }

            return array_map(fn ($panel) => $panel->getAttribute('id'), $panels);
        };

        $first = $ids(Blade::render($page));

        expect($first)->toHaveCount(3)
            ->and(array_unique($first))->toHaveCount(3)
            ->and($first[0])->not->toBe('')
            // the same page renders to the same ids again, and again
            ->and($ids(Blade::render($page)))->toBe($first)
            ->and($ids(Blade::render($page)))->toBe($first);
    });

    it('keeps the id of a single group what it was, and keeps persist-key groups apart', function () {
        $id = fn (string $template) => domQuery(Blade::render($template), '//*[@data-atom-navlist-group-items]')[0]->getAttribute('id');

        $single = '<atom:navlist.group heading="Reports"><atom:navlist.item href="/a">A</atom:navlist.item></atom:navlist.group>';

        expect($id($single))->toBe('atom-navlist-group-'.substr(md5('|Reports'), 0, 8))
            ->and($id('<atom:navlist.group expandable heading="Reports" persist-key="x"><atom:navlist.item href="/a">A</atom:navlist.item></atom:navlist.group>'))
            ->not->toBe($id($single));
    });

    it('prints the items once', function () {
        $html = Blade::render('<atom:navlist.group heading="Reports"><atom:navlist.item href="/only-once">A</atom:navlist.item></atom:navlist.group>');

        expect(substr_count($html, 'href="/only-once"'))->toBe(1);
    });

    it('reads the shell from the wrapper the layout puts around the nav', function () {
        $html = Blade::render('<atom:navlist.group heading="Reports"><atom:navlist.item href="/a">A</atom:navlist.item></atom:navlist.group>');

        expect($html)->toContain('[data-atom-nav-shell=header]');
    });

    it('only cloaks a group that has an expandable state to settle', function () {
        $html = Blade::render('<atom:navlist.group heading="Reports" :expanded="false" persist-key="k"><atom:navlist.item href="/a">A</atom:navlist.item></atom:navlist.group>');

        expect($html)->not->toContain('x-cloak');
    });

    it('keeps the expandable disclosure as the first button, and the trigger second', function () {
        $html = Blade::render('<atom:navlist.group expandable heading="Reports"><atom:navlist.item href="/a">A</atom:navlist.item></atom:navlist.group>');
        $buttons = domQuery($html, '//button');

        expect($buttons[0]->hasAttribute('data-atom-navlist-group-toggle'))->toBeTrue()
            ->and($buttons[1]->hasAttribute('data-atom-navlist-group-trigger'))->toBeTrue();
    });

    it('leaves a group with no heading as a plain section', function () {
        $html = Blade::render('<atom:navlist.group><atom:navlist.item href="/a">A</atom:navlist.item></atom:navlist.group>');

        expect($html)->toContain('data-atom-navlist-section')->not->toContain('data-atom-navlist-group-trigger');
    });
});

describe('navlist.item', function () {
    it('marks the current item for assistive tech as well as for styling', function () {
        $current = Blade::render('<atom:navlist.item href="/a" :current="true">A</atom:navlist.item>');
        $other = Blade::render('<atom:navlist.item href="/a" :current="false">A</atom:navlist.item>');

        expect(domQuery($current, '//a[@aria-current="page"][@data-current]'))->toHaveCount(1);
        expect(domQuery($other, '//a[@aria-current or @data-current]'))->toHaveCount(0);
    });

    it('is a 28px row at the compact density and draws its icon at 16px', function () {
        $html = Blade::render('<atom:navlist.item href="/a" icon="dashboard">A</atom:navlist.item>');

        expect($html)->toContain('h-control')->toContain('size-4')->toContain('rounded-nav');
    });
});

// The current item is read from the PATH of its href; an href that names another host never counts.
describe('navlist current item', function () {
    $current = function (string $url, string $href, string $appUrl = 'http://app.test'): bool {
        app()->instance('request', \Illuminate\Http\Request::create($url));
        config(['app.url' => $appUrl]);

        $html = Blade::render('<atom:navlist.item href="'.$href.'">Item</atom:navlist.item>');

        return count(domQuery($html, '//*[@data-atom-navlist-item][@aria-current="page"]')) === 1;
    };

    it('marks the item whose path is the request\'s path', function () use ($current) {
        expect($current('http://app.test/invoices', '/invoices'))->toBeTrue()
            ->and($current('http://app.test/invoices', '/customers'))->toBeFalse()
            ->and($current('http://app.test/', '/'))->toBeTrue();
    });

    it('follows the host the app is really served from, not only APP_URL', function () use ($current) {
        // the request comes in on another domain than APP_URL: a relative href, and an absolute one on the request's own host, count
        expect($current('http://other.example/invoices', '/invoices'))->toBeTrue()
            ->and($current('http://other.example/invoices', 'https://other.example/invoices'))->toBeTrue()
            ->and($current('http://other.example/invoices', 'http://app.test/invoices'))->toBeTrue()
            ->and($current('http://other.example/invoices', 'https://other.example/customers'))->toBeFalse();
    });

    it('never marks an external link, whatever its path', function () use ($current) {
        expect($current('http://app.test/orders', 'https://github.com/orders'))->toBeFalse()
            ->and($current('http://app.test/', 'https://docs.example.com/'))->toBeFalse()
            ->and($current('http://other.example/invoices', 'https://elsewhere.example/invoices'))->toBeFalse()
            // a look-alike host is a different host
            ->and($current('http://app.test/orders', 'https://app.test.evil.example/orders'))->toBeFalse()
            ->and($current('http://app.test/orders', '//github.com/orders'))->toBeFalse();
    });

    it('ignores the port when it compares hosts', function () use ($current) {
        expect($current('http://127.0.0.1:8000/invoices', 'http://127.0.0.1:8000/invoices'))->toBeTrue()
            ->and($current('http://127.0.0.1:8000/invoices', 'http://127.0.0.1/invoices'))->toBeTrue()
            // APP_URL without the port, the request on :8000
            ->and($current('http://127.0.0.1:8000/invoices', 'http://localhost/invoices', 'http://localhost'))->toBeTrue()
            ->and($current('http://127.0.0.1:8000/invoices', 'http://localhost:9000/invoices', 'http://localhost'))->toBeTrue()
            ->and($current('http://127.0.0.1:8000/invoices', 'http://localhost/customers', 'http://localhost'))->toBeFalse();
    });

    it('reads an app installed under a path from that path', function () use ($current) {
        // APP_URL=http://app.test/sub: the request path is relative to the install
        // (a real install gives a request path without the install's own path, which is what these requests are)
        expect($current('http://app.test/invoices', 'http://app.test/sub/invoices', 'http://app.test/sub'))->toBeTrue()
            ->and($current('http://app.test/invoices', '/sub/invoices', 'http://app.test/sub'))->toBeTrue()
            ->and($current('http://app.test/invoices', 'http://app.test/sub/customers', 'http://app.test/sub'))->toBeFalse()
            // only the path prefix is stripped: a sibling that starts with the same letters is not the install
            ->and($current('http://app.test/invoices', '/subscriptions', 'http://app.test/sub'))->toBeFalse();
    });

    it('is an exact match under a locale prefix', function () use ($current) {
        expect($current('http://app.test/en/invoices', '/en/invoices'))->toBeTrue()
            ->and($current('http://app.test/en/invoices', '/ms/invoices'))->toBeFalse()
            ->and($current('http://app.test/en/invoices', '/invoices'))->toBeFalse()
            ->and($current('http://app.test/en', '/en'))->toBeTrue();
    });

    it('ignores a trailing slash and a query string, on the href and on the request', function () use ($current) {
        expect($current('http://app.test/invoices', '/invoices/'))->toBeTrue()
            ->and($current('http://app.test/invoices/', '/invoices'))->toBeTrue()
            ->and($current('http://app.test/invoices', '/invoices?status=paid'))->toBeTrue()
            ->and($current('http://app.test/invoices?page=2', '/invoices'))->toBeTrue()
            ->and($current('http://app.test/invoices', '/invoices#top'))->toBeTrue()
            ->and($current('http://app.test/invoices', 'http://app.test/invoices/?a=1'))->toBeTrue();
    });

    it('stays an exact match: a parent path is not current for a deeper one, or the other way', function () use ($current) {
        expect($current('http://app.test/invoices/12', '/invoices'))->toBeFalse()
            ->and($current('http://app.test/invoices', '/invoices/12'))->toBeFalse();
    });

    it('keeps a pattern in the path working, and the explicit :current', function () use ($current) {
        expect($current('http://app.test/invoices/12', '/invoices/*'))->toBeTrue();

        app()->instance('request', \Illuminate\Http\Request::create('http://app.test/other'));

        $forced = Blade::render('<atom:navlist.item href="/invoices" :current="true">A</atom:navlist.item>');
        $denied = Blade::render('<atom:navlist.item href="/other" :current="false">A</atom:navlist.item>');

        expect(domQuery($forced, '//a[@aria-current="page"]'))->toHaveCount(1)
            ->and(domQuery($denied, '//a[@aria-current]'))->toHaveCount(0);
    });

    it('marks the home item for the URL Laravel gives, which has no path', function () use ($current) {
        // route('home') and url('/') are `http://localhost`: a host that is the app's, and no path, is "/"
        expect($current('http://app.test/', 'http://app.test'))->toBeTrue()
            ->and($current('http://app.test/', 'http://app.test/'))->toBeTrue()
            ->and($current('http://app.test/', 'http://app.test?ref=nav'))->toBeTrue()
            ->and($current('http://127.0.0.1:8000/', 'http://localhost', 'http://localhost'))->toBeTrue()
            ->and($current('http://other.example/', 'http://other.example'))->toBeTrue()
            // only on the home page, and never for another host
            ->and($current('http://app.test/invoices', 'http://app.test'))->toBeFalse()
            ->and($current('http://app.test/', 'https://docs.example.com'))->toBeFalse();

        // and through the helpers a host really writes
        app()->instance('request', \Illuminate\Http\Request::create('http://localhost/'));
        config(['app.url' => 'http://localhost']);

        $home = Blade::render('<atom:navlist.item :href="$href">Home</atom:navlist.item>', ['href' => url('/')]);

        expect(domQuery($home, '//a[@aria-current="page"]'))->toHaveCount(1);
    });

    it('is never current for a scheme that is not a page: mailto, tel', function (string $href) use ($current) {
        expect($current('http://app.test/orders', $href))->toBeFalse();
    })->with(['mailto:orders', 'mailto:orders@example.test', 'tel:orders', 'sms:orders']);

    it('is never current for an href with no host and no path, or none at all', function (string $href) use ($current) {
        expect($current('http://app.test/', $href))->toBeFalse();
    })->with(['?page=invoices', '#top', '']);
});
