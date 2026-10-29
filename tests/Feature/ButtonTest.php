<?php

describe('button', function () {
    it('renders a button element with its label and data hook', function () {
        $html = renderBlade('<atom:button>Save</atom:button>');

        expect($html)
            ->toContain('<button')
            ->toContain('type="button"')
            ->toContain('Save')
            ->toContain('data-atom-button');
    });

    it('does not auto-label a button that already has visible text', function () {
        // Regression: the slot text used to be copied into aria-label, which
        // is redundant — the visible text already names the control.
        expect(renderBlade('<atom:button>Save</atom:button>'))
            ->not->toContain('aria-label');
    });

    it('styles variants', function () {
        // three tiers: the default is bordered, `primary` the solid accent (`accent` is its old name), `ghost` bare
        expect(renderBlade('<atom:button>Default</atom:button>'))->toContain('bg-surface')->toContain('border-control-border');
        expect(renderBlade('<atom:button variant="primary">P</atom:button>'))->toContain('bg-accent text-accent-foreground');
        expect(renderBlade('<atom:button variant="accent">A</atom:button>'))->toContain('bg-accent text-accent-foreground');
        expect(renderBlade('<atom:button variant="danger">D</atom:button>'))->toContain('bg-danger-fg');
        expect(renderBlade('<atom:button variant="ghost">G</atom:button>'))->toContain('bg-transparent');
    });

    it('draws no shadow on any tier', function (string $variant) {
        expect(renderBlade('<atom:button variant="'.$variant.'">B</atom:button>'))
            ->not->toContain('shadow');
    })->with(['default', 'primary', 'accent', 'danger', 'warning', 'success', 'ghost', 'link', 'google']);

    it('treats the variants outline and secondary, which v3 never had, as the default tier', function (string $variant) {
        expect(renderBlade('<atom:button variant="'.$variant.'">B</atom:button>'))
            ->toContain('bg-surface')
            ->toContain('border-control-border');
    })->with(['outline', 'secondary']);

    it('draws a social button as the default tier, with the brand icon and no brand colour', function () {
        $html = renderBlade('<atom:button variant="google" icon="google">Sign in</atom:button>');

        expect($html)
            ->toContain('border-control-border')
            ->not->toMatch('/(?:bg|text|border)-(?:rose|blue|sky|green)-/');
    });

    it('gives the link variant a dark-mode text colour', function () {
        // Regression: link was text-zinc-800 with no dark: variant, so it was
        // near-invisible on dark backgrounds.
        expect(renderBlade('<atom:button variant="link">L</atom:button>'))
            ->toContain('underline-offset-4')
            ->toContain('text-fg')
            ->not->toContain('dark:');
    });

    it('tints ghost buttons by colour', function () {
        expect(renderBlade('<atom:button variant="ghost" color="danger">D</atom:button>'))->toContain('text-danger-fg');
        expect(renderBlade('<atom:button variant="ghost" color="primary">P</atom:button>'))->toContain('text-accent-ink');
    });

    it('sizes the button', function () {
        expect(renderBlade('<atom:button size="xs">x</atom:button>'))->toContain('h-control-xs');
        expect(renderBlade('<atom:button size="sm">s</atom:button>'))->toContain('h-control-sm');
        expect(renderBlade('<atom:button>d</atom:button>'))->toContain('h-control ');
        expect(renderBlade('<atom:button size="md">m</atom:button>'))->toContain('h-control-md');
        expect(renderBlade('<atom:button size="lg">l</atom:button>'))->toContain('h-control-lg');
    });

    it('spans full width when block', function () {
        expect(renderBlade('<atom:button block>B</atom:button>'))->toContain('flex w-full');
    });

    it('renders a leading icon and a trailing icon', function () {
        $html = renderBlade('<atom:button icon="add" iconSuffix="arrow-right">Go</atom:button>');

        expect($html)
            ->toContain('data-atom-icon')
            ->toContain('-ml-0.5'); // the trailing icon spacing
    });

    it('renders a slotless button as an icon-only square', function () {
        $html = renderBlade('<atom:button icon="settings"/>');

        expect($html)
            ->toContain('rounded-control')
            ->toContain('aspect-square')
            ->toContain('h-control');
    });

    it('auto-labels an icon-only button from the icon name', function () {
        expect(renderBlade('<atom:button icon="settings"/>'))->toContain('aria-label="Settings"');
        expect(renderBlade('<atom:button icon="arrow-right"/>'))->toContain('aria-label="Arrow Right"');
    });

    it('lets a consumer override the icon-only label', function () {
        $html = renderBlade('<atom:button icon="settings" aria-label="Open settings"/>');

        expect($html)
            ->toContain('aria-label="Open settings"')
            ->not->toContain('aria-label="Settings"');
    });

    it('styles a submit button as primary and wires its loading state', function () {
        $html = renderBlade('<atom:button type="submit">Submit</atom:button>');

        expect($html)
            ->toContain('type="submit"')
            ->toContain('bg-accent')
            ->toContain('wire:loading.class="is-loading"')
            ->toContain('wire:target="submit"');
    });

    it('adds no icon of its own to a submit or a delete button', function () {
        // the loading spinner is the one icon every button holds; a check or a trash can would be a second
        expect(substr_count(renderBlade('<atom:button type="submit">Save</atom:button>'), 'data-atom-icon'))->toBe(1);
        expect(substr_count(renderBlade('<atom:button type="delete">Delete</atom:button>'), 'data-atom-icon'))->toBe(1);
        expect(substr_count(renderBlade('<atom:button type="submit" icon="check">Save</atom:button>'), 'data-atom-icon'))->toBe(2);
    });

    it('auto-wires the delete confirm flow', function () {
        $html = renderBlade('<atom:button type="delete">Delete</atom:button>');

        expect($html)
            ->toContain('bg-danger-bg') // danger + inverted
            ->toContain('atom.confirm({')
            ->toContain("\$dispatch(&#039;confirmed&#039;)")
            ->toContain('x-on:confirmed="$wire.delete()"');
    });

    it('does not auto-wire the confirm flow when the caller sets its own click', function () {
        $html = renderBlade('<atom:button type="delete" wire:click="remove">Delete</atom:button>');

        expect($html)->not->toContain('atom.confirm({');
    });

    it('renders an anchor when given an href', function () {
        $html = renderBlade('<atom:button href="/foo">Link</atom:button>');

        expect($html)
            ->toContain('<a')
            ->toContain('href="/foo"')
            ->toContain('rel="noopener noreferrer"');
    });

    it('opens in a new tab when newtab is set', function () {
        expect(renderBlade('<atom:button href="/foo" newtab>Link</atom:button>'))
            ->toContain('target="_blank"');
    });

    it('builds a whatsapp share link', function () {
        $html = renderBlade("<atom:button :social=\"['name' => 'whatsapp', 'number' => '123', 'text' => 'hi']\">Chat</atom:button>");

        expect($html)
            ->toContain('<a')
            ->toContain('href="https://wa.me/123?text=hi"');
    });

    it('builds a telegram share link', function () {
        $html = renderBlade("<atom:button :social=\"['name' => 'telegram', 'url' => 'https://x.test', 'text' => 'hi']\">Share</atom:button>");

        expect($html)->toContain('href="https://t.me/share/url?url=https://x.test&amp;text=hi"');
    });

    it('wires the loading state for a wire:click button', function () {
        $html = renderBlade('<atom:button wire:click="save">Save</atom:button>');

        expect($html)
            ->toContain('wire:loading.class="is-loading"')
            ->toContain('wire:target="save"');
    });

    it('leaves an explicit wire:loading alone', function () {
        $html = renderBlade('<atom:button wire:click="save" wire:loading.attr="disabled">Save</atom:button>');

        expect($html)->not->toContain('wire:loading.class="is-loading"');
    });
});

