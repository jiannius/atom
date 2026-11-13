<?php

describe('dropdown', function () {
    it('wires the dropdown alpine component', function () {
        $html = renderBlade('<atom:dropdown><button>Open</button><atom:menu popover>x</atom:menu></atom:dropdown>');

        expect($html)
            ->toContain('data-atom-dropdown')
            ->toContain('x-data="dropdown({')
            ->toContain('group/dropdown')
            ->toContain("placement: 'bottom-start'") // position + align defaults
            ->toContain('locked: false');
    });

    it('maps position and align to a floating-ui placement', function () {
        $html = renderBlade('<atom:dropdown position="top" align="end"><button>x</button></atom:dropdown>');

        expect($html)->toContain("placement: 'top-end'");
    });

    it('locks open when locked', function () {
        expect(renderBlade('<atom:dropdown locked><button>x</button></atom:dropdown>'))
            ->toContain('locked: true');
    });
});

describe('menu', function () {
    it('renders a menu surface with menu role and data hook', function () {
        $html = renderBlade('<atom:menu><atom:menu.item>One</atom:menu.item></atom:menu>');

        expect($html)
            ->toContain('data-atom-menu')
            ->toContain('role="menu"')
            ->toContain('min-w-48');
    });

    it('becomes a native popover when popover is set', function () {
        expect(renderBlade('<atom:menu popover>x</atom:menu>'))->toContain('data-atom-menu popover');
        expect(renderBlade('<atom:menu>x</atom:menu>'))->not->toContain('popover');
    });
});

describe('menu.item', function () {
    it('renders a button menuitem by default', function () {
        $html = renderBlade('<atom:menu.item>Edit</atom:menu.item>');

        expect($html)
            ->toContain('<button')
            ->toContain('role="menuitem"')
            ->toContain('data-atom-menu-item')
            ->toContain('Edit');
    });

    it('renders a leading icon', function () {
        expect(renderBlade('<atom:menu.item icon="edit">Edit</atom:menu.item>'))
            ->toContain('data-atom-icon');
    });

    it('renders the trailing iconSuffix alongside the leading icon', function () {
        // Regression: the $icon array was clobbered by the leading-icon
        // lookup, so iconSuffix could never render.
        $html = renderBlade('<atom:menu.item icon="edit" iconSuffix="arrow-right">Edit</atom:menu.item>');

        expect(substr_count($html, 'data-atom-icon'))->toBe(2);
    });

    it('renders the iconSuffix even without a leading icon', function () {
        $html = renderBlade('<atom:menu.item iconSuffix="arrow-right">Edit</atom:menu.item>');

        expect(substr_count($html, 'data-atom-icon'))->toBe(1);
    });

    it('renders an anchor when given an href', function () {
        $html = renderBlade('<atom:menu.item href="/foo">Go</atom:menu.item>');

        expect($html)
            ->toContain('<a')
            ->toContain('href="/foo"')
            ->toContain('role="menuitem"');
    });

    it('prints the end slot after the label, before the badge, in a muted small size (#154)', function () {
        $html = renderBlade('<atom:menu.item badge="Fresh"><x-slot:end>Soon</x-slot:end> Billing</atom:menu.item>');
        $end = domQuery($html, '//*[@data-atom-menu-item-end]');

        expect($end)->toHaveCount(1)
            ->and(domClasses($end[0]))->toContain('shrink-0', 'ms-auto', 'text-xs', 'text-fg-subtle')
            ->and(strpos($html, 'Billing'))->toBeLessThan(strpos($html, 'Soon'))
            ->and(strpos($html, 'Soon'))->toBeLessThan(strpos($html, 'Fresh'));
    });

    it('really disables an item that has an href: a disabled button with no link (#154)', function () {
        $html = renderBlade('<atom:menu.item href="/billing" disabled>Billing</atom:menu.item>');

        expect($html)
            ->toContain('<button')
            ->toContain('disabled')
            ->not->toContain('href=')
            ->not->toContain('<a ');

        // and an enabled one, or one with :disabled="false", is still a link
        expect(renderBlade('<atom:menu.item href="/billing">Billing</atom:menu.item>'))->toContain('<a')->toContain('href="/billing"');
        expect(renderBlade('<atom:menu.item href="/billing" :disabled="false">Billing</atom:menu.item>'))->toContain('<a')->toContain('href="/billing"');
    });

    it('tints danger variants', function () {
        expect(renderBlade('<atom:menu.item variant="danger">D</atom:menu.item>'))
            ->toContain('text-danger-fg')
            ->toContain('hover:bg-danger-bg');
        expect(renderBlade('<atom:menu.item variant="warning">W</atom:menu.item>'))
            ->toContain('text-warning-fg')
            ->toContain('hover:bg-warning-bg');
    });

    it('auto-wires the delete confirm flow', function () {
        $html = renderBlade('<atom:menu.item variant="delete">Delete</atom:menu.item>');

        expect($html)
            ->toContain('atom.confirm({')
            ->toContain('x-on:confirmed="$wire.delete()"')
            ->toContain('data-atom-icon'); // the delete icon
    });

    it('does not auto-wire the confirm flow when the caller sets its own click', function () {
        expect(renderBlade('<atom:menu.item variant="delete" wire:click="remove">Delete</atom:menu.item>'))
            ->not->toContain('atom.confirm({');
    });

    it('renders a badge', function () {
        expect(renderBlade('<atom:menu.item badge="3">Team</atom:menu.item>'))->toContain('3');
    });

    it('does not freeze a popover menu against a morph with a static wire:ignore.self', function () {
        // An open popover keeps its own attributes through a morph by a morph.updating hook
        // (helpers/morphed.js), so a closed menu still takes server-driven attributes (issue #56).
        expect(renderBlade('<atom:menu popover>x</atom:menu>'))->not->toContain('wire:ignore');
        expect(renderBlade('<atom:menu>x</atom:menu>'))->not->toContain('wire:ignore');
    });

    it('does not freeze the dropdown root against a morph', function () {
        // Its open state is written back from the popover after a morph instead (dropdown.js), so a
        // server-driven class on the root keeps updating. wire:ignore.self here would stop that.
        expect(renderBlade('<atom:dropdown><button>Open</button><atom:menu popover>x</atom:menu></atom:dropdown>'))
            ->toContain('data-atom-dropdown')
            ->not->toMatch('/<div[^>]*x-data="dropdown[^>]*wire:ignore/s');
    });
});

it('draws <atom:menu.separator> as a hairline separator that is not a menu item', function () {
    $html = renderBlade('<atom:menu><atom:menu.item>A</atom:menu.item><atom:menu.separator class="x"/><atom:menu.item>B</atom:menu.item></atom:menu>');
    $separator = domQuery($html, '//*[@role="menu"]/*[@role="separator"]');

    expect($separator)->toHaveCount(1)
        ->and($separator[0]->hasAttribute('data-atom-menu-separator'))->toBeTrue()
        ->and($separator[0]->hasAttribute('data-atom-menu-item'))->toBeFalse()
        ->and(domClasses($separator[0]))->toContain('h-px', 'bg-border', 'x')
        ->and(domQuery($html, '//*[@role="menu"]/*[@data-atom-menu-item]'))->toHaveCount(2);
});
