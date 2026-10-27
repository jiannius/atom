<?php

$evil = '<img src=x onerror=alert(1)>';

describe('page', function () {
    it('is the only padded block: a 1240px column with the gutters and gap of the design', function () {
        $page = domQuery(renderBlade('<atom:page class="mt-2"><p id="c">x</p></atom:page>'), '//*[@data-atom-page]')[0];

        expect(domClasses($page))->toContain('mx-auto', 'max-w-[var(--atom-page-max,1240px)]', 'pt-[26px]', 'pb-(--atom-page-pb)', '[:where(&)]:[--atom-page-pb:120px]', 'gap-[22px]', 'grid', 'mt-2')
            ->and($page->getElementsByTagName('p')->length)->toBe(1);
    });

    it('has a narrow and a full width', function () {
        $narrow = domQuery(renderBlade('<atom:page width="narrow" />'), '//*[@data-atom-page]')[0];
        $full = domQuery(renderBlade('<atom:page width="full" />'), '//*[@data-atom-page]')[0];
        $other = domQuery(renderBlade('<atom:page width="huge" />'), '//*[@data-atom-page]')[0];

        expect(domClasses($narrow))->toContain('mx-auto', 'max-w-[980px]')
            ->and(array_filter(domClasses($full), fn ($c) => str_starts_with($c, 'max-w-')))->toBeEmpty()
            ->and(domClasses($full))->not->toContain('mx-auto')
            ->and(domClasses($other))->toContain('mx-auto', 'max-w-[var(--atom-page-max,1240px)]');
    });
});

