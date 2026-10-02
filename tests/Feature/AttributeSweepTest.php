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

describe('B2: duplicate or wrong attributes', function () {
    it('prints one class attribute on copy, carrying the caller classes', function () {
        $html = renderBlade('<atom:copy value="abc" class="ml-2">x</atom:copy>');
        $root = domQuery($html, '//*[@x-data]')[0];

        expect(substr_count($html, 'class="'))->toBe(1)
            ->and(domClasses($root))->toContain('contents', 'ml-2');
    });

    it('lets alt, loading and id reach the embed image, and no more than one src', function () {
        $html = renderBlade('<atom:embed src="https://example.test/a.png" alt="A cat" loading="lazy" id="cat" class="rounded" />');
        $imgs = domQuery($html, '//img');

        expect($imgs)->toHaveCount(1);
        expect($imgs[0]->getAttribute('alt'))->toBe('A cat')
            ->and($imgs[0]->getAttribute('loading'))->toBe('lazy')
            ->and($imgs[0]->getAttribute('id'))->toBe('cat')
            ->and($imgs[0]->getAttribute('src'))->toBe('https://example.test/a.png')
            ->and(domClasses($imgs[0]))->toContain('rounded', 'object-contain')
            ->and(substr_count($html, 'src='))->toBe(1);
    });

    it('does not let the embed image bypass its scheme gate', function () {
        $html = renderBlade('<atom:embed src="javascript:alert(1)" alt="x" />');

        expect(domQuery($html, '//img'))->toHaveCount(0)
            ->and($html)->not->toContain('javascript:');
    });

    it('keeps the senangpay alt, width and height on the img and the rest on the wrapper only', function () {
        $html = renderBlade('<atom:logo.senangpay class="h-8" id="pay" />');
        $img = domQuery($html, '//img')[0];
        $figure = domQuery($html, '//figure')[0];

        expect($img->getAttribute('alt'))->toBe('SenangPay')
            ->and($img->getAttribute('width'))->toBe('512')
            ->and($img->getAttribute('height'))->toBe('512')
            ->and($img->hasAttribute('class'))->toBeFalse()
            ->and($img->hasAttribute('id'))->toBeFalse()
            ->and(domClasses($figure))->toContain('h-8')
            ->and($figure->getAttribute('id'))->toBe('pay');
    });

    it('merges a caller style into the single style attribute of a hex badge', function () {
        $html = renderBlade('<atom:badge color="#ff0000" label="Custom" style="margin-left: 4px" class="max-w-sm" id="b" />');
        $badge = domQuery($html, '//*[@data-atom-badge]')[0];

        expect(substr_count($html, 'style='))->toBe(1);
        expect($badge->getAttribute('style'))->toContain('color: #ff0000')
            ->and($badge->getAttribute('style'))->toContain('background-color:')
            ->and($badge->getAttribute('style'))->toEndWith('margin-left: 4px;')
            ->and($badge->getAttribute('id'))->toBe('b')
            ->and(domClasses($badge))->toContain('max-w-sm');
    });

    it('keeps the hex badge colours with no caller style, and a named badge unstyled', function () {
        $hex = domQuery(renderBlade('<atom:badge color="#ff0000" label="Custom" />'), '//*[@data-atom-badge]')[0];
        $named = domQuery(renderBlade('<atom:badge color="green" label="Active" />'), '//*[@data-atom-badge]')[0];

        expect($hex->getAttribute('style'))->toContain('border-color:')
            ->and($hex->getAttribute('style'))->not->toContain('; ;')
            ->and($named->hasAttribute('style'))->toBeFalse();
    });

    it('merges a caller style into the single style attribute of a placeholder bar', function () {
        $html = renderBlade('<atom:placeholder-bar size="45%x10" style="opacity: .5" class="bg-red-200" />');
        $bar = domQuery($html, '//div')[0];

        expect(substr_count($html, 'style='))->toBe(1);
        expect($bar->getAttribute('style'))->toStartWith('width: 45%; height: 10px')
            ->and($bar->getAttribute('style'))->toEndWith('opacity: .5;')
            ->and($bar->hasAttribute('size'))->toBeFalse()
            ->and(domClasses($bar))->toContain('bg-red-200', 'rounded-xl');
    });

    it('sizes a placeholder bar the same with no caller style', function () {
        $bar = domQuery(renderBlade('<atom:placeholder-bar size="40x8" />'), '//div')[0];

        expect(trim($bar->getAttribute('style')))->toBe('width: 40px; height: 8px;');
    });
});

