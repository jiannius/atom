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

    it('hands a delete button\'s `phrase` alias to the dialog as `passphrase`, and does not print it as an attribute', function () {
        $alias = renderBlade('<atom:button type="delete" phrase="remove-me">Delete</atom:button>');
        $both = renderBlade('<atom:button type="delete" passphrase="first" phrase="second">Delete</atom:button>');

        expect($alias)->toContain('passphrase: &#039;remove-me&#039;')
            ->not->toContain('phrase="remove-me"')
            ->not->toContain(' phrase: ');
        expect($both)->toContain('passphrase: &#039;first&#039;');
    });

    it('hands a delete button\'s passphrase to the dialog, escaped', function () {
        $html = renderBlade('<atom:button type="delete" passphrase="it\'s">Delete</atom:button>');

        expect($html)->toContain('passphrase: &#039;it\\u0027s&#039;');
    });

    it('maps a `phrase` in an atom.confirm() config to the dialog\'s passphrase', function () {
        $xData = domQuery(renderBlade('<atom:confirm />'), '//form[@x-data]')[0]->getAttribute('x-data');

        // showConfirm() copies `phrase` over `passphrase` when only the alias is given, so the typed-text field and the check both see it
        expect($xData)->toContain('showConfirm (e)')
            ->toContain('if (! this.config.passphrase && this.config.phrase) this.config.passphrase = this.config.phrase');
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

/**
 * The JS string literal a delete trigger's click handler passes for `$key` ("buttonConfirm", "passphrase"), decoded back to the text,
 * or null when the handler has none.
 */
function confirmHandlerText(string $html, string $key): ?string
{
    foreach (domQuery($html, '//*') as $node) {
        if ($node->hasAttribute('x-on:confirmed') && preg_match("/{$key}: '([^']*)'/", $node->getAttribute('x-on:click'), $m)) {
            return json_decode('"'.$m[1].'"');
        }
    }

    return null;
}

/**
 * The click handler of the first element carrying a `confirmed` listener.
 */
function confirmHandlerOf(string $html): string
{
    foreach (domQuery($html, '//*') as $node) {
        if ($node->hasAttribute('x-on:confirmed')) {
            return $node->getAttribute('x-on:click');
        }
    }

    return '';
}

describe('confirm button label rules', function () {
    $triggers = [
        'button' => fn (string $label) => renderBlade('<atom:button type="delete">'.$label.'</atom:button>'),
        'menu item' => fn (string $label) => renderBlade('<atom:menu.item variant="delete">'.$label.'</atom:menu.item>'),
    ];

    foreach ($triggers as $name => $render) {
        it("keeps a 40 character label and drops a 41 character one ({$name})", function () use ($render) {
            expect(confirmHandlerText($render(str_repeat('a', 40)), 'buttonConfirm'))->toBe(str_repeat('a', 40));

            $long = $render(str_repeat('a', 41));

            expect($long)->not->toContain('buttonConfirm')
                ->and(confirmHandlerText($long, 'buttonConfirm'))->toBeNull();
        });

        it("counts characters, not bytes, against the limit ({$name})", function () use ($render) {
            expect(confirmHandlerText($render(str_repeat('é', 40)), 'buttonConfirm'))->toBe(str_repeat('é', 40))
                ->and($render(str_repeat('é', 41)))->not->toContain('buttonConfirm');
        });

        it("decodes the entities a plain label was written with ({$name})", function () use ($render) {
            expect(confirmHandlerText($render('Delete it&#039;s &amp; more'), 'buttonConfirm'))->toBe("Delete it's & more");
        });

        it("collapses a multi-line label to one line ({$name})", function () use ($render) {
            expect(confirmHandlerText($render("\n    Delete\n        this   invoice\n"), 'buttonConfirm'))->toBe('Delete this invoice');
        });

        it("escapes a label with a quote or a script end tag ({$name})", function () use ($render) {
            $quoted = $render('Say &quot;hi&quot; &#039;now&#039;');
            $script = $render('Go &lt;/script&gt;&lt;b&gt;');

            // decoded back, the text is what was written; in the markup, nothing can close the handler, the attribute or a script
            expect(confirmHandlerText($quoted, 'buttonConfirm'))->toBe('Say "hi" \'now\'')
                ->and(confirmHandlerText($script, 'buttonConfirm'))->toBe('Go </script><b>')
                ->and($script)->not->toContain('</script>')->not->toContain('<b>')
                ->and(confirmHandlerOf($script))->toContain('Go \\u003C\\/script\\u003E\\u003Cb\\u003E')
                ->and(confirmHandlerOf($quoted))->toContain('Say \\u0022hi\\u0022 \\u0027now\\u0027');
        });
    }
});

describe('the delete handler', function () {
    it('escapes the passphrase so it cannot break out of the handler', function () {
        $passphrase = "it's </script> \"x\" \\ `\${1}`";
        $handler = app('atom')->deleteConfirmHandler($passphrase, 'Delete it');

        expect($handler)->not->toContain('</script>')
            ->not->toContain($passphrase)
            ->toContain("passphrase: 'it\\u0027s \\u003C\\/script\\u003E \\u0022x\\u0022 \\\\ `\${1}`',");

        // and the same through the components, where the attribute is HTML-escaped on top
        $html = renderBlade('<atom:button type="delete" :passphrase="$passphrase">Delete</atom:button>', ['passphrase' => $passphrase]);

        expect(confirmHandlerText($html, 'passphrase'))->toBe($passphrase);
    });

    it('takes plain text, and null or an empty string for none', function () {
        expect(app('atom')->deleteConfirmHandler())->toContain("passphrase: '',")
            ->and(app('atom')->deleteConfirmHandler(null))->toContain("passphrase: '',")
            ->and(app('atom')->deleteConfirmHandler(''))->toContain("passphrase: '',");
    });

    it('counts a bulk delete with thousands separators, as the bar does, in one :count string', function () {
        $handler = app('atom')->deleteConfirmHandler();

        // the heading is the single string with :count, filled with the count formatted like number_format()
        expect($handler)->toContain("'Delete :count records?'.replace(':count', count.toLocaleString('en-US'))")
            ->and($handler)->toContain('count > 1 ?')
            ->and(trans('atom::messages.delete-selected-records'))->toBe('Delete :count records?')->not->toContain('|');
    });
});
