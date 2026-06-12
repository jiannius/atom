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

    it('navigates via Livewire.navigate (not the old Liveiwre typo)', function () {
        $html = Blade::render('<atom:toast />');

        expect($html)
            ->toContain('Livewire.navigate(this.config.navigate)')
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
