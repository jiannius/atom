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
});