describe('page.header', function () use ($evil) {
    it('prints the title as the page h1, with its status beside it, not inside it', function () {
        $html = renderBlade('<atom:page.header title="Work order WO-1042"><x-slot:status><atom:status tone="success" label="Done" /></x-slot:status></atom:page.header>');
        $h1 = domQuery($html, '//header[@data-atom-page-header]//h1[@data-atom-page-header-title]')[0];

        expect($h1->textContent)->toBe('Work order WO-1042')
            ->and(domClasses($h1))->toContain('text-2xl', 'font-semibold')
            ->and(domQuery($html, '//h1//*[@data-atom-status]'))->toBeEmpty()
            ->and(domQuery($html, '//header//*[@data-atom-status]'))->toHaveCount(1);
    });

    it('prints title, crumb labels and fact labels and values as text', function () use ($evil) {
        $html = renderBlade(
            '<atom:page.header :title="$e" :crumbs="[[\'label\' => $e, \'href\' => \'/a\'], [\'label\' => $e]]" :facts="[[\'label\' => $e, \'value\' => $e], $e]" />',
            ['e' => $evil],
        );

        expect(domQuery($html, '//header//img'))->toBeEmpty()
            ->and(substr_count($html, '&lt;img src=x onerror=alert(1)&gt;'))->toBe(6);
    });

    it('runs every crumb href through safe_url(), and the last crumb is the current page', function () {
        $html = renderBlade('<atom:page.header title="T" :crumbs="$crumbs" />', ['crumbs' => [
            ['label' => 'Safe', 'href' => '/jobs'],
            ['label' => 'Bad', 'href' => 'javascript:alert(1)'],
            ['label' => 'Here', 'href' => '/jobs/1'],
        ]]);

        expect(domQuery($html, '//nav[@data-atom-page-crumbs]//a'))->toHaveCount(1)
            ->and(domQuery($html, '//nav[@data-atom-page-crumbs]//a')[0]->getAttribute('href'))->toBe('/jobs')
            ->and($html)->not->toContain('javascript:')
            ->and(domQuery($html, '//nav[@data-atom-page-crumbs]//*[@aria-current="page"]'))->toHaveCount(1)
            ->and(domQuery($html, '//nav[@data-atom-page-crumbs]//*[@aria-current="page"]')[0]->textContent)->toBe('Here')
            ->and(domQuery($html, '//nav[@data-atom-page-crumbs]//a[@aria-current]'))->toBeEmpty();
    });

    it('prints the current crumb as the record\'s own text, never through the translator', function () {
        // t() is trans_choice(), which splits on a pipe: 'Smith | Sons' came out as 'Smith '
        $html = renderBlade('<atom:page.header title="T" :crumbs="$crumbs" />', ['crumbs' => [
            ['label' => 'Jobs', 'href' => '/jobs'],
            ['label' => 'Smith | Sons'],
        ]]);

        expect(domQuery($html, '//nav[@data-atom-page-crumbs]//*[@aria-current="page"]')[0]->textContent)->toBe('Smith | Sons');
    });

    it('prints a lone string crumb, a pipe included, whole', function () {
        $html = renderBlade('<atom:page.header title="T" :crumbs="[\'A | B\']" />');

        expect(domQuery($html, '//nav[@data-atom-page-crumbs]//*[@aria-current="page"]')[0]->textContent)->toBe('A | B');
    });

    it('prints no heading without a title, so the layout fallback h1 is not hidden', function (mixed $title) {
        $html = renderBlade('<atom:page.header :title="$title"><x-slot:status><atom:status label="Draft" /></x-slot:status></atom:page.header>', ['title' => $title]);

        expect(domQuery($html, '//*[@data-atom-page-header-title]'))->toBeEmpty()
            ->and(domQuery($html, '//h1 | //h2'))->toBeEmpty()
            // the rest of the header still draws
            ->and(domQuery($html, '//header[@data-atom-page-header]//*[@data-atom-status]'))->toHaveCount(1);
    })->with([[null], [''], ['  ']]);

    it('draws no title row with neither a title nor a status, so the crumbs gap is not doubled', function () {
        $none = renderBlade('<atom:page.header :crumbs="[\'A\']" :facts="[\'x\']" />');
        $status = renderBlade('<atom:page.header><x-slot:status><atom:status label="Draft" /></x-slot:status></atom:page.header>');
        $title = renderBlade('<atom:page.header title="T" />');
        $row = '//header/div/div[contains(concat(" ", normalize-space(@class), " "), " gap-y-1.5 ")]';

        expect(domQuery($none, $row))->toBeEmpty()
            ->and(domQuery($status, $row))->toHaveCount(1)
            ->and(domQuery($title, $row))->toHaveCount(1);
    });

    it('has no crumb row without crumbs', function () {
        expect(domQuery(renderBlade('<atom:page.header title="T" />'), '//*[@data-atom-page-crumbs]'))->toBeEmpty();
    });

    it('reads facts as strings or label and value, and skips an empty one', function () {
        $html = renderBlade('<atom:page.header title="T" :facts="$f" />', ['f' => [
            'Customer A',
            ['label' => 'Due', 'value' => '29 Jul'],
            ['label' => 'Ref', 'value' => 'WO-1', 'mono' => true],
            ['label' => 'Empty', 'value' => ''],
            '',
        ]]);
        $meta = domQuery($html, '//*[@data-atom-page-meta]')[0];

        expect($meta->getElementsByTagName('span')->length)->toBe(3)
            ->and($meta->textContent)->toContain('Customer A')->toContain('Due')->toContain('29 Jul')
            ->and($meta->textContent)->not->toContain('Empty')
            ->and(domClasses(domQuery($html, '//*[@data-atom-page-meta]//b[contains(., "WO-1")]')[0]))->toContain('font-id');
    });

    it('draws the meta slot after the facts, and no meta row when there is neither', function () {
        $with = renderBlade('<atom:page.header title="T"><x-slot:meta><span id="m">Free</span></x-slot:meta></atom:page.header>');

        expect(domQuery($with, '//*[@data-atom-page-meta]//*[@id="m"]'))->toHaveCount(1)
            ->and(domQuery(renderBlade('<atom:page.header title="T" />'), '//*[@data-atom-page-meta]'))->toBeEmpty();
    });

    it('puts the primary action last, after the secondary actions and the overflow menu', function () {
        $html = renderBlade(<<<'BLADE'
            <atom:page.header title="T">
                <x-slot:actions><a id="sec" href="/x">Edit</a></x-slot:actions>
                <x-slot:menu><atom:menu.item>Delete</atom:menu.item></x-slot:menu>
                <x-slot:primary><button id="pri" type="button">Mark done</button></x-slot:primary>
            </atom:page.header>
        BLADE);
        $children = domQuery($html, '//*[@data-atom-page-actions]/*');

        expect($children)->toHaveCount(3)
            ->and($children[0]->getAttribute('id'))->toBe('sec')
            ->and($children[1]->hasAttribute('data-atom-dropdown'))->toBeTrue()
            ->and($children[2]->getAttribute('id'))->toBe('pri')
            ->and(domQuery($html, '//*[@data-atom-dropdown]//button[@aria-label="More actions"]'))->toHaveCount(1)
            ->and(domQuery($html, '//*[@data-atom-dropdown]//*[@data-atom-menu][@popover]'))->toHaveCount(1);
    });

    it('sizes the overflow trigger like the header: the control\'s own on a page, sm in a modal or a slide-over', function () {
        $trigger = fn (string $attrs) => domClasses(domQuery(
            renderBlade('<atom:page.header title="T" '.$attrs.'><x-slot:menu><atom:menu.item>Delete</atom:menu.item></x-slot:menu></atom:page.header>'),
            '//button[@aria-label="More actions"]',
        )[0]);

        expect($trigger(''))->toContain('h-control')->not->toContain('h-control-sm')
            ->and($trigger('level="2" size="sm"'))->toContain('h-control-sm')->not->toContain('h-control');
    });

    it('has no action row and no menu when it is given none', function () {
        $html = renderBlade('<atom:page.header title="T" />');

        expect(domQuery($html, '//*[@data-atom-page-actions]'))->toBeEmpty()
            ->and(domQuery($html, '//*[@data-atom-dropdown]'))->toBeEmpty();
    });

    it('renders as a smaller level 2 heading for a modal or a slide-over', function () {
        $html = renderBlade('<atom:page.header title="New job" level="2" size="sm" />');

        expect(domQuery($html, '//h2[@data-atom-page-header-title]'))->toHaveCount(1)
            ->and(domQuery($html, '//h1'))->toBeEmpty()
            ->and(domClasses(domQuery($html, '//h2')[0]))->toContain('text-lg')->not->toContain('text-2xl');
    });

    it('takes an unknown level for 1, and never builds a tag name from it', function () {
        $html = renderBlade('<atom:page.header title="T" level="script" />');

        expect(domQuery($html, '//h1[@data-atom-page-header-title]'))->toHaveCount(1);
    });

    it('renders inside a modal without the page around it', function () {
        $html = renderBlade('<atom:modal name="edit-job"><atom:page.header title="Edit job" level="2" size="sm"><x-slot:status><atom:status label="Draft" /></x-slot:status></atom:page.header></atom:modal>');

        expect(domQuery($html, '//dialog//header[@data-atom-page-header]//h2'))->toHaveCount(1)
            ->and(domQuery($html, '//dialog//*[@data-atom-status]'))->toHaveCount(1);
    });

    it('replaces the layout fallback h1 in atom.css, for a level 1 header only', function () {
        $css = file_get_contents(dirname(__DIR__, 2).'/resources/css/atom.css');

        expect($css)->toContain('[data-atom-main]:has(h1[data-atom-page-header-title]) > [data-atom-page-title]');
    });

    it('prints the bag', function () {
        $header = domQuery(renderBlade('<atom:page.header title="T" id="h" class="mb-2" x-show="ready" />'), '//header')[0];

        expect($header->getAttribute('id'))->toBe('h')->and($header->getAttribute('x-show'))->toBe('ready')->and(domClasses($header))->toContain('mb-2');
    });
});

