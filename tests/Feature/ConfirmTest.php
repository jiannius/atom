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

    it('forwards the reason prop (and label) through the trigger', function () {
        $html = renderBlade('<atom:confirm.trigger heading="Void?" reason reason-label="Why?">Go</atom:confirm.trigger>');

        expect($html)
            ->toContain('reason')
            ->toContain('reasonLabel')
            ->toContain('Why?');
    });

    it('dispatches reason / reasonLabel / reasonPlaceholder from the PHP helper', function () {
        // Capture what atom()->confirm() dispatches on the current component.
        $fake = new class {
            public array $dispatched = [];

            public function dispatch(string $event, ...$params): void
            {
                $this->dispatched[] = ['event' => $event, 'params' => $params];
            }

            public function getId(): string
            {
                return 'fake-id';
            }
        };

        withLivewireContext($fake, function () use ($fake) {
            app('atom')->confirm(
                heading: 'Void invoice?',
                reason: true,
                reasonLabel: 'Reason for voiding',
                reasonPlaceholder: 'e.g. duplicate',
            );
        });

        expect($fake->dispatched)->toHaveCount(1);

        $params = $fake->dispatched[0]['params'];

        expect($fake->dispatched[0]['event'])->toBe('atom-confirm-show')
            ->and($params['reason'])->toBeTrue()
            ->and($params['reasonLabel'])->toBe('Reason for voiding')
            ->and($params['reasonPlaceholder'])->toBe('e.g. duplicate');
    });
});
