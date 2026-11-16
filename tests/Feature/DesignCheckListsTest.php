<?php

use Jiannius\Atom\Services\DesignCheck;
use Jiannius\Atom\Tests\Support\DesignHost;

/**
 * The notes and corners of the four list checks. What each finds is held by its fixture host (DesignCheckFixturesTest).
 */

describe('filter-outside-bar', function () {
    it('names the key that is bound', function () {
        expect(DesignHost::notes("<atom:table />\n<input wire:model.live=\"filters.status.0\">\n", 'filter-outside-bar'))
            ->toBe([2 => 'binds `filters.status.0` outside `<atom:table.filters>`']);
    });

    it('reads nothing in a view with no table', function () {
        expect(DesignHost::scanSource('<input wire:model="filters.status"><button wire:click="$set(\'filters.a\', 1)">a</button>', 'filter-outside-bar'))->toBe([]);
    });

    it('is one finding for a tag, whichever of its attributes binds a filter', function () {
        expect(DesignHost::notes("<atom:table />\n<button wire:model=\"filters.a\" wire:click=\"\$set('filters.b', 1)\">a</button>\n", 'filter-outside-bar'))
            ->toBe([2 => 'binds `filters.a` outside `<atom:table.filters>`']);
    });

    it('does not read a model that is a comparison, a sub-path of another property or an echo', function () {
        expect(DesignHost::scanSource("<atom:table />\n<input wire:model=\"filters\">\n<input wire:model=\"my.filters.a\">\n<input wire:model=\"filters.{{ \$k }}\">\n<b x-on:click=\"\$wire.filters.a === 1\"></b>\n", 'filter-outside-bar'))->toBe([]);
    });

    it('declines a view whose tags do not balance', function () {
        expect((new DesignCheck)->rules()['filter-outside-bar']->structural())->toBeTrue()
            ->and(DesignHost::scanSource("<atom:table />\n<atom:card>\n<input wire:model=\"filters.a\">\n", 'filter-outside-bar'))->toBe([]);
    });
});

describe('filters-chips', function () {
    it('is an info', function () {
        expect((new DesignCheck)->rules()['filters-chips']::SEVERITY)->toBe(DesignCheck::INFO);
    });

    it('reads a bound or bare chips as a choice', function () {
        expect(DesignHost::scanSource('<atom:table.toolbar><atom:table.filters :chips="$c" /><atom:table.filters chips /></atom:table.toolbar>', 'filters-chips'))->toBe([]);
    });
});

describe('responsive-column', function () {
    it('names the classes', function () {
        expect(DesignHost::notes("<atom:table.column class=\"hidden 2xl:table-cell\" />\n<atom:table.cell class=\"max-xl:hidden\" />\n", 'responsive-column'))
            ->toBe([1 => '`hidden 2xl:table-cell`', 2 => '`max-xl:hidden`']);
    });

    it('does not read the class of a plain table, a conditional class or a variant that is not a column\'s', function () {
        expect(DesignHost::scanSource('<th class="hidden lg:table-cell"></th><atom:table.column :class="$x ? \'max-lg:hidden\' : null" /><atom:table.column class="hidden-lg:table-cell" /><atom:table.column class="lg:table-cell" />', 'responsive-column'))->toBe([]);
    });
});

describe('panel-overflow-hidden', function () {
    $table = '<atom:table :paginate="$rows" />';

    it('is a break, and is reported at the box with the table\'s line', function () use ($table) {
        expect((new DesignCheck)->rules()['panel-overflow-hidden']::SEVERITY)->toBe(DesignCheck::BREAK)
            ->and(DesignHost::notes("<div class=\"overflow-hidden\">\n{$table}\n</div>", 'panel-overflow-hidden'))
            ->toBe([1 => 'holds the pager of the table on line 2, and it scrolls or hides its overflow: use `<atom:panel>`']);
    });

    it('is one finding for a box with two tables in it', function () use ($table) {
        expect(DesignHost::scanSource("<div class=\"overflow-auto\">{$table}{$table}</div>", 'panel-overflow-hidden'))->toHaveCount(1);
    });

    it('reports the nearest box only, when two around a table scroll', function () use ($table) {
        expect(DesignHost::scanSource("<div class=\"overflow-auto\"><atom:card>{$table}</atom:card></div>", 'panel-overflow-hidden'))->toHaveCount(1);
    });

    it('looks through a panel, which clips, to a box that scrolls', function () use ($table) {
        expect(DesignHost::scanSource("<div class=\"overflow-hidden\"><atom:panel>{$table}</atom:panel></div>", 'panel-overflow-hidden'))->toHaveCount(1)
            ->and(DesignHost::scanSource("<atom:panel>{$table}</atom:panel>", 'panel-overflow-hidden'))->toBe([]);
    });

    it('reads a panel that is given overflow-hidden as the box', function () use ($table) {
        expect(DesignHost::scanSource("<atom:panel class=\"overflow-hidden\">{$table}</atom:panel>", 'panel-overflow-hidden'))->toHaveCount(1);
    });

    it('stops at a modal, which is its own scroll box', function () use ($table) {
        expect(DesignHost::scanSource("<div class=\"overflow-auto\"><atom:modal>{$table}</atom:modal></div>", 'panel-overflow-hidden'))->toBe([])
            ->and(DesignHost::scanSource("<atom:modal><div class=\"overflow-auto\">{$table}</div></atom:modal>", 'panel-overflow-hidden'))->toHaveCount(1);
    });

    it('reads a sticky pager that is off, bound or written empty as the host\'s choice', function () {
        foreach (['sticky-pager="false"', ':sticky-pager="false"', ':sticky-pager="$pin"', 'sticky-pager=""'] as $attribute) {
            expect(DesignHost::scanSource("<atom:card><atom:table :paginate=\"\$rows\" {$attribute} /></atom:card>", 'panel-overflow-hidden'))->toBe([], $attribute);
        }

        foreach (['sticky-pager', ':sticky-pager="true"', 'sticky-pager="true"'] as $attribute) {
            expect(DesignHost::scanSource("<atom:card><atom:table :paginate=\"\$rows\" {$attribute} /></atom:card>", 'panel-overflow-hidden'))->toHaveCount(1, $attribute);
        }
    });

    it('declines a view whose tags do not balance', function () use ($table) {
        expect(DesignHost::scanSource("<atom:card>\n<div class=\"overflow-auto\">{$table}</div>\n", 'panel-overflow-hidden'))->toBe([]);
    });
});