describe('button label', function () {
    it('wraps a plain-text label in an element that can truncate, so a suffix icon stays in view', function () {
        $html = renderBlade('<atom:button block icon-suffix="dropdown">Northwind Trading Company</atom:button>');
        $label = domQuery($html, '//button[@data-atom-button]//span[@data-atom-button-label]');

        expect($label)->toHaveCount(1)
            ->and($label[0]->getAttribute('class'))->toContain('min-w-0')->toContain('truncate')
            ->and(trim($label[0]->textContent))->toBe('Northwind Trading Company')
            // the content row can shrink and, on a block button, takes the whole width and the button's own justification
            ->and($html)->toContain('inline-flex min-w-0 max-w-full items-center gap-2 w-full [justify-content:inherit]');
    });

    it('lets a non-block button shrink to a narrow container', function () {
        expect(renderBlade('<atom:button>Save</atom:button>'))
            ->toContain('inline-flex max-w-full')
            ->toContain('items-center gap-2 justify-center');
    });

    it('wraps no label for an icon-only button or for a slot holding markup', function () {
        expect(domQuery(renderBlade('<atom:button icon="add"/>'), '//*[@data-atom-button-label]'))->toBeEmpty();
        expect(domQuery(renderBlade('<atom:button><atom:badge>3</atom:badge> Inbox</atom:button>'), '//*[@data-atom-button-label]'))->toBeEmpty();
    });

    it('keeps the label wrapper for a link, a submit and a delete button', function (string $tag) {
        expect(domQuery(renderBlade($tag), '//*[@data-atom-button]//*[@data-atom-button-label]'))->toHaveCount(1);
    })->with([
        'link' => '<atom:button href="/x">Open</atom:button>',
        'submit' => '<atom:button type="submit">Save</atom:button>',
        'delete' => '<atom:button type="delete">Delete</atom:button>',
        'icon + label' => '<atom:button icon="add">New</atom:button>',
    ]);
});

