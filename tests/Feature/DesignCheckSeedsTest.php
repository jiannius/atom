<?php

use Jiannius\Atom\Services\DesignCheck;
use Jiannius\Atom\Tests\Support\DesignHost;

/**
 * The notes and messages of the two seed checks. What each finds is held by its fixture host (DesignCheckFixturesTest);
 * these hold what it says, and the corners a fixture line cannot show.
 */

describe('success-button', function () {
    it('names the attribute that carries the tone', function () {
        expect(DesignHost::notes("<atom:button variant=\"success\">a</atom:button>\n<atom:button color=\"info\">b</atom:button>\n", 'success-button'))
            ->toBe([1 => '`variant="success"`', 2 => '`color="info"`']);
    });

    it('is one finding for a button, whichever of its two attributes wears the tone', function () {
        expect(DesignHost::notes('<atom:button variant="success" color="info">a</atom:button>', 'success-button'))->toBe([1 => '`variant="success"`']);
    });

    it('reads the value as written, trimmed and in any case, and not a bound or echoed one', function () {
        expect(DesignHost::notes("<atom:button variant=\" Success \">a</atom:button>\n<atom:button :variant=\"'success'\">b</atom:button>\n<atom:button variant=\"{{ \$v }}\">c</atom:button>\n", 'success-button'))
            ->toBe([1 => '`variant="success"`']);
    });

    it('is a text check: it runs on a view whose tags do not balance', function () {
        $rule = (new DesignCheck)->rules()['success-button'];

        expect($rule->structural())->toBeFalse()->and($rule::SEVERITY)->toBe(DesignCheck::INFO);
    });
});

describe('own-overflow', function () {
    it('names the surface to use, by where the ⋯ sits', function () {
        $dropdown = '<atom:dropdown><atom:button icon="more"/><atom:menu popover/></atom:dropdown>';

        expect(DesignHost::notes("<atom:page.header title=\"a\"><x-slot:actions>{$dropdown}</x-slot:actions></atom:page.header>", 'own-overflow'))->toBe([1 => 'in `page.header`: use its `menu` slot'])
            ->and(DesignHost::notes("<atom:table.toolbar><x-slot:end>{$dropdown}</x-slot:end></atom:table.toolbar>", 'own-overflow'))->toBe([1 => 'in `table.toolbar`: use its `menu` slot'])
            ->and(DesignHost::notes("<atom:card>{$dropdown}</atom:card>", 'own-overflow'))->toBe([1 => 'in a card: use its `menu` slot'])
            ->and(DesignHost::notes("<atom:panel>{$dropdown}</atom:panel>", 'own-overflow'))->toBe([1 => 'in a panel: use its `menu` slot'])
            ->and(DesignHost::notes("<atom:table.row><atom:table.cell>{$dropdown}</atom:table.cell></atom:table.row>", 'own-overflow'))->toBe([1 => 'in a table row: use `<atom:table.actions>`'])
            ->and(DesignHost::notes("<atom:table><x-slot:checked>{$dropdown}</x-slot:checked></atom:table>", 'own-overflow'))->toBe([1 => 'in the bulk bar: use the table\'s `checked-menu` slot'])
            ->and(DesignHost::notes("<div>{$dropdown}</div>", 'own-overflow'))->toBe([1 => 'use the surface\'s `menu` slot']);
    });

    it('takes the nearest surface: a ⋯ in a row inside a card is a row\'s', function () {
        $dropdown = '<atom:dropdown><atom:button icon="more"/></atom:dropdown>';

        expect(DesignHost::notes("<atom:card><atom:table><x-slot:rows><atom:table.row><atom:table.cell>{$dropdown}</atom:table.cell></atom:table.row></x-slot:rows></atom:table></atom:card>", 'own-overflow'))
            ->toBe([1 => 'in a table row: use `<atom:table.actions>`']);
    });

    it('does not take a slot of another component called checked for the bulk bar', function () {
        $dropdown = '<atom:dropdown><atom:button icon="more"/></atom:dropdown>';

        expect(DesignHost::notes("<atom:card><x-slot:checked>{$dropdown}</x-slot:checked></atom:card>", 'own-overflow'))
            ->toBe([1 => 'in a card: use its `menu` slot']);
    });

    it('takes the first button outside the menu for the trigger, however it is wrapped', function () {
        expect(DesignHost::notes('<atom:dropdown><div><atom:button icon="ellipsis"/></div><atom:menu popover><atom:button>Export</atom:button></atom:menu></atom:dropdown>', 'own-overflow'))->toHaveCount(1)
            ->and(DesignHost::notes('<atom:dropdown><atom:button icon="down">Export</atom:button><atom:menu popover><atom:button icon="more"/></atom:menu></atom:dropdown>', 'own-overflow'))->toBe([]);
    });

    it('does not flag a trigger whose content it cannot read', function () {
        expect(DesignHost::notes('<atom:dropdown><atom:button><atom:icon.more/>{{ $label }}</atom:button></atom:dropdown>', 'own-overflow'))->toBe([])
            ->and(DesignHost::notes('<atom:dropdown><atom:button></atom:button></atom:dropdown>', 'own-overflow'))->toBe([])
            ->and(DesignHost::notes('<atom:dropdown><atom:button><atom:icon.more/><atom:icon.add/></atom:button></atom:dropdown>', 'own-overflow'))->toBe([]);
    });

    it('is a review, and a structural check', function () {
        $rule = (new DesignCheck)->rules()['own-overflow'];

        expect($rule->structural())->toBeTrue()->and($rule::SEVERITY)->toBe(DesignCheck::REVIEW);
    });
});
