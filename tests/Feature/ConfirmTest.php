<?php

use Illuminate\Support\ViewErrorBag;

beforeEach(function () {
    view()->share('errors', new ViewErrorBag);
});

describe('confirm', function () {
    it('renders the confirm modal form with accept/cancel wiring', function () {
        $html = renderBlade('<atom:confirm />');

        expect($html)
            ->toContain('atom-confirm')
            ->toContain('x-on:atom-confirm-show.window="showConfirm"')
            ->toContain('x-on:submit.prevent="accept"')
            ->toContain('Are you sure?')   // default heading
            ->toContain('Confirm')         // default confirm button
            ->toContain('Cancel');         // default cancel button
    });

    it('exposes password, passphrase and reason inputs', function () {
        $html = renderBlade('<atom:confirm />');

        expect($html)
            ->toContain('x-if="config.password"')
            ->toContain('x-if="config.passphrase"')
            ->toContain('x-if="config.reason"');
    });

    it('forwards trigger props into the confirm call, html included', function () {
        $html = renderBlade('<atom:confirm.trigger heading="Delete?" html="<b>x</b>" passphrase="DELETE">Go</atom:confirm.trigger>');

        expect($html)
            ->toContain('data-atom-confirm-trigger')
            ->toContain('atom.confirm(')
            ->toContain('html')
            ->toContain('passphrase');
    });
});
