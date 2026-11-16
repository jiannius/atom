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

    it('names the component made for a search and for saved views', function () {
        expect(DesignHost::notes("<atom:table />\n<input wire:model=\"filters.search\">\n<atom:tabs.item wire:click=\"\$set('filters.status', 1)\">a</atom:tabs.item>\n", 'filter-outside-bar'))
            ->toBe([2 => 'binds `filters.search` outside `<atom:table.filters>`: the search is `<atom:table.search>`', 3 => 'binds `filters.status` outside `<atom:table.filters>`: saved views are `<atom:table.views>`']);
    });

    it('reads a handler whose value has an echo, but not one whose key has', function () {
        expect(DesignHost::scanSource("<atom:table />\n<b wire:click=\"\$set('filters.a', '{{ \$v }}')\"></b>\n", 'filter-outside-bar'))->toHaveCount(1)
            ->and(DesignHost::scanSource("<atom:table />\n<b wire:click=\"\$set('filters.a{{ \$v }}', 1)\"></b>\n<b wire:click=\"\$set('filters.{{ \$v }}', 1)\"></b>\n", 'filter-outside-bar'))->toBe([]);
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

    it('reads a variant before max-*, and not a column that is already hideable', function () {
        expect(DesignHost::notes('<atom:table.column class="dark:max-lg:hidden" /><atom:table.column hideable class="max-lg:hidden" />', 'responsive-column'))->toBe([1 => '`dark:max-lg:hidden`']);
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

    it('reads a sticky pager that is bound off, bound to an expression or written empty as the host\'s choice', function () {
        foreach ([':sticky-pager="false"', ':stickyPager="false"', ':sticky-pager=" False "', ':sticky-pager="$pin"', 'x-bind:sticky-pager="false"', 'sticky-pager=""', 'stickyPager=""', 'sticky-pager="{{ $pin }}"', 'sticky-pager="{!! $pin !!}"'] as $attribute) {
            expect(DesignHost::scanSource("<atom:card><atom:table :paginate=\"\$rows\" {$attribute} /></atom:card>", 'panel-overflow-hidden'))->toBe([], $attribute);
        }

        foreach (['sticky-pager', ':sticky-pager="true"', ':sticky-pager=" True "', 'sticky-pager="true"', 'sticky-pager="false"', 'stickyPager="false"'] as $attribute) {
            expect(DesignHost::scanSource("<atom:card><atom:table :paginate=\"\$rows\" {$attribute} /></atom:card>", 'panel-overflow-hidden'))->toHaveCount(1, $attribute);
        }
    });

    it('reads a paginate that is literally off as no paginator, and an expression as one', function () {
        foreach ([':paginate="null"', ':paginate=" NULL "', ':paginate="false"', ':paginate="[]"', ':paginate="array()"', 'paginate=""', ':paginate=""'] as $attribute) {
            expect(DesignHost::scanSource("<atom:card><atom:table {$attribute} /></atom:card>", 'panel-overflow-hidden'))->toBe([], $attribute);
        }

        foreach ([':paginate="$on ? $rows : null"', 'paginate', ':paginate="$rows"'] as $attribute) {
            expect(DesignHost::scanSource("<atom:card><atom:table {$attribute} /></atom:card>", 'panel-overflow-hidden'))->toHaveCount(1, $attribute);
        }
    });

    it('declines a box with a bounded height, a fixed one, and a card that clips', function () use ($table) {
        foreach (['max-h-96', 'h-96', 'h-full', 'h-screen', 'lg:h-[40rem]', 'size-80', 'fixed', 'absolute', 'inset-0', 'inset-x-0'] as $class) {
            expect(DesignHost::scanSource("<div class=\"overflow-hidden {$class}\">{$table}</div>", 'panel-overflow-hidden'))->toBe([], $class);
        }

        foreach (['h-auto', 'h-fit', 'hover:bg-surface-sunken', 'rounded-panel'] as $class) {
            expect(DesignHost::scanSource("<div class=\"overflow-hidden {$class}\">{$table}</div>", 'panel-overflow-hidden'))->toHaveCount(1, $class);
        }

        expect(DesignHost::scanSource("<atom:card class=\"overflow-clip\">{$table}</atom:card>", 'panel-overflow-hidden'))->toBe([])
            ->and(DesignHost::scanSource("<atom:card class=\"h-96\">{$table}</atom:card>", 'panel-overflow-hidden'))->toBe([])
            ->and(DesignHost::scanSource("<dialog class=\"overflow-auto\">{$table}</dialog>", 'panel-overflow-hidden'))->toBe([]);
    });

    it('declines a view whose tags do not balance', function () use ($table) {
        expect(DesignHost::scanSource("<atom:card>\n<div class=\"overflow-auto\">{$table}</div>\n", 'panel-overflow-hidden'))->toBe([]);
    });
});
