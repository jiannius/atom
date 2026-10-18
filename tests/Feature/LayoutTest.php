<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ViewErrorBag;

// The sidebar shell mounts <atom:confirm>, whose reason input reads the shared
// $errors bag that the session middleware would normally provide.
beforeEach(fn () => view()->share('errors', new ViewErrorBag));

describe('heading', function () {
    it('renders as a div with the heading hook by default', function () {
        $html = Blade::render('<atom:heading>Dashboard</atom:heading>');

        expect($html)
            ->toContain('data-atom-heading')
            ->toContain('Dashboard')
            ->toContain('<div');
    });

    it('renders a semantic element when given a level', function () {
        $html = Blade::render('<atom:heading level="2">Dashboard</atom:heading>');

        expect($html)->toContain('<h2')->toContain('data-atom-heading');
    });

    it('applies the size scale', function () {
        $html = Blade::render('<atom:heading size="lg">Big</atom:heading>');

        expect($html)->toContain('text-lg');
    });

    // lg/xl set a size but no weight, so they inherited body weight 400; xs/sm
    // set neither, so a size="sm" heading was pixel-identical to the paragraph
    // under it. Every size now carries both halves.
    it('gives every size both a size and a weight', function (string $size, string $expected) {
        $html = Blade::render('<atom:heading size="'.$size.'">Title</atom:heading>');

        expect($html)->toContain($expected);
    })->with([
        ['xs', 'text-xs font-medium'],
        ['sm', 'text-sm font-medium'],
        ['default', 'text-base font-medium'],
        ['lg', 'text-lg font-semibold'],
        ['xl', 'text-xl font-semibold'],
    ]);

    it('lets the call site override the weight', function () {
        $html = Blade::render('<atom:heading size="lg" class="font-normal">Title</atom:heading>');

        // both land; the bag is merged last so the caller's wins in the cascade
        expect($html)->toContain('font-normal');
    });

    it('still renders a numeric size as an inline font-size', function () {
        $html = Blade::render('<atom:heading size="32">Title</atom:heading>');

        expect($html)->toContain('font-size: 32px');
    });

    it('renders an actions slot', function () {
        $html = renderBlade('<atom:heading>Title<x-slot:actions>Save</x-slot></atom:heading>');

        expect($html)
            ->toContain('data-atom-heading-actions')
            ->toContain('Save');
    });
});

describe('subheading', function () {
    it('renders muted text with the subheading hook', function () {
        $html = Blade::render('<atom:subheading>Caption</atom:subheading>');

        expect($html)
            ->toContain('data-atom-subheading')
            ->toContain('text-zinc-500')
            ->toContain('Caption');
    });

    it('passes the size through to the heading partial', function () {
        $html = Blade::render('<atom:subheading size="sm">Small</atom:subheading>');

        expect($html)->toContain('text-sm');
    });
});

describe('card', function () {
    it('renders a padded card by default', function () {
        $html = Blade::render('<atom:card>Body</atom:card>');

        expect($html)
            ->toContain('data-atom-card')
            ->toContain('rounded-lg')
            ->toContain('p-6')
            ->toContain('Body');
    });

    it('drops its own padding when inset', function () {
        $html = Blade::render('<atom:card inset>Body</atom:card>');

        expect($html)->toContain('data-atom-card-inset');
    });

    it('renders the subtle surface', function () {
        $html = Blade::render('<atom:card subtle>Body</atom:card>');

        expect($html)->toContain('bg-zinc-100');
    });

    it('renders a stats variant with a positive indicator', function () {
        $html = Blade::render('<atom:card variant="stats" heading="Revenue" data="1,000" :indicator="5" />');

        expect($html)
            ->toContain('Revenue')
            ->toContain('1,000')
            ->toContain('5%')
            ->toContain('text-green-500');
    });
});

describe('separator', function () {
    it('renders a plain rule', function () {
        $html = Blade::render('<atom:separator />');

        expect($html)
            ->toContain('data-atom-separator')
            ->toContain('role="none"')
            ->toContain('bg-zinc-200');
    });

    it('wraps a centred label between two rules', function () {
        $html = Blade::render('<atom:separator>OR</atom:separator>');

        expect($html)->toContain('OR');
        // centre alignment draws a rule on each side of the label
        expect(substr_count($html, 'bg-zinc-200'))->toBe(2);
    });
});

