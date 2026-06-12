<?php

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ViewErrorBag;

beforeEach(function () {
    view()->share('errors', new ViewErrorBag);
});

describe('alert', function () {
    it('renders the alert modal listener with config defaults', function () {
        $html = renderBlade('<atom:alert />');

        expect($html)
            ->toContain('atom-alert')
            ->toContain('x-on:atom-alert-show.window="showAlert"')
            ->toContain('Alert')   // default heading
            ->toContain('Ok');     // default button
    });

    it('renders all three variant icons for the heading row', function () {
        $html = renderBlade('<atom:alert />');

        expect($html)
            ->toContain("config.variant === 'danger'")
            ->toContain("config.variant === 'success'")
            ->toContain("config.variant === 'warning'");
    });

    it('does not leak a stray text node from the message templates', function () {
        // Regression: two <template ... hidden> tags carried a trailing "l"
        // that cloned into the DOM as visible junk.
        $html = renderBlade('<atom:alert />');

        expect($html)->not->toContain('hidden>l');
    });

    it('forwards trigger props into the alert call, html included', function () {
        $html = renderBlade('<atom:alert.trigger heading="Hi" html="<b>x</b>" button="Got it">Show</atom:alert.trigger>');

        expect($html)
            ->toContain('data-atom-alert-trigger')
            ->toContain('atom.alert(')
            ->toContain('html')
            ->toContain('Got it');
    });
});
