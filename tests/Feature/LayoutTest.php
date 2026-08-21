<?php

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
                </x-slot:dropdown>
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
    })->with(['layouts.sidebar', 'layouts.auth']);

    it('emits the bootstrap when a layout opts in', function (string $layout) {
        $html = renderBlade('<atom:'.$layout.' :vite="false" dark>Body</atom:'.$layout.'>');

        expect($html)
            ->toContain('window.darkmode')
            ->toContain('prefers-color-scheme');
    })->with(['layouts.sidebar', 'layouts.auth']);

    // now rendered rather than read from source: layouts.auth forwards `vite`,
    // so it no longer drags in atom's default entries and a missing manifest.
    it('matches the <atom:html> default it forwards to', function (string $layout) {
        $viaLayout = renderBlade('<atom:'.$layout.' :vite="false">Body</atom:'.$layout.'>');
        $direct = renderBlade('<atom:html :vite="false">Body</atom:html>');

        expect(str_contains($viaLayout, 'window.darkmode'))
            ->toBe(str_contains($direct, 'window.darkmode'));
    })->with(['layouts.sidebar', 'layouts.auth']);
});

// layouts.auth declared no `vite` prop and forwarded none, so the <atom:html>
// inside it always resolved atom's own defaults — an app whose entries differ
// could not use the layout at all, and it could not be render-tested.
describe('layout vite entries', function () {
    it('forwards the callers entries', function (string $layout) {
        $html = renderBlade('<atom:'.$layout.' :vite="false">Body</atom:'.$layout.'>');

        expect($html)->not->toContain('resources/js/app.js');
    })->with(['layouts.sidebar', 'layouts.auth']);

    it('defaults to the same entries as <atom:html>', function (string $component) {
        $source = file_get_contents(__DIR__.'/../../components/'.$component.'.blade.php');

        expect($source)->toContain("'vite' => ['resources/css/app.css', 'resources/js/app.js']");
    })->with(['html', 'layouts/sidebar', 'layouts/auth']);
});