describe('B3: caller class swallowed', function () {
    beforeEach(function () {
        view()->share('errors', new \Illuminate\Support\ViewErrorBag);
    });

    it('puts the slider caller class on the wrapper and none on the range input', function () {
        $html = renderBlade('<atom:slider wire:model="vol" class="mt-4 max-w-sm" />');
        $root = domQuery($html, '//*[@data-atom-slider]')[0];
        $input = domQuery($html, '//input[@type="range"]')[0];

        expect(domClasses($root))->toContain('mt-4', 'max-w-sm', 'group/slider', 'space-y-2')
            ->and($root->getAttribute('wire:model'))->toBe('vol')
            ->and(domClasses($input))->toBe(['w-full', 'text-primary']);
        expect(substr_count(substr($html, 0, strpos($html, '>')), 'class='))->toBe(1);
    });

    it('puts the rating caller class on the wrapper', function () {
        $html = renderBlade('<atom:rating wire:model="stars" class="mt-4" />');
        $root = domQuery($html, '//*[@data-atom-rating]')[0];

        expect(domClasses($root))->toContain('mt-4', 'space-y-2')
            ->and($root->getAttribute('wire:model'))->toBe('stars');
        expect(substr_count(substr($html, 0, strpos($html, '>')), 'class='))->toBe(1);
    });

    it('keeps the slider and rating defaults with no caller class', function () {
        $slider = domQuery(renderBlade('<atom:slider />'), '//*[@data-atom-slider]')[0];
        $rating = domQuery(renderBlade('<atom:rating />'), '//*[@data-atom-rating]')[0];

        expect(domClasses($slider))->toBe(['group/slider', 'space-y-2'])
            ->and(domClasses($rating))->toBe(['space-y-2']);
    });

    it('merges a tab item caller class into its defaults', function () {
        $html = renderBlade('<atom:tabs.item class="text-red-500" value="a">A</atom:tabs.item>');
        $tab = domQuery($html, '//button')[0];

        expect(domClasses($tab))->toContain('text-red-500', 'grow', 'px-4', '-mb-px')
            ->and($tab->getAttribute('type'))->toBe('button');
        expect(substr_count($html, 'class='))->toBe(1);
    });

    it('keeps the tab item defaults with no caller class', function () {
        $tab = domQuery(renderBlade('<atom:tabs.item>A</atom:tabs.item>'), '//button')[0];

        expect(domClasses($tab))->toContain('grow', 'self-stretch', 'px-4')
            ->and(domClasses($tab))->not->toContain('text-red-500');
    });

    it('keeps the caller class on the otp root and off the boxes', function () {
        $html = renderBlade('<atom:input.otp length="4" class="justify-center mt-2" />');
        $root = domQuery($html, '//*[@data-atom-input-otp]')[0];
        $box = domQuery($html, '//input')[0];

        expect(domClasses($root))->toContain('justify-center', 'mt-2', 'flex', 'items-center', 'gap-2')
            ->and(domClasses($box))->not->toContain('justify-center')
            ->and(domClasses($box))->toContain('size-11');
    });

    it('gives the separator label its own classes instead of copying the root class', function () {
        $html = renderBlade('<atom:separator class="py-3">Section</atom:separator>');
        $root = domQuery($html, '//*[@data-atom-separator]')[0];
        $label = domQuery($html, '//span')[0];

        expect(domClasses($root))->toContain('py-3', 'w-full', 'flex')
            ->and(domClasses($label))->toContain('font-medium', 'uppercase', 'text-sm', 'mx-4')
            ->and(domClasses($label))->not->toContain('py-3');
    });

    it('puts the checkbox caller class on the label and keeps wire:model on the sr-only input', function () {
        $html = renderBlade('<atom:checkbox wire:model="agree" label="Agree" class="mt-3" id="agree" />');
        $label = domQuery($html, '//*[@data-atom-checkbox]')[0];
        $input = domQuery($html, '//input')[0];

        expect($label->nodeName)->toBe('label')
            ->and(domClasses($label))->toContain('mt-3', 'group/checkbox', 'inline-block')
            ->and($input->getAttribute('wire:model'))->toBe('agree')
            ->and($input->getAttribute('name'))->toBe('agree')
            ->and($input->getAttribute('id'))->toBe('agree')
            ->and(domClasses($input))->toBe(['sr-only', 'peer']);
        expect($label->hasAttribute('wire:model'))->toBeFalse()
            ->and($label->hasAttribute('id'))->toBeFalse();
    });

    it('puts the toggle caller class on the label and keeps wire:model on the sr-only input', function () {
        $html = renderBlade('<atom:toggle wire:model="on" label="On" class="mt-3" />');
        $label = domQuery($html, '//*[@data-atom-toggle]')[0];
        $input = domQuery($html, '//input')[0];

        expect(domClasses($label))->toContain('mt-3', 'group/toggle', 'inline-block')
            ->and($input->getAttribute('wire:model'))->toBe('on')
            ->and($input->getAttribute('name'))->toBe('on')
            ->and(domClasses($input))->toBe(['peer', 'sr-only']);
    });

    it('puts the radio caller class on the label and keeps value and name on the sr-only input', function () {
        $html = renderBlade('<atom:radio label="A" value="a" name="opt" class="mt-3" />');
        $label = domQuery($html, '//*[@data-atom-radio]')[0];
        $input = domQuery($html, '//input')[0];

        expect(domClasses($label))->toContain('mt-3', 'group/radio', 'inline-block')
            ->and($input->getAttribute('value'))->toBe('a')
            ->and($input->getAttribute('name'))->toBe('opt')
            ->and(domClasses($input))->toBe(['sr-only', 'peer']);
    });

    it('keeps the choice control defaults with no caller class', function () {
        $checkbox = domQuery(renderBlade('<atom:checkbox label="x" />'), '//*[@data-atom-checkbox]')[0];
        $toggle = domQuery(renderBlade('<atom:toggle label="x" />'), '//*[@data-atom-toggle]')[0];
        $radio = domQuery(renderBlade('<atom:radio label="x" />'), '//*[@data-atom-radio]')[0];

        expect(domClasses($checkbox))->toBe(['group/checkbox', 'inline-block', 'space-y-2'])
            ->and(domClasses($toggle))->toBe(['group/toggle', 'inline-block', 'space-y-2'])
            ->and(domClasses($radio))->toBe(['group/radio', 'inline-block']);
    });

    it('puts the uploader caller class on the root and the file attributes on the hidden input', function (string $tag) {
        $html = renderBlade('<atom:'.$tag.' wire:model="photo" accept="image/*" multiple class="mt-3" />');
        $root = domQuery($html, '//*[@x-data][contains(@class, "group/uploader")]')[0];
        $input = domQuery($html, '//input[@type="file"]')[0];

        expect(domClasses($root))->toContain('mt-3', 'group/uploader', 'relative')
            ->and($input->getAttribute('wire:model'))->toBe('photo')
            ->and($input->getAttribute('accept'))->toBe('image/*')
            ->and($input->hasAttribute('multiple'))->toBeTrue()
            ->and(domClasses($input))->toBe(['hidden']);
    })->with(['uploader', 'uploader.dropzone']);

    it('keeps the uploader defaults with no caller class', function (string $tag) {
        $root = domQuery(renderBlade('<atom:'.$tag.' />'), '//*[contains(@class, "group/uploader")]')[0];

        expect(domClasses($root))->toBe(['group/uploader', 'relative']);
    })->with(['uploader', 'uploader.dropzone']);

    it('delivers class to the uploader root through <atom:input type="file">', function () {
        $html = renderBlade('<atom:input type="file" wire:model="photo" class="mt-3" />');
        $root = domQuery($html, '//*[contains(@class, "group/uploader")]')[0];
        $input = domQuery($html, '//input[@type="file"]')[0];

        expect(domClasses($root))->toContain('mt-3')
            ->and(domClasses($input))->toBe(['hidden'])
            ->and($input->getAttribute('wire:model'))->toBe('photo');
    });
});
