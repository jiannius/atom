<?php

use Illuminate\Support\Facades\Blade;

describe('breadcrumbs', function () {
    it('renders the breadcrumbs Alpine factory wired to navigation events', function () {
        $html = Blade::render('<atom:breadcrumbs />');

        expect($html)
            ->toContain('data-atom-breadcrumbs')
            ->toContain('breadcrumbs({ heading: true })')
            ->toContain('x-on:livewire:navigated.window="build()"')
            ->toContain('x-on:navigate-back.window="back()"');
    });

    it('renders the single-crumb heading branch by default', function () {
        $html = Blade::render('<atom:breadcrumbs />');

        // heading prop defaults true → the lone-crumb branch renders a heading
        expect($html)->toContain('breadcrumbs.length === 1');
    });

    it('can suppress the heading branch', function () {
        $html = Blade::render('<atom:breadcrumbs :heading="false" />');

        expect($html)
            ->toContain('data-atom-breadcrumbs')
            ->not->toContain('breadcrumbs.length === 1');
    });
});

describe('the trail length the layout reads', function () {
    $key = \Jiannius\Atom\Atom::CRUMB_COUNT_ATTRIBUTE;

    beforeEach(fn () => request()->attributes->remove(\Jiannius\Atom\Atom::CRUMB_COUNT_ATTRIBUTE));
    afterEach(fn () => request()->attributes->remove(\Jiannius\Atom\Atom::CRUMB_COUNT_ATTRIBUTE));

    it('is the home plus the items, set on the request at mount', function () use ($key) {
        $component = new class () extends \Livewire\Component {
            use \Jiannius\Atom\Traits\WithBreadcrumbs;

            public function breadcrumbs($trail)
            {
                return $trail->home('Home', '/')->push('A', '/a')->push('B', '/a/b');
            }
        };

        $component->mountWithBreadcrumbs();

        expect(request()->attributes->get($key))->toBe(3);
    });

    it('is 1 for a lone home, and 0 when there is no home', function () use ($key) {
        $one = new class () extends \Livewire\Component {
            use \Jiannius\Atom\Traits\WithBreadcrumbs;

            public function breadcrumbs($trail)
            {
                return $trail->home('Home', '/');
            }
        };
        $none = new class () extends \Livewire\Component {
            use \Jiannius\Atom\Traits\WithBreadcrumbs;

            public function breadcrumbs($trail)
            {
                return $trail;
            }
        };

        $one->mountWithBreadcrumbs();
        expect(request()->attributes->get($key))->toBe(1);

        request()->attributes->remove($key);
        $none->mountWithBreadcrumbs();
        expect(request()->attributes->get($key))->toBe(0);
    });

    it('is the first writer\'s: a nested component does not overwrite the page', function () use ($key) {
        $make = fn (int $extra) => new class ($extra) extends \Livewire\Component {
            use \Jiannius\Atom\Traits\WithBreadcrumbs;

            public function __construct(public int $extra = 0) {}

            public function breadcrumbs($trail)
            {
                $trail->home('Home', '/');

                for ($i = 1; $i <= $this->extra; $i++) {
                    $trail->push('Crumb '.$i, '/c'.$i);
                }

                return $trail;
            }
        };

        $make(0)->mountWithBreadcrumbs();
        $make(3)->mountWithBreadcrumbs();

        expect(request()->attributes->get($key))->toBe(1);
    });

    it('is left unset for a component with no breadcrumbs() method', function () use ($key) {
        $component = new class () extends \Livewire\Component {
            use \Jiannius\Atom\Traits\WithBreadcrumbs;
        };

        $component->mountWithBreadcrumbs();

        expect(request()->attributes->has($key))->toBeFalse();
    });
});