describe('html', function () {
    it('renders the document shell and injects atom assets', function () {
        // :vite="false" exercises the @if($vite) guard so the test does not need
        // a consuming app's Vite manifest.
        $html = Blade::render('<atom:html :vite="false" title="My Page">Hello</atom:html>');

        expect($html)
            ->toContain('<!DOCTYPE html>')
            ->toContain('<title>My Page</title>')
            ->toContain('Hello')
            ->toContain('data-navigate-once')
            ->toContain('csrf-token');
    });

    it('injects the recaptcha site key meta only when configured', function () {
        expect(Blade::render('<atom:html :vite="false">x</atom:html>'))
            ->not->toContain('recaptcha-sitekey');

        config(['services.recaptcha.site_key' => 'site-key-123']);

        expect(Blade::render('<atom:html :vite="false">x</atom:html>'))
            ->toContain('<meta name="recaptcha-sitekey" content="site-key-123">');
    });
});

describe('layouts.sidebar', function () {
    it('renders the sidebar/header/main grid areas', function () {
        $html = Blade::render('<atom:layouts.sidebar :vite="false">Main content</atom:layouts.sidebar>');

        expect($html)
            ->toContain('data-atom-sidebar')
            ->toContain('data-atom-header')
            ->toContain('data-atom-main')
            ->toContain('Main content');
    });

    it('renders the user menu without a logged-in user', function () {
        // Regression: the user-menu blocks referenced an undefined $name/$avatar
        // and called auth()->user()->initials() unguarded, which fataled when no
        // user was authenticated.
        $html = renderBlade(<<<'BLADE'
            <atom:layouts.sidebar :vite="false">
                <x-slot:dropdown>
                    <atom:menu.item>Logout</atom:menu.item>
                </x-slot>
                Body
            </atom:layouts.sidebar>
        BLADE);

        expect($html)
            ->toContain('data-atom-sidebar')
            ->toContain('Logout')
            ->toContain('Body');
    });

    // Every atom app rendered zero h1-h3: <atom:heading> defaults to a div and
    // nothing passed `level`, while the visible page title comes from
    // <atom:breadcrumbs> — a nav landmark that emits no heading. The layout now
    // supplies the outline root itself.
    it('emits one visually-hidden h1 from the page title', function () {
        $html = renderBlade('<atom:layouts.sidebar :vite="false" title="Checklists">Body</atom:layouts.sidebar>');

        expect($html)
            ->toContain('data-atom-page-title')
            ->toContain('>Checklists</h1>')
            ->toContain('class="sr-only"');

        expect(substr_count($html, '<h1'))->toBe(1);
    });

    it('emits no empty h1 when the layout has no title', function () {
        $html = renderBlade('<atom:layouts.sidebar :vite="false">Body</atom:layouts.sidebar>');

        expect($html)->not->toContain('<h1');
    });

    // <atom:html> wraps the whole darkmode bootstrap in @if ($dark), so on a
    // light-only app window.darkmode() is never defined and the switcher's menu
    // items threw when clicked. The toggle has to follow the same flag.
    it('renders the darkmode toggle only when dark mode is enabled', function () {
        $enabled = renderBlade('<atom:layouts.sidebar :vite="false" dark>Body</atom:layouts.sidebar>');
        $disabled = renderBlade('<atom:layouts.sidebar :vite="false">Body</atom:layouts.sidebar>');

        expect($enabled)
            ->toContain('data-atom-darkmode-toggle')
            ->toContain('window.darkmode');

        expect($disabled)
            ->not->toContain('data-atom-darkmode-toggle')
            ->not->toContain('window.darkmode');
    });
});

