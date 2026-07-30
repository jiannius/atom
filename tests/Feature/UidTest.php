<?php

use Illuminate\Support\ViewErrorBag;

beforeEach(function () {
    view()->share('errors', new ViewErrorBag);
});

/**
 * Simulate a fresh request: the per-request numbering is stored on the container.
 */
function forgetUids(): void
{
    app()->forgetInstance('atom.uids');
}

describe('atom uid', function () {
    it('numbers ids per call', function () {
        forgetUids();

        expect(app('atom')->uid())->not->toBe(app('atom')->uid());
    });

    it('applies the given prefix', function () {
        forgetUids();

        expect(app('atom')->uid('atom-select'))->toStartWith('atom-select-');
    });

    it('hands out the same ids again on the next render of the same component', function () {
        $component = new class () {
            public function getId(): string
            {
                return 'component-a';
            }
        };

        forgetUids();
        $first = withLivewireContext($component, fn () => [app('atom')->uid(), app('atom')->uid()]);

        // next request → same component renders the same widgets in the same order
        forgetUids();
        $second = withLivewireContext($component, fn () => [app('atom')->uid(), app('atom')->uid()]);

        expect($second)->toBe($first);
    });

    it('numbers each livewire component separately', function () {
        $a = new class () {
            public function getId(): string
            {
                return 'component-a';
            }
        };

        $b = new class () {
            public function getId(): string
            {
                return 'component-b';
            }
        };

        forgetUids();

        $first = withLivewireContext($a, fn () => app('atom')->uid());
        $second = withLivewireContext($b, fn () => app('atom')->uid());

        expect($first)->not->toBe($second)
            ->and($first)->toContain('component-a')
            ->and($second)->toContain('component-b');
    });
});

// The selects mint their ids client-side with $id instead of calling uid(): any
// id baked into the markup lands in the x-data attribute, and a render that mints
// a different number of ids before this one (a form modal renders one shape on
// load and another on edit()) changes that attribute. Livewire's morph then
// applies it, Alpine re-evaluates x-data, and the subtree's effects stay bound to
// the previous scope — the picker opens on a permanent "No Results".
describe('select ids', function () {
    it('mints the listbox id client-side, not in the markup', function () {
        $html = renderBlade('<atom:select variant="listbox" options="countries" wire:model="pick" />');

        expect($html)
            ->toContain('x-id="[\'atom-select\']"')
            ->toContain('x-bind:id="`${$id(\'atom-select\')}-list`"')
            ->not->toMatch('/\sid="atom-select/');
    });

    it('mints the filter id client-side too', function () {
        $html = renderBlade('<atom:select variant="filter" label="Status" options="countries" wire:model="pick" />');

        expect($html)
            ->toContain('x-id="[\'atom-select\']"')
            ->toContain('x-bind:id="`${$id(\'atom-select\')}-list`"')
            ->not->toMatch('/\sid="atom-select/');
    });

    it('renders a byte-identical x-data on every render', function () {
        $component = new class () {
            public function getId(): string
            {
                return 'component-a';
            }
        };

        // $minted stands in for ids handed out earlier in the render: a form modal
        // renders one shape on load and another on edit(), so the count shifts.
        $xData = function (int $minted) use ($component) {
            forgetUids();

            $html = withLivewireContext($component, function () use ($minted) {
                for ($i = 0; $i < $minted; $i++) {
                    app('atom')->uid('atom-select');
                }

                return renderBlade('<div><atom:select variant="listbox" options="countries" /><atom:select variant="listbox" options="countries" /></div>');
            });

            preg_match_all('/x-data="select\((.*?)\)"/s', $html, $matches);

            return $matches[1];
        };

        expect($xData(0))->toHaveCount(2)
            ->and($xData(8))->toBe($xData(0));
    });
});