describe('button tooltip', function () {
    it('wraps the button in a tooltip only when asked', function () {
        expect(domQuery(renderBlade('<atom:button>Save</atom:button>'), '//*[@data-atom-tooltip]'))->toHaveCount(0);

        $html = renderBlade('<atom:button tooltip="Save the record">Save</atom:button>');
        $wrapper = domQuery($html, '//*[@data-atom-tooltip]');

        expect($wrapper)->toHaveCount(1)
            ->and(domQuery($html, '//*[@data-atom-tooltip]/button[@data-atom-button]'))->toHaveCount(1)
            ->and(domQuery($html, '//*[@data-atom-tooltip]/*[@data-atom-tooltip-content]'))->toHaveCount(1)
            ->and(trim(domQuery($html, '//*[@data-atom-tooltip-content]')[0]->textContent))->toBe('Save the record')
            ->and(domClasses($wrapper[0]))->toContain('inline-flex', 'min-w-0', 'max-w-full');
    });

    it('does not print tooltip as an attribute of the button', function () {
        $button = domQuery(renderBlade('<atom:button tooltip="Save the record" icon="add"/>'), '//button')[0];

        expect($button->hasAttribute('tooltip'))->toBeFalse();
    });

    it('makes the wrapper full width for a block button', function () {
        $wrapper = domQuery(renderBlade('<atom:button block tooltip="Save">Save</atom:button>'), '//*[@data-atom-tooltip]')[0];

        expect(domClasses($wrapper))->toContain('flex', 'w-full')->not->toContain('inline-flex');
    });

    it('names a slotless icon button from the tooltip', function () {
        $button = domQuery(renderBlade('<atom:button icon="add" tooltip="Add a contact"/>'), '//button')[0];

        expect($button->getAttribute('aria-label'))->toBe('Add a contact');
    });

    it('lets the caller\'s aria-label win over the tooltip', function () {
        $button = domQuery(renderBlade('<atom:button icon="add" tooltip="Add a contact" aria-label="New contact"/>'), '//button')[0];

        expect($button->getAttribute('aria-label'))->toBe('New contact');
    });

    it('keeps naming an icon button without a tooltip from its icon', function () {
        $button = domQuery(renderBlade('<atom:button icon="add"/>'), '//button')[0];

        expect($button->getAttribute('aria-label'))->toBe('Add');
    });

    it('gives a button with a visible label no aria-label', function () {
        $button = domQuery(renderBlade('<atom:button icon="add" tooltip="Add a contact">Add</atom:button>'), '//button')[0];

        expect($button->hasAttribute('aria-label'))->toBeFalse();
    });

    it('keeps the host\'s attributes on the button, not on the wrapper', function () {
        $html = renderBlade('<atom:button icon="add" tooltip="Add" wire:click="add" x-on:click="open = !open" data-testid="add"/>');
        $button = domQuery($html, '//button')[0];
        $wrapper = domQuery($html, '//*[@data-atom-tooltip]')[0];

        expect($button->getAttribute('wire:click'))->toBe('add')
            ->and($button->getAttribute('x-on:click'))->toBe('open = !open')
            ->and($button->getAttribute('data-testid'))->toBe('add')
            ->and($wrapper->hasAttribute('wire:click'))->toBeFalse()
            ->and($wrapper->hasAttribute('data-testid'))->toBeFalse();
    });

    it('keeps a hostile tooltip inside its attribute and its text node', function () {
        $html = renderBlade('<atom:button icon="add" tooltip=\'"><img src=x onerror=alert(1)>\'/>');

        expect(domQuery($html, '//img'))->toHaveCount(0)
            ->and(domQuery($html, '//button')[0]->getAttribute('aria-label'))->toBe('"><img src=x onerror=alert(1)>')
            ->and(domQuery($html, '//*[@data-atom-tooltip-content]')[0]->textContent)->toContain('"><img src=x onerror=alert(1)>');
    });

    it('adds nothing to a button without a tooltip: the markup ends with the one newline it always did', function (string $tag) {
        $html = renderBlade($tag);

        // a stray newline after the closing tag (an inline sibling then gets a space) is what a wrapper added once
        expect($html)->toStartWith('<')->toEndWith('>'."\n")->not->toEndWith("\n\n")
            ->and($html)->not->toContain('data-atom-tooltip');
    })->with([
        'button' => '<atom:button>Save</atom:button>',
        'icon only' => '<atom:button icon="add"/>',
        'link' => '<atom:button href="/x">Go</atom:button>',
        'submit' => '<atom:button type="submit">Save</atom:button>',
        'block' => '<atom:button block>Save</atom:button>',
    ]);

    it('prints the same button inside the wrapper as it does bare', function () {
        $bare = renderBlade('<atom:button variant="ghost" wire:click="save">Save</atom:button>');
        $wrapped = renderBlade('<atom:button variant="ghost" wire:click="save" tooltip="Save it">Save</atom:button>');
        $button = domQuery($wrapped, '//*[@data-atom-tooltip]/button')[0];

        expect(domQuery($bare, '//button')[0]->getAttribute('class'))->toBe($button->getAttribute('class'))
            ->and(domQuery($bare, '//button')[0]->getAttribute('wire:click'))->toBe($button->getAttribute('wire:click'))
            ->and(domQuery($bare, '//button')[0]->textContent)->toBe($button->textContent);
    });

    it('puts the wire:key on the wrapper as well, so a loop is matched by key and not by position', function () {
        $html = renderBlade('@foreach ([1, 2] as $id)<atom:button icon="add" tooltip="Add" :wire:key="\'row-\'.$id"/>@endforeach');

        $wrappers = domQuery($html, '//*[@data-atom-tooltip]');
        $buttons = domQuery($html, '//*[@data-atom-tooltip]/button');

        expect($wrappers)->toHaveCount(2)
            ->and($wrappers[0]->getAttribute('wire:key'))->toBe('row-1')
            ->and($wrappers[1]->getAttribute('wire:key'))->toBe('row-2')
            // the button keeps its own: keys are matched among siblings only, and the button is its wrapper's only sibling-level node
            ->and($buttons[0]->getAttribute('wire:key'))->toBe('row-1')
            ->and($buttons[1]->getAttribute('wire:key'))->toBe('row-2');
    });

    it('gives the wrapper no wire:key when the button has none', function () {
        $wrapper = domQuery(renderBlade('<atom:button icon="add" tooltip="Add"/>'), '//*[@data-atom-tooltip]')[0];

        expect($wrapper->hasAttribute('wire:key'))->toBeFalse();
    });

    it('renders the tooltip on a link button too', function () {
        $html = renderBlade('<atom:button href="/x" icon="add" tooltip="Go"/>');

        expect(domQuery($html, '//*[@data-atom-tooltip]/a[@data-atom-button]'))->toHaveCount(1)
            ->and(domQuery($html, '//a')[0]->getAttribute('aria-label'))->toBe('Go');
    });
});

describe('button.group', function () {
    it('collapses adjacent button borders', function () {
        $html = renderBlade('<atom:button.group><atom:button>A</atom:button><atom:button>B</atom:button></atom:button.group>');

        expect($html)
            ->toContain('group/group')
            ->toContain('[&amp;_[data-atom-button]:not(:first-child)]:-ml-px');
    });

    it('uses a plain gap layout when gap is set', function () {
        $html = renderBlade('<atom:button.group gap><atom:button>A</atom:button></atom:button.group>');

        expect($html)
            ->toContain('gap-3')
            ->not->toContain('[&amp;_[data-atom-button]:not(:first-child)]:-ml-px');
    });

    it('no longer emits the dead buttons group selector', function () {
        // Regression: index.blade.php carried group-[]/buttons:* classes that
        // had no matching ancestor (the group is named group/group).
        expect(renderBlade('<atom:button>A</atom:button>'))
            ->not->toContain('group-[]/buttons');
    });
});