// The layouts used to default dark => true while <atom:html> defaulted it to
// false, so the same flag meant opposite things depending on the entry point.
// And because the bootstrap falls back to 'system' when nothing is stored,
// "supported" resolved to "on" for every visitor whose OS is in dark mode — an
// app got an unreviewed dark theme without opting in.
describe('dark mode is opt-in', function () {
    it('emits no darkmode bootstrap by default', function (string $layout) {
        $html = renderBlade('<atom:'.$layout.' :vite="false">Body</atom:'.$layout.'>');

        expect($html)
            ->not->toContain('window.darkmode')
            ->not->toContain('prefers-color-scheme')
            // the server-rendered opt-in class is gone too
            ->not->toContain('class="dark"');
    })->with(['layouts.sidebar', 'layouts.header', 'layouts.auth']);

    it('emits the bootstrap when a layout opts in', function (string $layout) {
        $html = renderBlade('<atom:'.$layout.' :vite="false" dark>Body</atom:'.$layout.'>');

        expect($html)
            ->toContain('window.darkmode')
            ->toContain('prefers-color-scheme');
    })->with(['layouts.sidebar', 'layouts.header', 'layouts.auth']);

    // now rendered rather than read from source: layouts.auth forwards `vite`,
    // so it no longer drags in atom's default entries and a missing manifest.
    it('matches the <atom:html> default it forwards to', function (string $layout) {
        $viaLayout = renderBlade('<atom:'.$layout.' :vite="false">Body</atom:'.$layout.'>');
        $direct = renderBlade('<atom:html :vite="false">Body</atom:html>');

        expect(str_contains($viaLayout, 'window.darkmode'))
            ->toBe(str_contains($direct, 'window.darkmode'));
    })->with(['layouts.sidebar', 'layouts.header', 'layouts.auth']);
});

// layouts.auth declared no `vite` prop and forwarded none, so the <atom:html>
// inside it always resolved atom's own defaults — an app whose entries differ
// could not use the layout at all, and it could not be render-tested.
describe('layout vite entries', function () {
    it('forwards the callers entries', function (string $layout) {
        $html = renderBlade('<atom:'.$layout.' :vite="false">Body</atom:'.$layout.'>');

        expect($html)->not->toContain('resources/js/app.js');
    })->with(['layouts.sidebar', 'layouts.header', 'layouts.auth']);

    it('defaults to the same entries as <atom:html>', function (string $component) {
        $source = file_get_contents(__DIR__.'/../../components/'.$component.'.blade.php');

        expect($source)->toContain("'vite' => ['resources/css/app.css', 'resources/js/app.js']");
    })->with(['html', 'layouts/sidebar', 'layouts/header', 'layouts/auth']);
});

