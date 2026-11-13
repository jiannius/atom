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

    it('hands a menu item\'s typed confirmation to the dialog as `passphrase`, with `phrase` as the alias', function () {
        $passphrase = renderBlade('<atom:menu.item variant="delete" passphrase="remove-me">Delete</atom:menu.item>');
        $alias = renderBlade('<atom:menu.item variant="delete" phrase="remove-me">Delete</atom:menu.item>');
        $none = renderBlade('<atom:menu.item variant="delete">Delete</atom:menu.item>');

        expect($passphrase)->toContain('passphrase: &#039;remove-me&#039;')->not->toContain(' phrase: ');
        expect($alias)->toContain('passphrase: &#039;remove-me&#039;');
        expect($none)->toContain('passphrase: &#039;&#039;');
    });

    it('hands a delete button\'s passphrase to the dialog, escaped', function () {
        $html = renderBlade('<atom:button type="delete" passphrase="it\'s">Delete</atom:button>');

        expect($html)->toContain('passphrase: &#039;it\\u0027s&#039;');
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

describe('confirm tone and verb', function () {
    it('draws the confirm button in the dialog\'s tone', function () {
        $html = renderBlade('<atom:confirm />');

        foreach (['danger' => "config.variant === 'danger'", 'warning' => "config.variant === 'warning'", 'success' => "config.variant === 'success'"] as $tone => $condition) {
            $template = domQuery($html, '//template[@x-if="'.$condition.'"]');

            expect($template, $tone)->toHaveCount(1)
                ->and($template[0]->ownerDocument->saveHTML($template[0]), $tone)->toContain('bg-'.$tone.'-fg');
        }

        // anything else keeps the accent button
        $accent = domQuery($html, '//template[contains(@x-if, "includes(config.variant)")]');
        expect($accent)->toHaveCount(1)
            ->and($accent[0]->ownerDocument->saveHTML($accent[0]))->toContain('bg-accent');
    });

    it('names the confirm button after a delete button or menu item that has a plain label', function () {
        expect(renderBlade('<atom:button type="delete">Delete invoice</atom:button>'))->toContain('buttonConfirm: &#039;Delete invoice&#039;')
            ->and(renderBlade('<atom:menu.item variant="delete">Delete invoice</atom:menu.item>'))->toContain('buttonConfirm: &#039;Delete invoice&#039;');
    });

    it('leaves the dialog\'s default label when the trigger has no plain label', function () {
        expect(renderBlade('<atom:button type="delete" icon="delete" />'))->not->toContain('buttonConfirm')
            ->and(renderBlade('<atom:button type="delete"><b>Delete</b> it</atom:button>'))->not->toContain('buttonConfirm')
            ->and(renderBlade('<atom:menu.item variant="delete"><b>Delete</b></atom:menu.item>'))->not->toContain('buttonConfirm');
    });
});

describe('delete wording', function () {
    it('asks in sentence case, plainly, with a count-aware heading for a table\'s checked bar', function () {
        $html = renderBlade('<atom:button type="delete">Delete</atom:button>');

        expect($html)
            ->toContain('Delete this record?')
            ->toContain('This can\\u0027t be undone.')
            ->toContain('Delete :count records?')
            ->toContain('data-atom-table-checked')
            ->not->toContain('Permanently')
            ->not->toContain('CANNOT BE UNDONE');
    });

    it('uses the same wording for the delete menu item', function () {
        expect(renderBlade('<atom:menu.item variant="delete">Delete</atom:menu.item>'))
            ->toContain('Delete this record?')
            ->toContain('Delete :count records?');
    });
});
