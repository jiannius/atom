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

describe('select uid', function () {
    // A random id changed on every render, so Livewire's morph replaced the popover
    // node and re-evaluated x-data — the listbox then never fetched its options
    // again. The id has to survive a re-render of the same component.
    it('keeps the listbox popover id stable across re-renders', function () {
        $component = new class () {
            public function getId(): string
            {
                return 'component-a';
            }
        };

        $render = fn () => withLivewireContext(
            $component,
            fn () => renderBlade('<atom:select variant="listbox" options="countries" wire:model="pick" />')
        );

        forgetUids();
        $first = $render();

        forgetUids();
        $second = $render();

        preg_match('/id="(atom-select-[^"]+)-list"/', $first, $matches);

        expect($matches[1] ?? null)->not->toBeNull()
            ->and($second)->toContain($matches[1]);
    });

    it('gives two listboxes in the same component different ids', function () {
        forgetUids();

        $html = renderBlade('<div><atom:select variant="listbox" :options="[]" /><atom:select variant="listbox" :options="[]" /></div>');

        preg_match_all('/id="(atom-select-[^"]+)-list"/', $html, $matches);

        expect($matches[1])->toHaveCount(2)
            ->and($matches[1][0])->not->toBe($matches[1][1]);
    });
});