// V4-2a: one nav, two shells. layouts.header is the top-nav twin of layouts.sidebar: same props, same slots, and the
// same `nav` markup, which the shell (not the nav) turns into a column or a bar.
describe('layouts.header', function () {
    it('renders the bar, the main column and the outline root', function () {
        $html = renderBlade('<atom:layouts.header :vite="false" title="Sites">Main content</atom:layouts.header>');

        expect($html)
            ->toContain('data-atom-header-bar')
            ->toContain('data-atom-main')
            ->toContain('data-atom-header-nav')
            ->toContain('data-atom-nav-shell="header"')
            ->toContain('Main content')
            ->toContain('>Sites</h1>')
            ->toMatch('/<body[^>]*data-atom-density="compact"/');

        expect(substr_count($html, '<h1'))->toBe(1);
    });

    it('leaves <main> unpadded', function (string $layout) {
        $main = domQuery(renderBlade('<atom:'.$layout.' :vite="false">Body</atom:'.$layout.'>'), '//main[@data-atom-main]')[0];

        expect(domClasses($main))->not->toContain('p-4', 'p-6', 'px-6', 'py-6', 'p-8');
    })->with(['layouts.sidebar', 'layouts.header']);

    it('declares the same props as layouts.sidebar, so a host can swap one for the other', function () {
        $props = function (string $layout): string {
            $source = file_get_contents(__DIR__.'/../../components/layouts/'.$layout.'.blade.php');
            preg_match('/@props\(\[.*?\]\)/s', $source, $match);

            return $match[0];
        };

        expect($props('header'))->toBe($props('sidebar'));
    });

    it('accepts the same slots as layouts.sidebar', function (string $layout) {
        $html = renderBlade(<<<BLADE
            <atom:{$layout} :vite="false" dark>
                <x-slot:brand><span>BRAND-SLOT</span></x-slot>
                <x-slot:context><span>CONTEXT-SLOT</span></x-slot>
                <x-slot:search><span>SEARCH-SLOT</span></x-slot>
                <x-slot:nav><atom:navlist><atom:navlist.item href="/a">NAV-SLOT</atom:navlist.item></atom:navlist></x-slot>
                <x-slot:navfoot><atom:navlist><atom:navlist.item href="/z">NAVFOOT-SLOT</atom:navlist.item></atom:navlist></x-slot>
                <x-slot:crumbs><span>CRUMBS-SLOT</span></x-slot>
                <x-slot:actions><span>ACTIONS-SLOT</span></x-slot>
                <x-slot:dropdown><atom:menu.item>DROPDOWN-SLOT</atom:menu.item></x-slot>
                <x-slot:footer>FOOTER-SLOT</x-slot>
                BODY-SLOT
            </atom:{$layout}>
        BLADE);

        expect($html)->toContain(
            'BRAND-SLOT', 'CONTEXT-SLOT', 'SEARCH-SLOT', 'NAV-SLOT', 'NAVFOOT-SLOT',
            'CRUMBS-SLOT', 'ACTIONS-SLOT', 'DROPDOWN-SLOT', 'FOOTER-SLOT', 'BODY-SLOT',
        );

        // each lands where it belongs, not in the default slot
        expect(domQuery($html, '//*[@data-atom-nav-shell]//a[contains(., "NAV-SLOT")]'))->toHaveCount(1)
            ->and(domQuery($html, '//*[@data-atom-navfoot]//a[contains(., "NAVFOOT-SLOT")]'))->toHaveCount(1)
            ->and(domQuery($html, '//*[@data-atom-shell-crumbs][contains(., "CRUMBS-SLOT")]'))->toHaveCount(1)
            ->and(domQuery($html, '//*[@data-atom-shell-actions][contains(., "ACTIONS-SLOT")]'))->toHaveCount(1)
            ->and(domQuery($html, '//*[@data-atom-shell-context][contains(., "CONTEXT-SLOT")]'))->toHaveCount(1)
            ->and(domQuery($html, '//*[@data-atom-shell-search][contains(., "SEARCH-SLOT")]'))->toHaveCount(1)
            ->and(domQuery($html, '//main[contains(., "BODY-SLOT")]'))->toHaveCount(1)
            ->and(domQuery($html, '//main[contains(., "NAV-SLOT") or contains(., "BRAND-SLOT")]'))->toHaveCount(0);
    })->with(['layouts.sidebar', 'layouts.header']);

    it('prints the nav slot once, so its links are not duplicated for the mobile menu', function () {
        $html = renderBlade('<atom:layouts.header :vite="false">
<x-slot:nav>
<atom:navlist><atom:navlist.item href="/only-once">Once</atom:navlist.item></atom:navlist>
</x-slot>
x</atom:layouts.header>');

        expect(substr_count($html, 'href="/only-once"'))->toBe(1);
    });

    it('wires the menu button to the nav panel', function () {
        $html = renderBlade('<atom:layouts.header :vite="false">x</atom:layouts.header>');
        $button = domQuery($html, '//button[@data-atom-header-menu-button]')[0];
        $panel = domQuery($html, '//*[@data-atom-header-nav]')[0];

        expect($button->getAttribute('aria-controls'))->toBe($panel->getAttribute('id'))
            ->and($panel->getAttribute('id'))->not->toBe('');
    });
});

// The header's search slot is printed once and shown three ways (a row of the menu panel, a popover a button opens, inline
// from xl: tests/e2e/shells.spec.js), and the nav that does not fit the bar moves into "More" (resources/js/alpinejs/header-shell.js).
describe('layouts.header search and "More"', function () {
    $render = fn (string $slots = '') => renderBlade('<atom:layouts.header :vite="false">'."\n".$slots."\nx</atom:layouts.header>");
    $search = "<x-slot:search>\n<input type=\"search\" aria-label=\"Find\">\n</x-slot>";
    $nav = "<x-slot:nav>\n<atom:navlist><atom:navlist.item href=\"/a\">A</atom:navlist.item></atom:navlist>\n</x-slot>";

    it('prints the search slot once, in the nav panel, under the context and above the items', function () use ($render, $search, $nav) {
        $html = $render("<x-slot:context>\n<button type=\"button\">CTX</button>\n</x-slot>\n".$search."\n".$nav);

        expect(substr_count($html, 'aria-label="Find"'))->toBe(1)
            ->and(domQuery($html, '//*[@data-atom-header-nav]//*[@data-atom-shell-search]//input[@aria-label="Find"]'))->toHaveCount(1);

        $order = fn (string $needle) => strpos($html, $needle);
        expect($order('data-atom-shell-context>'))->toBeLessThan($order('data-atom-shell-search'))
            ->and($order('data-atom-shell-search'))->toBeLessThan($order('href="/a"'));
    });

    it('gives the search button an accessible name and points it at the field', function () use ($render, $search) {
        $html = $render($search);
        $button = domQuery($html, '//button[@data-atom-shell-search-button]')[0];
        $field = domQuery($html, '//*[@data-atom-shell-search]')[0];

        expect($button->getAttribute('aria-label'))->toBe('Search')
            ->and($button->getAttribute('aria-controls'))->toBe($field->getAttribute('id'))
            ->and($field->getAttribute('id'))->not->toBe('')
            ->and($button->hasAttribute('x-bind:aria-expanded'))->toBeTrue()
            // the old class hid the field from xl down for good; atom.css decides now
            ->and($field->getAttribute('class'))->not->toContain('max-xl:hidden');
    });

    it('draws no search button and no search field without a search slot', function () use ($render, $nav) {
        $html = $render($nav);

        expect(domQuery($html, '//*[@data-atom-shell-search-button]'))->toHaveCount(0)
            ->and(domQuery($html, '//*[@data-atom-shell-search]'))->toHaveCount(0);
    });

    it('closes everything on Escape from the capture phase, so an inner dropdown\'s Escape never also closes the panel', function () use ($render) {
        $header = domQuery($render(), '//header[@data-atom-header-bar]')[0];

        expect($header->getAttribute('x-data'))->toBe('headerShell')
            ->and($header->hasAttribute('x-on:keydown.escape.capture'))->toBeTrue()
            ->and($header->getAttribute('x-on:keydown.escape.capture'))->toBe('escape($event)');
    });

    it('prints "More" once after the nav, empty, as the group dropdown\'s markup, with unique ids', function () use ($render, $search, $nav) {
        $html = $render($search."\n".$nav);
        $more = domQuery($html, '//*[@data-atom-header-more]');

        expect($more)->toHaveCount(1)
            ->and($more[0]->hasAttribute('data-atom-navlist-group'))->toBeTrue()
            ->and($more[0]->hasAttribute('data-visible'))->toBeFalse()
            ->and(domQuery($html, '//*[@data-atom-header-more]//*[@data-atom-more-copy]'))->toHaveCount(0);

        $trigger = domQuery($html, '//*[@data-atom-header-more]/button')[0];
        $panel = domQuery($html, '//*[@data-atom-header-more]/*[@data-atom-navlist-group-items]')[0];

        expect(trim($trigger->textContent))->toBe('More')
            ->and($trigger->getAttribute('aria-controls'))->toBe($panel->getAttribute('id'))
            ->and($panel->hasAttribute('wire:ignore'))->toBeTrue();

        $ids = array_map(fn ($el) => $el->getAttribute('id'), domQuery($html, '//*[@id]'));
        expect(array_unique($ids))->toHaveCount(count($ids));
    });

    it('opens "More" through toggleMore(), which closes the search popover first', function () use ($render, $search, $nav) {
        $trigger = domQuery($render($search."\n".$nav), '//*[@data-atom-header-more]/button')[0];

        expect($trigger->getAttribute('x-on:click'))->toBe('toggleMore()');
    });

    it('has no "More" when there is no nav slot', function () use ($render) {
        expect(domQuery($render(), '//*[@data-atom-header-more]'))->toHaveCount(0);
    });
});

describe('the shells\' slots', function () {
    it('puts the header shell\'s context slot in the nav panel, once, so a menu-mode user can reach it', function () {
        $html = renderBlade("<atom:layouts.header :vite=\"false\">\n<x-slot:context>\n<button type=\"button\">SWITCH-WORKSPACE</button>\n</x-slot>\nx</atom:layouts.header>");
        $panel = domQuery($html, '//*[@data-atom-header-nav]')[0];

        expect(domQuery($html, '//*[@id="'.$panel->getAttribute('id').'"]//*[@data-atom-shell-context][contains(., "SWITCH-WORKSPACE")]'))->toHaveCount(1)
            ->and(substr_count($html, 'SWITCH-WORKSPACE'))->toBe(1);
    });

    it('draws a profile with no dropdown as plain content, never a dead account button', function (string $layout) {
        $html = renderBlade('<atom:'.$layout." :vite=\"false\">\n<x-slot:profile>\n<span>PROFILE-ONLY</span>\n</x-slot>\nx</atom:".$layout.'>');

        expect(domQuery($html, '//*[@data-atom-shell-profile][contains(., "PROFILE-ONLY")]'))->toHaveCount(1)
            ->and(domQuery($html, '//button[@aria-label="Account"]'))->toHaveCount(0)
            ->and(domQuery($html, '//*[@data-atom-shell-profile]//button'))->toHaveCount(0)
            ->and(substr_count($html, 'PROFILE-ONLY'))->toBe(1);
    })->with(['layouts.sidebar', 'layouts.header']);

    it('keeps the account button where there is a menu behind it', function (string $layout) {
        $html = renderBlade('<atom:'.$layout." :vite=\"false\">\n<x-slot:dropdown>\n<atom:menu.item>OUT</atom:menu.item>\n</x-slot>\nx</atom:".$layout.'>');

        expect(domQuery($html, '//button[@aria-label="Account"]'))->not->toBeEmpty();
    })->with(['layouts.sidebar', 'layouts.header']);

    // A host's User model need not have an initials() method; the shells called it unguarded (BadMethodCallException on every
    // page with a `dropdown` slot). They now derive the initials from the display name, and still prefer the model's own.
    it('derives the account initials from the name when the user model has no initials()', function (string $layout, string $name, string $expected) {
        Auth::setUser(new class(['name' => $name, 'email' => 'user@example.com']) extends \Illuminate\Foundation\Auth\User {
            protected $guarded = [];
        });

        $html = renderBlade('<atom:'.$layout." :vite=\"false\">\n<x-slot:dropdown>\n<atom:menu.item>OUT</atom:menu.item>\n</x-slot>\nx</atom:".$layout.'>');

        expect(trim(domQuery($html, '//button[@aria-label="Account"]')[0]->textContent))->toBe($expected);
    })->with(['layouts.sidebar', 'layouts.header'])->with([
        'two words' => ['tan joon long', 'TJ'],
        'one word' => ['alex', 'A'],
        'extra spaces' => ['  alex   doe ', 'AD'],
        'multibyte' => ['éva östen', 'ÉÖ'],
    ]);

    // preventAccessingMissingAttributes() (part of Model::shouldBeStrict()) makes a saved model throw on an attribute it was
    // not loaded with, so a User table with no avatar column crashed the shells on the `$user->avatar` read.
    it('reads a missing avatar as null on a model that refuses missing attributes', function (string $layout) {
        $user = new class(['name' => 'Alex Doe', 'email' => 'user@example.com']) extends \Illuminate\Foundation\Auth\User {
            protected $guarded = [];
        };
        $user->exists = true;
        Auth::setUser($user);
        Model::preventAccessingMissingAttributes();

        try {
            $html = renderBlade('<atom:'.$layout." :vite=\"false\">\n<x-slot:dropdown>\n<atom:menu.item>OUT</atom:menu.item>\n</x-slot>\nx</atom:".$layout.'>');
        } finally {
            Model::preventAccessingMissingAttributes(false);
        }

        expect(trim(domQuery($html, '//button[@aria-label="Account"]')[0]->textContent))->toBe('AD');
    })->with(['layouts.sidebar', 'layouts.header']);

    it('prefers the user model\'s own initials()', function (string $layout) {
        Auth::setUser(new class(['name' => 'Alex Doe']) extends \Illuminate\Foundation\Auth\User {
            protected $guarded = [];

            public function initials(): string
            {
                return 'XQ';
            }
        });

        $html = renderBlade('<atom:'.$layout." :vite=\"false\">\n<x-slot:dropdown>\n<atom:menu.item>OUT</atom:menu.item>\n</x-slot>\nx</atom:".$layout.'>');

        expect(domQuery($html, '//button[@aria-label="Account"]')[0]->textContent)->toContain('XQ')->not->toContain('AD');
    })->with(['layouts.sidebar', 'layouts.header']);

    it('marks the sidebar\'s top bar empty (not drawn from lg up) only with no crumbs, actions or dark toggle', function () {
        $has = fn (string $attrs, string $slots = '') => str_contains(
            renderBlade('<atom:layouts.sidebar :vite="false" '.$attrs.">\n".$slots."\nx</atom:layouts.sidebar>"),
            'data-atom-header-empty',
        );

        expect($has(''))->toBeTrue()
            ->and($has('dark'))->toBeFalse()
            ->and($has('', "<x-slot:crumbs>\nC\n</x-slot>"))->toBeFalse()
            ->and($has('', "<x-slot:actions>\nA\n</x-slot>"))->toBeFalse();
    });
});

describe('one nav slot in both shells', function () {
    // plain items, a count, a group, a group with a heading only, and an @can-hidden item
    $nav = <<<'BLADE'
        <atom:navlist>
            <atom:navlist.item href="/dashboard" icon="dashboard" count="3">Dashboard</atom:navlist.item>
            <atom:navlist.item href="/sites" :current="true">Sites</atom:navlist.item>
            <atom:navlist.group heading="Reports" expandable>
                <atom:navlist.item href="/reports/sales">Sales</atom:navlist.item>
            </atom:navlist.group>
            <atom:navlist.group heading="Account">
                <atom:navlist.item href="/settings">Settings</atom:navlist.item>
            </atom:navlist.group>
            @can('see-secret')
                <atom:navlist.item href="/secret">Secret</atom:navlist.item>
            @endcan
            <atom:navlist.item href="javascript:alert(1)">Unsafe</atom:navlist.item>
        </atom:navlist>
    BLADE;

    it('renders the same items in each shell', function (string $layout) use ($nav) {
        $html = renderBlade('<atom:'.$layout.' :vite="false">'."\n<x-slot:nav>\n".$nav."\n</x-slot>\nx".'</atom:'.$layout.'>');

        expect($html)
            ->toContain('href="/dashboard"', 'href="/sites"', 'href="/reports/sales"', 'href="/settings"')
            ->not->toContain('/secret');
    })->with(['layouts.sidebar', 'layouts.header']);

    it('passes every nav href through safe_url() in each shell', function (string $layout) use ($nav) {
        $html = renderBlade('<atom:'.$layout.' :vite="false">'."\n<x-slot:nav>\n".$nav."\n</x-slot>\nx".'</atom:'.$layout.'>');

        expect($html)->not->toContain('javascript:');
        foreach (domQuery($html, '//a[@data-atom-navlist-item]') as $link) {
            expect($link->getAttribute('href'))->not->toStartWith('javascript');
        }
    })->with(['layouts.sidebar', 'layouts.header']);

    it('marks the current item in each shell', function (string $layout) use ($nav) {
        $html = renderBlade('<atom:'.$layout.' :vite="false">'."\n<x-slot:nav>\n".$nav."\n</x-slot>\nx".'</atom:'.$layout.'>');
        $current = domQuery($html, '//*[@data-atom-navlist-item][@aria-current="page"]');

        expect($current)->toHaveCount(1)
            ->and($current[0]->getAttribute('href'))->toBe('/sites')
            ->and($current[0]->hasAttribute('data-current'))->toBeTrue();
    })->with(['layouts.sidebar', 'layouts.header']);

    it('wraps the nav in the shell it sits in', function () use ($nav) {
        foreach (['sidebar', 'header'] as $shell) {
            $html = renderBlade('<atom:layouts.'.$shell.' :vite="false">'."\n<x-slot:nav>\n".$nav."\n</x-slot>\nx".'</atom:layouts.'.$shell.'>');

            expect(domQuery($html, '//*[@data-atom-nav-shell="'.$shell.'"]//a[@href="/sites"]'))->toHaveCount(1);
        }
    });
});
