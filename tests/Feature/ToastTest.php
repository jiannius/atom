<?php

use Illuminate\Support\Facades\Blade;

describe('toast', function () {
    it('renders the popover listener with config defaults', function () {
        $html = Blade::render('<atom:toast />');

        expect($html)
            ->toContain('data-atom-toast')
            ->toContain('popover="manual"')
            ->toContain('x-on:atom-toast-show.window="showToast"')
            // defaults baked into the @js($config)
            ->toContain('Saved')
            ->toContain('success')
            ->toContain('3000')
            ->toContain('bottom');
    });

    it('closes on atom-toast-close, but only a toast with the same source when one is given', function () {
        $html = Blade::render('<atom:toast />');

        // parse it: a stray double quote in the x-data comment ends the attribute early and
        // silently drops every attribute after it, which a string match would not notice
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8"?><body>'.$html.'</body>');
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $toast = (new DOMXPath($document))->query('//*[@data-atom-toast]')->item(0);

        expect($toast->getAttribute('role'))->toBe('status')
            ->and($toast->getAttribute('aria-live'))->toBe('polite')
            ->and($toast->getAttribute('x-on:atom-toast-close.window'))->toBe('onClose')
            ->and($toast->getAttribute('x-on:atom-toast-show.window'))->toBe('showToast')
            ->and($toast->getAttribute('x-data'))
            ->toContain('onClose (e)')
            ->toContain('this.config.source !== source')
            ->not->toContain('//')
            ->toEndWith('}');
    });

    it('navigates via Livewire.navigate (not the old Liveiwre typo)', function () {
        $html = Blade::render('<atom:toast />');

        expect($html)
            ->toContain('Livewire.navigate(navigate)')
            ->not->toContain('Liveiwre');
    });

    it('gives the close button an accessible name', function () {
        $html = Blade::render('<atom:toast />');

        expect($html)->toContain('aria-label="Close"');
    });

    it('forwards trigger props into the toast call, html included', function () {
        $html = Blade::render('<atom:toast.trigger heading="Hi" html="<b>x</b>" navigate="/go">Go</atom:toast.trigger>');

        expect($html)
            ->toContain('data-atom-toast-trigger')
            ->toContain('atom.toast(')
            ->toContain('heading')
            ->toContain('html')
            ->toContain('navigate');
    });
});