describe('page.tabs', function () use ($evil) {
    $tabs = [
        ['label' => 'Overview', 'href' => '/jobs/1', 'current' => true],
        ['label' => 'Parts', 'href' => '/jobs/1/parts', 'count' => 4],
        ['label' => 'History', 'href' => 'javascript:alert(1)', 'count' => 0],
    ];

    it('marks the current tab aria-current="page" and only that one', function () use ($tabs) {
        $html = renderBlade('<atom:page.tabs :tabs="$tabs" />', ['tabs' => $tabs]);
        $current = domQuery($html, '//nav[@data-atom-page-tabs]//*[@aria-current]');

        expect($current)->toHaveCount(1)
            ->and($current[0]->getAttribute('aria-current'))->toBe('page')
            ->and($current[0]->textContent)->toContain('Overview')
            ->and(domClasses($current[0]))->toContain('border-fg', 'text-fg');
    });

    it('runs every href through safe_url(): a refused one is plain text, not a link', function () use ($tabs) {
        $html = renderBlade('<atom:page.tabs :tabs="$tabs" />', ['tabs' => $tabs]);

        expect(domQuery($html, '//nav//a'))->toHaveCount(2)
            ->and($html)->not->toContain('javascript:')
            ->and(domQuery($html, '//nav//span[@data-atom-page-tab][contains(., "History")]'))->toHaveCount(1);
    });

    it('marks a current tab aria-current even when it is not a link', function (?string $href) {
        $html = renderBlade('<atom:page.tabs :tabs="$tabs" />', ['tabs' => [
            ['label' => 'Here', 'href' => $href, 'current' => true],
            ['label' => 'There', 'href' => $href],
        ]]);
        $current = domQuery($html, '//nav//*[@aria-current="page"]');

        expect(domQuery($html, '//nav//a'))->toBeEmpty()
            ->and($current)->toHaveCount(1)
            ->and($current[0]->nodeName)->toBe('span')
            ->and($current[0]->textContent)->toContain('Here')
            ->and(domClasses($current[0]))->toContain('border-fg', 'text-fg');
    })->with([[null], ['javascript:alert(1)']]);

    it('keeps the current underline inside the clip of the scrolling row', function () {
        $html = renderBlade('<atom:page.tabs :tabs="$tabs" />', ['tabs' => [['label' => 'A', 'href' => '/a', 'current' => true]]]);

        // the tab's -mb-px underline must land in the ul's padding box (pb-px), not on the border the overflow clip cuts
        expect(domClasses(domQuery($html, '//nav//ul')[0]))->toContain('overflow-x-auto', 'border-b', 'pb-px');
    });

    it('prints counts, a zero included, with tabular figures', function () use ($tabs) {
        $html = renderBlade('<atom:page.tabs :tabs="$tabs" />', ['tabs' => $tabs]);
        $counts = domQuery($html, '//*[@data-atom-page-tab-count]');

        expect($counts)->toHaveCount(2)
            ->and($counts[0]->textContent)->toBe('4')
            ->and($counts[1]->textContent)->toBe('0')
            ->and(domClasses($counts[0]))->toContain('tabular-nums');
    });

    it('prints labels and counts as text', function () use ($evil) {
        $html = renderBlade('<atom:page.tabs :tabs="$t" />', ['t' => [['label' => $evil, 'href' => '/a', 'count' => $evil]]]);

        expect(domQuery($html, '//nav//img'))->toBeEmpty()
            ->and(substr_count($html, '&lt;img src=x onerror=alert(1)&gt;'))->toBe(2);
    });

    it('is a labelled nav landmark', function () use ($tabs) {
        $html = renderBlade('<atom:page.tabs :tabs="$tabs" label="Job sections" />', ['tabs' => $tabs]);

        expect(domQuery($html, '//nav[@aria-label="Job sections"]'))->toHaveCount(1)
            ->and(domQuery(renderBlade('<atom:page.tabs :tabs="$tabs" />', ['tabs' => $tabs]), '//nav[@aria-label="Sections"]'))->toHaveCount(1);
    });
});
