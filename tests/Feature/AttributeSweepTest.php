<?php

use Illuminate\Pagination\LengthAwarePaginator;

/**
 * The attribute-bag sweep: a component that declares no way for a caller's
 * `class`, `id` or `x-*` to land must print `$attributes` on the element the
 * caller would expect. Every assertion parses the render and reads the element,
 * because a string match cannot tell which element a class landed on.
 */

describe('B1: components that never printed the bag', function () {
    it('prints the bag on every empty variant', function (string $props) {
        $html = renderBlade('<atom:empty id="nothing" class="mt-4" x-show="ready" '.$props.' />');
        $roots = domQuery($html, '//*[@data-atom-empty]');

        expect($roots)->toHaveCount(1);
        expect($roots[0]->getAttribute('id'))->toBe('nothing')
            ->and($roots[0]->getAttribute('x-show'))->toBe('ready')
            ->and(domClasses($roots[0]))->toContain('mt-4');
    })->with([
        'default' => [''],
        'sm' => ['size="sm"'],
        'subtle' => ['subtle'],
    ]);

    it('keeps the empty defaults when the caller passes nothing', function () {
        $default = domQuery(renderBlade('<atom:empty />'), '//*[@data-atom-empty]')[0];
        $sm = domQuery(renderBlade('<atom:empty size="sm" />'), '//*[@data-atom-empty]')[0];
        $subtle = domQuery(renderBlade('<atom:empty subtle />'), '//*[@data-atom-empty]')[0];

        expect(domClasses($default))->toBe(['flex', 'flex-col', 'items-center', 'justify-center', 'gap-3', 'py-8'])
            ->and(domClasses($sm))->toBe(['flex', 'items-center', 'justify-center', 'w-full'])
            ->and(domClasses($subtle))->toContain('bg-zinc-100', 'rounded-lg', 'p-3');
    });

    it('prints the bag on the avatar and keeps the marker avatar.group matches on', function () {
        $html = renderBlade('<atom:avatar name="Jane Doe" id="me" class="ring-2" x-show="ready" />');
        $avatars = domQuery($html, '//*[@data-atom-avatar]');

        expect($avatars)->toHaveCount(1);
        expect($avatars[0]->nodeName)->toBe('figure')
            ->and($avatars[0]->getAttribute('id'))->toBe('me')
            ->and($avatars[0]->getAttribute('x-show'))->toBe('ready')
            ->and(domClasses($avatars[0]))->toContain('ring-2', 'size-10', 'aspect-square');
        // name, src, size and square are props, not DOM attributes
        expect($avatars[0]->hasAttribute('name'))->toBeFalse();
    });

    it('still lets avatar.group find a classed avatar', function () {
        $html = renderBlade('<atom:avatar.group max="1"><atom:avatar name="A B" class="ring-2" /><atom:avatar name="C D" /></atom:avatar.group>');

        expect(domQuery($html, '//*[@data-atom-avatar]'))->toHaveCount(2);
    });

    it('prints the bag on the field wrapper without leaking its label', function () {
        $html = renderBlade('<atom:input.field label="Name" id="f" class="mb-6" x-show="ready">x</atom:input.field>');
        $root = domQuery($html, '//*[contains(concat(" ", normalize-space(@class), " "), " group/field ")]')[0];

        expect($root->getAttribute('id'))->toBe('f')
            ->and($root->getAttribute('x-show'))->toBe('ready')
            ->and($root->hasAttribute('label'))->toBeFalse()
            ->and(domClasses($root))->toContain('mb-6', 'group/field')
            ->and(domClasses($root))->not->toContain('grid');
    });

    it('keeps the inline grid on the field wrapper', function () {
        $html = renderBlade('<atom:input.field label="Name" inline>x</atom:input.field>');
        $root = domQuery($html, '//*[contains(concat(" ", normalize-space(@class), " "), " group/field ")]')[0];

        expect(domClasses($root))->toBe(['group/field', 'grid', 'md:grid-cols-5']);
    });

    it('prints the bag on the standalone pagination nav', function () {
        $p = new LengthAwarePaginator(collect(range(1, 20)), 200, 20, 1);
        $nav = domQuery(renderBlade('<atom:pagination :paginator="$p" id="pg" class="mt-6" />', ['p' => $p]), '//nav')[0];

        expect($nav->getAttribute('id'))->toBe('pg')
            ->and($nav->getAttribute('role'))->toBe('navigation')
            ->and($nav->getAttribute('aria-label'))->not->toBe('')
            ->and($nav->getAttribute('x-on:paginate'))->toContain('scrollIntoView')
            ->and(domClasses($nav))->toContain('mt-6', 'py-2', 'px-4');
    });

    it('prints the bag on the table pagination nav', function () {
        $p = new LengthAwarePaginator(collect(range(1, 20)), 200, 20, 1);
        $nav = domQuery(renderBlade('<atom:table.pagination :paginate="$p" id="pg" class="mt-6" />', ['p' => $p]), '//nav')[0];

        expect($nav->getAttribute('id'))->toBe('pg')
            ->and($nav->getAttribute('role'))->toBe('navigation')
            ->and($nav->getAttribute('x-on:paginate'))->toContain('scrollIntoView')
            ->and(domClasses($nav))->toContain('mt-6', 'py-2', 'px-4');
    });

    it('prints the bag on the table filter bar and keeps its grow default', function () {
        $html = renderBlade('<atom:table.filters id="bar" class="mb-2" x-show="ready">x</atom:table.filters>');
        $root = domQuery($html, '//*[@data-atom-table-filters]')[0];

        expect($root->getAttribute('id'))->toBe('bar')
            ->and($root->getAttribute('x-show'))->toBe('ready')
            ->and(domClasses($root))->toContain('mb-2', 'grow', 'space-y-3')
            // its own x-data and the table-filter:set listener are untouched
            ->and($root->getAttribute('x-data'))->toContain('chips')
            ->and($root->hasAttribute('x-on:table-filter:set.window'))->toBeTrue();
    });

    it('prints the bag on the input prefix wrapper', function () {
        $html = renderBlade('<atom:input.prefix prefix="RM" id="amt" class="max-w-xs"><input></atom:input.prefix>');
        $root = domQuery($html, '//*[@id="amt"]')[0];

        expect(domClasses($root))->toContain('max-w-xs', 'group', 'flex', 'w-full');
    });

    it('prints the bag on the sharer root', function () {
        $html = renderBlade('<atom:sharer url="https://example.test" id="share" class="mt-4" />');
        $root = domQuery($html, '//*[@id="share"]')[0];

        expect(domClasses($root))->toContain('mt-4')
            // the root is the outermost element, not one of the buttons
            ->and($root->parentNode->nodeName)->toBe('body');
    });

    it('prints a table footer slot\'s attributes on the tfoot', function () {
        $html = renderBlade(<<<'BLADE'
            <atom:table :empty="false">
                <x-slot:rows><atom:table.row><atom:table.cell>Jane</atom:table.cell></atom:table.row></x-slot:rows>
                <x-slot:footer id="totals" class="font-bold"><atom:table.row><atom:table.cell>1</atom:table.cell></atom:table.row></x-slot:footer>
            </atom:table>
        BLADE);
        $foot = domQuery($html, '//*[@data-atom-table-footer]')[0];

        expect($foot->nodeName)->toBe('tfoot')
            ->and($foot->getAttribute('id'))->toBe('totals')
            ->and(domClasses($foot))->toContain('font-bold');
    });

    it('prints a table checked slot\'s attributes on the checked-bar actions', function () {
        $html = renderBlade(<<<'BLADE'
            <atom:table :empty="false">
                <x-slot:checked id="bulk" class="justify-end"><button>Delete</button></x-slot:checked>
                <x-slot:rows><atom:table.row><atom:table.cell>Jane</atom:table.cell></atom:table.row></x-slot:rows>
            </atom:table>
        BLADE);
        $actions = domQuery($html, '//*[@id="bulk"]')[0];

        expect(domClasses($actions))->toContain('justify-end', 'grow', 'flex', 'gap-3');
    });
});
