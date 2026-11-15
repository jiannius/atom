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

describe('a fixed trail (`items`)', function () {
    // the JSON the tag prints for the engine, decoded from the attribute the way a browser reads it
    $trail = function (string $html): ?array {
        $nodes = domQuery($html, '//*[@data-atom-breadcrumbs][@data-atom-breadcrumbs-items]');

        return count($nodes) === 1 ? json_decode($nodes[0]->getAttribute('data-atom-breadcrumbs-items'), true) : null;
    };

    beforeEach(function () {
        // the shells' search input reads $errors, which the session middleware shares in an app
        view()->share('errors', new \Illuminate\Support\ViewErrorBag);
        request()->attributes->remove(\Jiannius\Atom\Atom::CRUMB_COUNT_ATTRIBUTE);
    });
    afterEach(fn () => request()->attributes->remove(\Jiannius\Atom\Atom::CRUMB_COUNT_ATTRIBUTE));

    it('prints no items attribute without `items`, so the component keeps its own trail', function () {
        expect(domQuery(renderBlade('<atom:breadcrumbs />'), '//*[@data-atom-breadcrumbs-items]'))->toHaveCount(0);
    });

    it('prints the header\'s shape as JSON: the first item is the root, a string is a label with no link, the last has no link', function () use ($trail) {
        $html = renderBlade('<atom:breadcrumbs :items="[[\'label\' => \'Jobs\', \'href\' => \'/jobs\'], \'Open\', [\'label\' => \'WO-1042\', \'href\' => \'/jobs/1\']]" />');

        expect($trail($html))->toBe([
            ['title' => 'Jobs', 'url' => '/jobs', 'home' => true],
            ['title' => 'Open', 'url' => null, 'home' => false],
            ['title' => 'WO-1042', 'url' => null, 'home' => false],
        ])
            // the component draws the same engine: the factory is still a constant and the list is there
            ->and($html)->toContain('breadcrumbs({ heading: true })', 'data-atom-crumb-list');
    });

    it('escapes the JSON in the attribute: a label cannot close it or open a tag', function () use ($trail) {
        $label = '"><script>alert(1)</script> \' & </ol>';
        $html = renderBlade('<atom:breadcrumbs :items="$items" />', ['items' => [['label' => 'Home', 'href' => '/'], ['label' => $label]]]);

        // the page holds exactly one such element, none of the label's markup is live, and the browser reads back the label as it was
        expect(domQuery($html, '//script'))->toHaveCount(0)
            ->and(substr_count($html, 'data-atom-breadcrumbs-items='))->toBe(1)
            ->and($html)->not->toContain('<script>alert(1)</script>')
            ->and($trail($html)[1]['title'])->toBe($label);
    });

    it('drops a javascript: href, leaving a crumb with no link', function () use ($trail) {
        $html = renderBlade('<atom:breadcrumbs :items="[[\'label\' => \'Home\', \'href\' => \'javascript:alert(1)\'], [\'label\' => \'Mid\', \'href\' => \' JaVa&#09;script:alert(1)\'], [\'label\' => \'Safe\', \'href\' => \'/safe\'], [\'label\' => \'Now\']]" />');

        expect($html)->not->toContain('javascript')->not->toContain('JaVa')
            ->and(array_column($trail($html), 'url'))->toBe([null, null, '/safe', null]);
    });

    it('runs every label but the last through t(), and prints the last as data', function () use ($trail) {
        // t() splits on a pipe: a parent label is a translation key, the current page is the record's own text
        $html = renderBlade('<atom:breadcrumbs :items="[[\'label\' => \'Jobs|Many jobs\', \'href\' => \'/jobs\'], [\'label\' => \'WO|1042\']]" />');

        expect(array_column($trail($html), 'title'))->toBe(['Jobs', 'WO|1042']);
    });

    it('translates a parent crumb with the app\'s translations', function () use ($trail) {
        app('translator')->setLoaded(['*' => ['*' => [app()->getLocale() => ['Jobs' => 'Kerja']]]]);

        expect(array_column($trail(renderBlade('<atom:breadcrumbs :items="[[\'label\' => \'Jobs\', \'href\' => \'/jobs\'], [\'label\' => \'Jobs\']]" />')), 'title'))->toBe(['Kerja', 'Jobs']);
    });

    it('takes a Stringable label (a Str, an HtmlString) and a number as text, never drops it', function () use ($trail) {
        $html = renderBlade('<atom:breadcrumbs :items="$items" />', ['items' => [
            ['label' => str('Jobs'), 'href' => '/jobs'],
            new \Illuminate\Support\HtmlString('Open <b>jobs</b>'),
            ['label' => new \Illuminate\Support\HtmlString('WO-<i>1042</i>')],
        ]]);

        // text in the attribute, escaped there, and printed by x-text: markup in a label is never markup
        expect(array_column($trail($html), 'title'))->toBe(['Jobs', 'Open <b>jobs</b>', 'WO-<i>1042</i>'])
            ->and(array_column($trail($html), 'url'))->toBe(['/jobs', null, null])
            ->and($html)->not->toContain('<b>jobs</b>');

        expect(array_column($trail(renderBlade('<atom:breadcrumbs :items="[[\'label\' => 7, \'href\' => \'/a\'], 5, [\'label\' => 12]]" />')), 'title'))->toBe(['7', '5', '12']);
    });

    it('reads a model or a collection item for its label and href, never as the text of its JSON', function () use ($trail) {
        $model = new class () extends \Illuminate\Database\Eloquent\Model {
            protected $guarded = [];
        };
        $model->forceFill(['label' => 'Jobs', 'href' => '/jobs', 'secret_note' => 'TOP-SECRET-VALUE']);

        $html = renderBlade('<atom:breadcrumbs :items="$items" />', ['items' => [$model, collect(['label' => 'Open', 'href' => '/open', 'secret_note' => 'TOP-SECRET-VALUE']), ['label' => 'Now']]]);

        expect(array_column($trail($html), 'title'))->toBe(['Jobs', 'Open', 'Now'])
            ->and(array_column($trail($html), 'url'))->toBe(['/jobs', '/open', null])
            ->and($html)->not->toContain('TOP-SECRET-VALUE')->not->toContain('secret_note');
    });

    it('drops blank labels and a nothing-trail, and accepts a collection', function () use ($trail) {
        $html = renderBlade('<atom:breadcrumbs :items="$items" />', ['items' => collect([['label' => 'Home', 'href' => '/'], ['label' => ''], ['href' => '/x'], ['label' => 'Now']])]);

        expect(array_column($trail($html), 'title'))->toBe(['Home', 'Now'])
            ->and($trail(renderBlade('<atom:breadcrumbs :items="[]" />')))->toBe([])
            ->and($trail(renderBlade('<atom:breadcrumbs :items="\'not a list\'" />')))->toBe([]);
    });

    it('tells the layouts how long the trail is, like WithBreadcrumbs does (the first writer wins)', function () {
        $key = \Jiannius\Atom\Atom::CRUMB_COUNT_ATTRIBUTE;

        renderBlade('<atom:breadcrumbs :items="[[\'label\' => \'Home\', \'href\' => \'/\'], [\'label\' => \'A\', \'href\' => \'/a\'], [\'label\' => \'B\']]" />');
        expect(request()->attributes->get($key))->toBe(3);

        renderBlade('<atom:breadcrumbs :items="[[\'label\' => \'Only\']]" />');
        expect(request()->attributes->get($key))->toBe(3);

        request()->attributes->remove($key);
        renderBlade('<atom:breadcrumbs :items="[]" />');
        expect(request()->attributes->get($key))->toBe(0);

        // a component's trail leaves the count alone
        request()->attributes->remove($key);
        renderBlade('<atom:breadcrumbs />');
        expect(request()->attributes->has($key))->toBeFalse();
    });

    it('is what the shell reads to leave out the empty bar for one crumb above a page.header, with no component', function (string $layout) {
        $render = fn (array $items, bool $pageHeader) => renderBlade('<atom:'.$layout." :vite=\"false\">\n<x-slot:crumbs>\n<atom:breadcrumbs :items=\"\$items\" />\n</x-slot>\n"
            .($pageHeader ? '<atom:page.header title="Invoices" />' : '<p>Body</p>').'</atom:'.$layout.'>', ['items' => $items]);
        $node = $layout === 'layouts.sidebar' ? '//header[@data-atom-header]' : '//*[@data-atom-header-crumbs]';
        $empty = fn (string $html) => count(domQuery($html, $node.'[@data-atom-header-empty]')) === 1;

        request()->attributes->remove(\Jiannius\Atom\Atom::CRUMB_COUNT_ATTRIBUTE);
        expect($empty($render([['label' => 'Invoices']], true)))->toBeTrue();

        request()->attributes->remove(\Jiannius\Atom\Atom::CRUMB_COUNT_ATTRIBUTE);
        expect($empty($render([['label' => 'Invoices']], false)))->toBeFalse();

        request()->attributes->remove(\Jiannius\Atom\Atom::CRUMB_COUNT_ATTRIBUTE);
        expect($empty($render([['label' => 'Sales', 'href' => '/sales'], ['label' => 'Invoices']], true)))->toBeFalse();
    })->with(['layouts.sidebar', 'layouts.header']);
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

        // only shapes that libxml 2.9 and 2.14+ answer alike: an unterminated tag is dropped by one and kept by the other
        expect($atom->crumbsHoldOnlyTheTrail('</div><span>x</span>'))->toBeFalse()
            ->and($atom->crumbsHoldOnlyTheTrail("<div x-persist=\"a\"><div data-atom-breadcrumbs></div><b>"))->toBeFalse()
            ->and($atom->crumbsHoldOnlyTheTrail('<<<>>><div'))->toBeFalse()
            ->and($atom->crumbsHoldOnlyTheTrail("\xFF\xFE<div data-atom-breadcrumbs></div>"))->toBeFalse()
            ->and($atom->crumbsHoldOnlyTheTrail('<div title="data-atom-breadcrumbs"></div>'))->toBeFalse();
    });

    it('ignores a processing instruction like a comment, as libxml 2.9 and 2.14+ would otherwise answer differently', function () {
        expect(app('atom')->crumbsHoldOnlyTheTrail('<div data-atom-breadcrumbs></div><?php echo 1 ?>'))->toBeTrue();
    });

    it('reads UTF-8 in the trail as it is', function () use ($only) {
        expect($only('<div data-atom-breadcrumbs data-title="Dépôt – 日本語"></div>'))->toBeTrue()
            ->and($only('<div data-atom-breadcrumbs></div>日本語'))->toBeFalse();
    });
});
