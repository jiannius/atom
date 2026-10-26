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

describe('whether a crumbs slot holds only the trail (the server\'s copy of the browser\'s rule)', function () {
    $only = fn (string $template) => app('atom')->crumbsHoldOnlyTheTrail(renderBlade($template));

    it('is true for the persisted trail, bare or with whitespace and comments around it', function () use ($only) {
        expect($only("@persist('breadcrumbs')<atom:breadcrumbs />@endpersist"))->toBeTrue()
            ->and($only("\n  @persist('breadcrumbs')\n  <atom:breadcrumbs />\n  @endpersist\n"))->toBeTrue()
            ->and($only('<atom:breadcrumbs />'))->toBeTrue()
            ->and($only("{{-- a Blade comment --}}<!-- an HTML one -->\n<atom:breadcrumbs />\n"))->toBeTrue()
            ->and($only('<atom:breadcrumbs :heading="false" />'))->toBeTrue();
    });

    it('is false for a wrapper, persisted or not', function () use ($only) {
        expect($only('<div class="flex"><atom:breadcrumbs /></div>'))->toBeFalse()
            ->and($only("@persist('breadcrumbs')<div><atom:breadcrumbs /></div>@endpersist"))->toBeFalse()
            ->and($only("<div>@persist('breadcrumbs')<atom:breadcrumbs />@endpersist</div>"))->toBeFalse();
    });

    it('is false for anything beside the trail, in either order', function () use ($only) {
        expect($only('<atom:breadcrumbs /><span>Extra</span>'))->toBeFalse()
            ->and($only('<span>Extra</span><atom:breadcrumbs />'))->toBeFalse()
            ->and($only("@persist('breadcrumbs')<atom:breadcrumbs />@endpersist<span>Extra</span>"))->toBeFalse()
            ->and($only("<span>Extra</span>@persist('breadcrumbs')<atom:breadcrumbs />@endpersist"))->toBeFalse()
            ->and($only("@persist('breadcrumbs')<atom:breadcrumbs /><span>Extra</span>@endpersist"))->toBeFalse()
            ->and($only('<atom:breadcrumbs /><atom:breadcrumbs />'))->toBeFalse()
            ->and($only('Home <atom:breadcrumbs />'))->toBeFalse();
    });

    it('is false for static text, an empty slot, a persist around nothing, and non-string input', function () use ($only) {
        expect($only('Static text'))->toBeFalse()
            ->and($only('<span>Static</span>'))->toBeFalse()
            ->and($only(''))->toBeFalse()
            ->and($only("  \n "))->toBeFalse()
            ->and($only('<!-- nothing -->'))->toBeFalse()
            ->and($only("@persist('breadcrumbs')@endpersist"))->toBeFalse()
            ->and(app('atom')->crumbsHoldOnlyTheTrail(null))->toBeFalse()
            ->and(app('atom')->crumbsHoldOnlyTheTrail(new \Illuminate\Support\HtmlString('<atom:breadcrumbs />')))->toBeFalse()
            ->and(app('atom')->crumbsHoldOnlyTheTrail(new \Illuminate\Support\HtmlString(renderBlade('<atom:breadcrumbs />'))))->toBeTrue();
    });

    it('fails closed on malformed HTML and on a trail it cannot find', function () {
        $atom = app('atom');

        expect($atom->crumbsHoldOnlyTheTrail('<div data-atom-breadcrumbs'))->toBeFalse()
            ->and($atom->crumbsHoldOnlyTheTrail('</div><span>x</span>'))->toBeFalse()
            ->and($atom->crumbsHoldOnlyTheTrail("<div x-persist=\"a\"><div data-atom-breadcrumbs></div><b>"))->toBeFalse()
            ->and($atom->crumbsHoldOnlyTheTrail('<<<>>><div'))->toBeFalse()
            ->and($atom->crumbsHoldOnlyTheTrail("\xFF\xFE<div data-atom-breadcrumbs></div>"))->toBeFalse()
            ->and($atom->crumbsHoldOnlyTheTrail('<div title="data-atom-breadcrumbs"></div>'))->toBeFalse();
    });

    it('reads UTF-8 in the trail as it is', function () use ($only) {
        expect($only('<div data-atom-breadcrumbs data-title="Dépôt – 日本語"></div>'))->toBeTrue()
            ->and($only('<div data-atom-breadcrumbs></div>日本語'))->toBeFalse();
    });
});
