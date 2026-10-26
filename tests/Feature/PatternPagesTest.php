<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ViewErrorBag;
use Jiannius\Atom\Services\Docs;

beforeEach(fn () => view()->share('errors', new ViewErrorBag));

/**
 * The docs routes exist in the local environment only: stand-ins, so a view that links to them renders.
 */
function registerDocsRoutes(): void
{
    if (! Route::has('atom.docs.patterns')) {
        Route::get('/atom/docs', fn () => '')->name('atom.docs');
        Route::get('/atom/docs/patterns', fn () => '')->name('atom.docs.patterns');
        Route::get('/atom/docs/{component}', fn () => '')->name('atom.docs.show');
        Route::get('/atom/docs/patterns/{pattern}', fn () => '')->name('atom.docs.pattern');
        app('router')->getRoutes()->refreshNameLookups();
    }
}

// The pattern pages in /atom/docs: whole screens in each app shell, built from one shared nav partial.
describe('pattern pages', function () {
    it('has a view for every listed pattern', function (string $name) {
        expect(view()->exists('atom::docs.patterns.'.$name))->toBeTrue();
    })->with(array_keys(Docs::PATTERNS));

    it('renders each pattern in its shell', function (string $name, string $shell) {
        $html = view('atom::docs.patterns.'.$name)->render();

        expect(domQuery($html, '//*[@data-atom-nav-shell="'.$shell.'"]//a[@data-atom-navlist-item]'))->not->toBeEmpty()
            ->and(domQuery($html, '//main[@data-atom-main]//*[@data-testid="pattern-page"]'))->toHaveCount(1);
    })->with([
        'sidebar' => ['app-shell-sidebar', 'sidebar'],
        'header' => ['app-shell-header', 'header'],
    ]);

    it('draws the very same nav markup in both shells', function () {
        $navOf = function (string $name): string {
            $html = view('atom::docs.patterns.'.$name)->render();
            $nav = domQuery($html, '//*[@data-atom-nav-shell][1]//nav[@data-atom-navlist]')[0];

            return $nav->ownerDocument->saveHTML($nav);
        };

        expect($navOf('app-shell-header'))->toBe($navOf('app-shell-sidebar'));
    });

    it('demos the same slots in both shells, built from atom components', function (string $name) {
        $html = view('atom::docs.patterns.'.$name)->render();

        expect(domQuery($html, '//*[@data-atom-shell-crumbs][contains(., "Invoices")]'))->not->toBeEmpty()
            ->and(domQuery($html, '//*[@data-atom-shell-context]//button'))->not->toBeEmpty()
            ->and(domQuery($html, '//*[@data-atom-shell-search]//input'))->not->toBeEmpty()
            ->and(domQuery($html, '//main//*[@data-atom-table]//*[@data-atom-status]'))->toHaveCount(4);
    })->with(['app-shell-sidebar', 'app-shell-header']);

    it('draws the shell pages\' content as the list page does: status, a frameless panel, numeric money, the primary in the header', function (string $name) {
        $html = view('atom::docs.patterns.'.$name)->render();
        $header = '//main//*[@data-atom-page]/header[@data-atom-page-header]';

        expect(domQuery($html, '//main//*[@data-atom-badge]'))->toBeEmpty()
            ->and(domQuery($html, '//main//*[@data-atom-table][@data-atom-table-frameless]'))->toHaveCount(1)
            ->and(domClasses(domQuery($html, '//main//*[@data-atom-table]/..')[0]))->toContain('rounded-panel', 'overflow-clip')
            // money: numeric on the column and the cell, named once in the header
            ->and(trim(domQuery($html, '//main//thead//th[contains(@class, "text-right")]')[0]->textContent))->toBe('Amount (RM)')
            ->and(domQuery($html, '//main//tbody/tr/td[contains(@class, "tabular-nums")]'))->toHaveCount(4)
            ->and(domQuery($html, '//main//tbody//td[contains(@class, "tabular-nums")][contains(@class, "text-right")]'))->toHaveCount(4)
            // the page's one primary is the page header's, and the shell's actions slot holds only a secondary
            ->and(domQuery($html, $header.'//*[@data-atom-page-actions]//*[@data-testid="shell-primary"]'))->toHaveCount(1)
            ->and(domQuery($html, '//*[@data-atom-shell-actions]//*[@data-atom-button][contains(concat(" ", normalize-space(@class), " "), " bg-accent ")]'))->toBeEmpty()
            ->and(domQuery($html, '//*[@data-atom-shell-actions]//*[@data-atom-button]'))->not->toBeEmpty()
            ->and(domQuery($html, '//*[@data-atom-button][not(ancestor::template)][contains(concat(" ", normalize-space(@class), " "), " bg-accent ")]'))->toHaveCount(1);
    })->with(['app-shell-sidebar', 'app-shell-header']);

    it('shows a brand with a subline and a shortcut hint on the sidebar\'s search, as host markup', function () {
        $html = view('atom::docs.patterns.app-shell-sidebar')->render();

        expect(trim(domQuery($html, '//*[@data-testid="brand-subline"]')[0]->textContent))->toContain('Pro plan')
            ->and(domQuery($html, '//*[@data-atom-shell-search]//*[@data-testid="shell-search"]//input'))->toHaveCount(1)
            ->and(domQuery($html, '//*[@data-testid="shell-search"]//kbd'))->not->toBeEmpty()
            ->and(domQuery($html, '//*[@data-testid="shell-search"]')[0]->textContent)->toContain('⌘');
    });

    it('does not choke on a page query that is an array', function (string $name) {
        request()->query->set('page', ['x']);

        expect(fn () => view('atom::docs.patterns.'.$name)->render())->not->toThrow(Throwable::class);
    })->with(['app-shell-sidebar', 'app-shell-header']);

    it('marks the current page from the query string, in both shells', function (string $name) {
        request()->query->set('page', 'customers');

        $html = view('atom::docs.patterns.'.$name)->render();
        $current = domQuery($html, '//*[@data-atom-navlist-item][@aria-current="page"]');

        expect($current)->toHaveCount(1)->and($current[0]->getAttribute('href'))->toBe('?page=customers');
    })->with(['app-shell-sidebar', 'app-shell-header']);
});

// The patterns overview: every page under docs/patterns, with when to use it.
describe('patterns overview', function () {
    it('accounts for every file in the patterns directory: a page, or a partial the shells share', function () {
        $files = collect(scandir(dirname(__DIR__, 2).'/resources/views/docs/patterns'))
            ->filter(fn ($file) => str_ends_with($file, '.blade.php'))
            ->map(fn ($file) => str($file)->before('.blade.php')->toString())
            ->sort()->values()->all();

        expect($files)->toBe(collect([...array_keys(Docs::PATTERNS), ...array_keys(Docs::PATTERN_PARTIALS)])->sort()->values()->all())
            ->and(array_keys(Docs::PATTERN_NOTES))->toBe(array_keys(Docs::PATTERNS));

        foreach (Docs::PATTERN_NOTES as $name => $note) {
            expect($note['use'])->toBeString()->not->toBeEmpty($name)
                ->and($note['try'])->toStartWith('?', $name);
        }
    });

    it('lists every pattern page with a link, when to use it and the file to read', function () {
        registerDocsRoutes();

        $html = view('atom::docs.patterns-index')->render();

        foreach (Docs::PATTERNS as $name => $label) {
            $card = domQuery($html, '//*[@data-testid="pattern-'.$name.'"]');

            expect($card)->toHaveCount(1, $name)
                ->and(domQuery($html, '//*[@data-testid="pattern-'.$name.'"]//a[contains(@href, "/atom/docs/patterns/'.$name.'")]'))->toHaveCount(1, $name)
                ->and($card[0]->textContent)->toContain(Docs::PATTERN_NOTES[$name]['use'])
                ->toContain('vendor/jiannius/atom/resources/views/docs/patterns/'.$name.'.blade.php');
        }

        foreach (array_keys(Docs::PATTERN_PARTIALS) as $name) {
            expect($html)->toContain('docs/patterns/'.$name.'.blade.php');
        }
    });

    it('is in the docs nav and on the docs index', function () {
        registerDocsRoutes();

        $nav = renderBlade('<atom:docs.layout>x</atom:docs.layout>');
        $index = view('atom::docs.index', ['docs' => new Docs])->render();

        // the overview's own url, not a pattern page's: the link ends there
        $overview = 'a[substring(@href, string-length(@href) - 18) = "/atom/docs/patterns"]';

        // once in the nav, and once in the index page's own text
        expect(domQuery($nav, '//'.$overview))->toHaveCount(1)
            ->and(domQuery($index, '//main//'.$overview))->toHaveCount(1);
    });

    it('is the guide the Boost skill points at, and the skill names every page it lists', function () {
        $skill = file_get_contents(dirname(__DIR__, 2).'/resources/boost/skills/atom-components/SKILL.blade.php');
        $patterns = substr($skill, strpos($skill, '### Patterns'), strpos($skill, '### Toast / Alert / Confirm') - strpos($skill, '### Patterns'));

        expect($patterns)->toContain('/atom/docs/patterns');

        foreach ([...array_keys(Docs::PATTERNS), ...array_keys(Docs::PATTERN_PARTIALS)] as $name) {
            expect($patterns)->toContain('docs/patterns/'.$name.'.blade.php');
        }

        // and only pages that exist
        preg_match_all('#docs/patterns/([a-z-]+)\.blade\.php#', $patterns, $found);

        foreach (array_unique($found[1]) as $name) {
            expect(file_exists(dirname(__DIR__, 2).'/resources/views/docs/patterns/'.$name.'.blade.php'))->toBeTrue($name);
        }
    });
});

describe('docs navigation', function () {
    it('lists the patterns under their own group', function () {
        registerDocsRoutes();

        $html = renderBlade('<atom:docs.layout>x</atom:docs.layout>');

        expect($html)->toContain('Patterns')
            ->toContain('App shell (sidebar)')
            ->toContain('App shell (top nav)')
            ->toContain('Record page')
            ->toContain('List page')
            ->toContain('Document page')
            ->toContain('Form page')
            ->toContain('Slide-over form')
            ->toContain('Overview')
            ->toContain('/atom/docs/patterns/app-shell-header');
    });
});

// The record page: header, status, stats, record tabs and the three empty states, in a shell, from atom's own components.
describe('record page pattern', function () {
    it('is built from the page components inside the sidebar shell', function () {
        $html = view('atom::docs.patterns.record-page')->render();

        expect(domQuery($html, '//main[@data-atom-main]//*[@data-atom-page]'))->toHaveCount(1)
            ->and(domQuery($html, '//*[@data-atom-page]/header[@data-atom-page-header]//h1[@data-atom-page-header-title]'))->toHaveCount(1)
            ->and(domQuery($html, '//*[@data-atom-page]/header//*[@data-atom-status]'))->toHaveCount(1)
            ->and(domQuery($html, '//*[@data-atom-page]/*[@data-atom-stat-group]/*[@data-atom-stat]'))->toHaveCount(4)
            ->and(domQuery($html, '//*[@data-atom-page]/nav[@data-atom-page-tabs]//*[@aria-current="page"]'))->toHaveCount(1)
            ->and(domQuery($html, '//*[@data-atom-sidebar]'))->not->toBeEmpty();
    });

    it('draws in the top-nav shell too, when the host passes it', function () {
        $html = view('atom::docs.patterns.record-page', ['shell' => 'header'])->render();

        expect(domQuery($html, '//*[@data-atom-header-bar]'))->not->toBeEmpty()
            ->and(domQuery($html, '//main//*[@data-atom-page-header]'))->toHaveCount(1);
    });

    it('has one primary action, and it follows the status', function (string $status, string $word, string $action) {
        request()->query->set('status', $status);

        $html = view('atom::docs.patterns.record-page')->render();
        $primary = domQuery($html, '//*[@data-atom-page-actions]//*[@data-testid="record-primary"]');

        expect($primary)->toHaveCount(1)
            ->and($primary[0]->textContent)->toContain($action)
            ->and(trim(domQuery($html, '//header//*[@data-atom-status]')[0]->textContent))->toBe($word)
            ->and(domQuery($html, '//header[@data-atom-page-header]//*[@data-atom-button][not(ancestor::template)][contains(concat(" ", normalize-space(@class), " "), " bg-accent ")]'))->toHaveCount(1);
    })->with([
        ['draft', 'Draft', 'Schedule job'],
        ['in-progress', 'In progress · 1h 12m', 'Mark as done'],
        ['blocked', 'Waiting for parts', 'Order parts'],
        ['done', 'Done', 'Create invoice'],
    ]);

    it('puts one warning callout under the header when the job is blocked, and none otherwise', function (string $status, int $callouts) {
        request()->query->set('status', $status);

        $html = view('atom::docs.patterns.record-page')->render();
        $found = domQuery($html, '//*[@data-testid="record-callout"]');

        expect($found)->toHaveCount($callouts);

        if ($callouts) {
            expect(domClasses($found[0]))->toContain('bg-warning-bg');
        }
    })->with([['draft', 0], ['in-progress', 0], ['blocked', 1], ['done', 0]]);

    it('shows who a task is assigned to as an avatar and a name', function () {
        $html = view('atom::docs.patterns.record-page')->render();

        expect(domQuery($html, '//tbody//td[3]//*[@data-atom-avatar]'))->toHaveCount(4);
    });

    it('pulses only while the record is in progress', function (string $status, int $running) {
        request()->query->set('status', $status);

        expect(domQuery(view('atom::docs.patterns.record-page')->render(), '//header//*[@data-atom-status][@data-running]'))->toHaveCount($running);
    })->with([['draft', 0], ['in-progress', 1], ['blocked', 0], ['done', 0]]);

    it('shows each kind of empty state on its own tab, with a next step', function (string $tab, string $kind) {
        request()->query->set('tab', $tab);

        $html = view('atom::docs.patterns.record-page')->render();
        $empty = domQuery($html, '//*[@data-atom-empty]');

        expect($empty)->toHaveCount(1)
            ->and($empty[0]->getAttribute('data-kind'))->toBe($kind)
            ->and(domQuery($html, '//*[@data-atom-empty-action]//*[@data-atom-button]'))->toHaveCount(1);
    })->with([['parts', 'first-use'], ['activity', 'error'], ['files', 'no-results']]);

    it('shows the tasks table, no empty state, on the overview tab', function () {
        $html = view('atom::docs.patterns.record-page')->render();

        expect(domQuery($html, '//*[@data-atom-empty]'))->toBeEmpty()
            ->and(domQuery($html, '//*[@data-atom-table]//*[@data-atom-status]'))->toHaveCount(4);
    });

    it('has a rail of cards beside the main column on the overview, with the facts grouped by subject and no dash for an empty field', function () {
        $html = view('atom::docs.patterns.record-page')->render();
        $rail = '//*[@data-testid="record-rail"]';

        expect(domClasses(domQuery($html, $rail.'/..')[0]))->toContain('min-[1080px]:grid-cols-[minmax(0,1fr)_320px]')
            ->and(domQuery($html, $rail.'/*[@data-atom-card]'))->toHaveCount(5)
            ->and(array_map(fn ($h) => trim($h->textContent), domQuery($html, $rail.'/*[@data-atom-card]//h3')))->toBe(['Details', 'Customer', 'People', 'Linked records', 'Activity'])
            ->and(domQuery($html, $rail.'//*[@data-atom-dd-group]/*[@data-atom-dd]'))->not->toBeEmpty()
            // an empty field ("Cost centre", "Checked by") is left out, never printed as a dash
            ->and($html)->not->toContain('Cost centre')->not->toContain('Checked by')
            ->and(domQuery($html, $rail.'//dd[normalize-space(.) = "--" or normalize-space(.) = "—" or normalize-space(.) = ""]'))->toBeEmpty()
            // people are an avatar, a name and an id in the mono style
            ->and(domQuery($html, $rail.'//*[@data-atom-avatar]'))->toHaveCount(2)
            ->and(domQuery($html, $rail.'//*[@data-atom-dd]//span[contains(@class, "font-id")]'))->not->toBeEmpty()
            // linked records: mono ids and add links
            ->and(domQuery($html, '//*[@data-testid="record-linked"]//dd//span[contains(@class, "font-id")]'))->toHaveCount(2)
            ->and(domQuery($html, '//*[@data-testid="record-linked"]//a[contains(., "Quote")]'))->toHaveCount(1)
            ->and(domQuery($html, '//*[@data-testid="record-linked"]//a[contains(., "Purchase order")]'))->toHaveCount(1);
    });

    it('puts the activity timeline last in the rail, with a link to all changes', function () {
        $html = view('atom::docs.patterns.record-page')->render();
        $activity = '//*[@data-testid="record-rail"]/*[@data-atom-card][last()]';

        expect(domQuery($html, $activity.'[@data-testid="record-activity"]'))->toHaveCount(1)
            ->and(domQuery($html, $activity.'//*[@data-atom-timeline][@data-kind="event"]/ol/li'))->toHaveCount(4)
            ->and(domQuery($html, $activity.'//*[@data-atom-timeline-title]'))->toHaveCount(4)
            ->and(domQuery($html, $activity.'//a[contains(., "All changes")]'))->toHaveCount(1)
            ->and(domQuery($html, '//*[@data-testid="record-activity"]//li[@data-tone]'))->toHaveCount(4);
    });

    it('draws the stage path under the header, the current segment matching the status', function (string $status, array $states) {
        request()->query->set('status', $status);

        $html = view('atom::docs.patterns.record-page')->render();
        $path = domQuery($html, '//*[@data-atom-page]/*[@data-atom-timeline][@data-kind="path"][@data-testid="record-stages"]');

        expect($path)->toHaveCount(1)
            // under the header, over the stats
            ->and(domQuery($html, '//header[@data-atom-page-header]/following-sibling::*[@data-kind="path"]'))->toHaveCount(1)
            ->and(domQuery($html, '//*[@data-kind="path"]/following-sibling::*[@data-atom-stat-group]'))->toHaveCount(1)
            ->and(array_map(fn ($li) => $li->getAttribute('data-state'), domQuery($html, '//*[@data-kind="path"]/ol/li')))->toBe($states);
    })->with([
        ['draft', ['current', 'pending', 'pending', 'pending']],
        ['in-progress', ['done', 'done', 'current', 'pending']],
        ['blocked', ['done', 'done', 'current', 'pending']],
        ['done', ['done', 'done', 'done', 'done']],
    ]);

    it('tints the current stage in the status\'s tone', function (string $status, string $bg) {
        request()->query->set('status', $status);

        $html = view('atom::docs.patterns.record-page')->render();

        expect(domClasses(domQuery($html, '//*[@data-kind="path"]//li[@aria-current="step"]')[0]))->toContain($bg);
    })->with([['in-progress', 'bg-info-bg'], ['blocked', 'bg-warning-bg']]);

    it('gives the Done stage of a done job its time and person', function () {
        request()->query->set('status', 'done');

        $html = view('atom::docs.patterns.record-page')->render();

        expect(trim(domQuery($html, '//*[@data-kind="path"]//li[4]//*[@data-atom-timeline-meta]')[0]->textContent))->toContain('Today 11:20')->toContain('Ana');
    });

    it('puts the record\'s lines in a panel with a heading row and a count, a frameless table of numeric hours and totals under it', function () {
        $html = view('atom::docs.patterns.record-page')->render();
        $panel = '//*[@data-testid="record-lines"]';

        expect(trim(domQuery($html, $panel.'/div[1]//h2')[0]->textContent))->toContain('Tasks')->toContain('4 tasks')
            ->and(domQuery($html, $panel.'/div[1]//a[contains(., "View all")]'))->toHaveCount(1)
            ->and(domQuery($html, $panel.'//*[@data-atom-table][@data-atom-table-frameless]'))->toHaveCount(1)
            ->and(domQuery($html, $panel.'//thead//th[contains(@class, "text-right")]'))->toHaveCount(1)
            ->and(domQuery($html, $panel.'//tbody//td[contains(@class, "tabular-nums")][contains(@class, "text-right")]'))->toHaveCount(4)
            ->and(domQuery($html, $panel.'//td[@align="right"]'))->toBeEmpty()
            // the totals follow the table, and add up (2.0 + 3.5 + 1.5 + 0.5)
            ->and(domQuery($html, $panel.'//*[@data-atom-table]/following-sibling::dl[@data-testid="record-totals"]'))->toHaveCount(1)
            ->and(domQuery($html, $panel.'//dl')[0]->textContent)->toContain('7.5 h');
    });

    it('shows the description at the reading size', function () {
        $html = view('atom::docs.patterns.record-page')->render();

        expect(domClasses(domQuery($html, '//*[@data-testid="record-description"]//p')[0]))->toContain('text-base');
    });

    it('takes its crumbs from the shell\'s slot, with previous and next in the actions slot, and its nav from the shared partial', function (string $shell) {
        $html = view('atom::docs.patterns.record-page', ['shell' => $shell])->render();

        expect(domQuery($html, '//*[@data-atom-shell-crumbs]//*[@data-testid="record-crumbs"]'))->toHaveCount(1)
            ->and(domQuery($html, '//*[@data-atom-page-crumbs]'))->toBeEmpty()
            // previous and next: the sidebar's bar has them on its right, the top nav beside the crumbs (its right side is the account)
            ->and(domQuery($html, '//*[@data-atom-shell-'.($shell === 'header' ? 'crumbs' : 'actions').']//button[@aria-label="Previous job"]'))->toHaveCount(1)
            ->and(domQuery($html, '//*[@data-atom-shell-'.($shell === 'header' ? 'crumbs' : 'actions').']//button[@aria-label="Next job"]'))->toHaveCount(1)
            ->and(domQuery($html, '//button[@aria-label="Next job"]'))->toHaveCount(1)
            // the nav is the partial the shells share
            ->and(domQuery($html, '//nav[@data-atom-navlist]//a[@href="?page=invoices"]'))->not->toBeEmpty();
    })->with(['sidebar', 'header']);

    it('shows a process log and a conversation on their own tabs', function () {
        request()->query->set('tab', 'log');
        $log = view('atom::docs.patterns.record-page')->render();

        expect(domQuery($log, '//*[@data-testid="record-log"]//*[@data-atom-timeline][@data-kind="step"]/ol/li'))->toHaveCount(4);

        request()->query->set('tab', 'messages');
        $messages = view('atom::docs.patterns.record-page')->render();

        expect(domQuery($messages, '//*[@data-testid="record-messages"]//*[@data-atom-timeline][@data-kind="message"]/ol/li'))->toHaveCount(3)
            ->and(domQuery($messages, '//*[@data-testid="record-messages"]//*[@data-atom-timeline-composer]//form'))->toHaveCount(1);
    });

    it('draws each empty state in a card', function (string $tab) {
        request()->query->set('tab', $tab);

        $html = view('atom::docs.patterns.record-page')->render();

        expect(domQuery($html, '//*[@data-atom-card][@data-atom-card-inset]/*[@data-atom-empty]'))->toHaveCount(1);
    })->with(['parts', 'activity', 'files']);

    it('does not choke on a status or a tab that is an array or unknown', function () {
        request()->query->set('status', ['x']);
        request()->query->set('tab', ['y']);

        $html = view('atom::docs.patterns.record-page')->render();

        expect(trim(domQuery($html, '//header//*[@data-atom-status]')[0]->textContent))->toBe('In progress · 1h 12m');

        request()->query->set('status', 'nope');
        request()->query->set('tab', 'nope');

        expect(domQuery(view('atom::docs.patterns.record-page')->render(), '//*[@data-atom-page-tabs]//*[@aria-current="page"]')[0]->textContent)->toContain('Overview');
    });

    it('uses generic names and data only', function (string $page) {
        $source = file_get_contents(dirname(__DIR__, 2).'/resources/views/docs/patterns/'.$page.'.blade.php');

        // the names a public pattern page must never carry, written in halves so this file passes the repo grep too
        $private = [['humble', 'bear'], ['smg', 'dms'], ['firm', 'hive'], ['too', 'crm'], ['smg', 'auto'], ['mint', 'comm'], ['web', 'rock'], ['api', 'kan'], ['seri ', 'mutiara'], ['harbour ', 'line'], ['jiannius ', 'tech']];

        foreach ($private as [$a, $b]) {
            expect(strtolower($source))->not->toContain($a.$b);
        }

        expect($source)->not->toContain('/Us'.'ers/')->not->toContain('/pri'.'vate/');
    })->with(['record-page', 'list-page', 'document-page', 'form-page', 'slide-over-form']);
});

describe('page component docs', function () {
    it('has a demo for every page component, which renders and shows its source', function (string $name) {
        registerDocsRoutes();

        $html = view('atom::docs.demos.'.$name)->render();

        expect($html)->toContain('data-atom-')->and(view()->exists('atom::docs.demos.'.$name))->toBeTrue();
    })->with(['status', 'stat', 'page', 'empty', 'timeline']);

    it('lists the new components under their categories', function () {
        $docs = new Docs;

        expect($docs->category('status'))->toBe('Display & typography')
            ->and($docs->category('stat'))->toBe('Display & typography')
            ->and($docs->category('timeline'))->toBe('Display & typography')
            ->and($docs->category('page'))->toBe('Layout & navigation')
            ->and($docs->component('page')['props'])->toContain(['name' => 'width', 'default' => null])
            ->and(collect($docs->component('stat')['props'])->pluck('name')->all())->toContain('label', 'value', 'prefix', 'delta', 'inverse', 'breakdown', 'href');
    });
});

// One primary action per screen: the header's. An empty state's next step is a plain button.
describe('one primary action', function () {
    $primaries = fn (string $html) => domQuery($html, '//*[@data-atom-button][not(ancestor::template)][contains(concat(" ", normalize-space(@class), " "), " bg-accent ")]');

    it('keeps the record page to one primary button on every tab and status', function (string $tab, string $status) use ($primaries) {
        request()->query->set('tab', $tab);
        request()->query->set('status', $status);

        $found = $primaries(view('atom::docs.patterns.record-page')->render());

        expect($found)->toHaveCount(1)
            ->and($found[0]->getAttribute('data-testid'))->toBe('record-primary');
    })->with(function () {
        foreach (['overview', 'tasks', 'parts', 'activity', 'files', 'log', 'messages'] as $tab) {
            foreach (['draft', 'in-progress', 'blocked', 'done'] as $status) {
                yield "$tab, $status" => [$tab, $status];
            }
        }
    });

    it('has no primary button in the empty state demos', function () use ($primaries) {
        registerDocsRoutes();

        expect($primaries(view('atom::docs.demos.empty')->render()))->toBeEmpty();
    });

    it('does not teach a primary next step in the Boost skill', function () {
        $skill = file_get_contents(dirname(__DIR__, 2).'/resources/boost/skills/atom-components/SKILL.blade.php');

        expect($skill)->toContain('warning for waiting, blocked or needs attention, danger for failed or overdue')
            ->and($skill)->not->toContain('the next step creates the first one, `variant="primary"`');
    });
});


// V4-2c: the list page, the document page, the form page and the slide-over form: live pages built from atom's own components.
describe('list page pattern', function () {
    it('is one panel: the saved views, the toolbar and a frameless table, under a header and a totals strip', function () {
        $html = view('atom::docs.patterns.list-page')->render();
        $panel = domQuery($html, '//*[@data-testid="list-panel"]');

        expect($panel)->toHaveCount(1)
            ->and(domQuery($html, '//main//*[@data-atom-page]/header[@data-atom-page-header]'))->toHaveCount(1)
            ->and(domQuery($html, '//*[@data-atom-page]/*[@data-atom-stat-group]/*[@data-atom-stat]'))->toHaveCount(4)
            ->and(domQuery($html, '//*[@data-testid="list-panel"]/nav[@data-atom-table-views]/*[@data-atom-table-view]'))->toHaveCount(5)
            ->and(domQuery($html, '//*[@data-testid="list-panel"]/*[@data-atom-table-toolbar]//*[@data-atom-table-search]'))->toHaveCount(1)
            ->and(domQuery($html, '//*[@data-testid="list-panel"]//*[@data-atom-table][@data-atom-table-frameless]'))->toHaveCount(1);
    });

    it('draws it in the top-nav shell too', function () {
        $html = view('atom::docs.patterns.list-page', ['shell' => 'header'])->render();

        expect(domQuery($html, '//*[@data-atom-header-bar]'))->not->toBeEmpty()
            ->and(domQuery($html, '//main//*[@data-testid="list-panel"]'))->toHaveCount(1);
    });

    it('takes the counts from the rows and shows the chosen view', function (string $view, int $rows) {
        request()->query->set('view', $view);

        $html = view('atom::docs.patterns.list-page')->render();
        $chosen = domQuery($html, '//*[@data-atom-table-view][@aria-pressed="true"]');

        expect($chosen)->toHaveCount(1)
            ->and($chosen[0]->getAttribute('data-value'))->toBe($view)
            ->and(trim(domQuery($html, '//*[@data-atom-table-view][@aria-pressed="true"]//*[@data-atom-table-view-count]')[0]->textContent))->toBe((string) $rows)
            ->and(domQuery($html, '//*[@data-atom-table]//tbody/tr'))->toHaveCount($rows);
    })->with([['all', 8], ['unpaid', 4], ['overdue', 2], ['paid', 3], ['draft', 1]]);

    it('puts money in a numeric column named once in the header, and identifiers in the mono style', function () {
        $html = view('atom::docs.patterns.list-page')->render();
        $header = domQuery($html, '//thead//th[contains(@class, "text-right")]');

        expect($header)->toHaveCount(1)
            ->and(trim($header[0]->textContent))->toBe('Amount (RM)')
            ->and(domQuery($html, '//tbody/tr/td[contains(@class, "tabular-nums")]'))->toHaveCount(8)
            ->and(domQuery($html, '//tbody//span[contains(@class, "font-id")]'))->toHaveCount(8)
            ->and(domQuery($html, '//tbody//td[contains(., "RM")]'))->toBeEmpty();
    });

    it('has one primary action, the header\'s, and a status with a word on every row', function () {
        $html = view('atom::docs.patterns.list-page')->render();

        expect(domQuery($html, '//*[@data-atom-button][not(ancestor::template)][contains(concat(" ", normalize-space(@class), " "), " bg-accent ")]'))->toHaveCount(1)
            ->and(domQuery($html, '//*[@data-atom-page-actions]//*[@data-testid="list-primary"]'))->toHaveCount(1)
            // a status on every row, and the part-paid overdue invoice carries a second one
            ->and(domQuery($html, '//tbody//*[@data-atom-status]'))->toHaveCount(9)
            ->and(domQuery($html, '//tbody//tr[contains(., "INV-0409")]//*[@data-atom-status]'))->toHaveCount(2)
            ->and(domQuery($html, '//*[@data-atom-page-actions]//button[contains(., "Import")]'))->toHaveCount(1);
    });

    it('has the filters as sentence pills in the toolbar, one of them active, and the others dashed add pills', function () {
        $html = view('atom::docs.patterns.list-page')->render();
        $bar = '//*[@data-testid="list-panel"]/*[@data-atom-table-toolbar]//*[@data-atom-table-filters][@data-chips="inline"]';

        expect(domQuery($html, $bar))->toHaveCount(1)
            // customer, status, issued: all three are add pills (a select's trigger, a date range's input)
            ->and(domQuery($html, $bar.'//button[@data-atom-select-combobox][contains(@class, "border-dashed")]'))->toHaveCount(2)
            ->and(domQuery($html, $bar.'//button[@data-atom-date-range-add][contains(@class, "border-dashed")]'))->toHaveCount(1)
            // the issue date is the one that is set: the range reports it as a chip
            ->and($html)->toContain("'2026-04-01 to 2026-10-31'")
            ->and(domQuery($html, $bar.'//template//*[@data-atom-table-filter-chip]'))->toHaveCount(1);
    });

    it('has a checkbox column, with a box in the header and one on every row', function () {
        $html = view('atom::docs.patterns.list-page')->render();

        expect(domQuery($html, '//thead//th[1]//*[@data-atom-table-checkbox]'))->toHaveCount(1)
            ->and(domQuery($html, '//tbody/tr/td[1]//*[@data-atom-table-checkbox]'))->toHaveCount(8)
            ->and(domQuery($html, '//tbody/tr/td[2]//span[contains(@class, "font-id")]'))->toHaveCount(8);
    });

    it('does not choke on a view that is an array or unknown', function () {
        request()->query->set('view', ['x']);
        expect(domQuery(view('atom::docs.patterns.list-page')->render(), '//*[@data-atom-table-view][@aria-pressed="true"]')[0]->getAttribute('data-value'))->toBe('all');

        request()->query->set('view', 'nope');
        expect(domQuery(view('atom::docs.patterns.list-page')->render(), '//*[@data-atom-table-view][@aria-pressed="true"]')[0]->getAttribute('data-value'))->toBe('all');
    });
});

describe('document page pattern', function () {
    it('is a header, the document as paper and a rail with the balance, the details and the history', function () {
        $html = view('atom::docs.patterns.document-page')->render();

        expect(domQuery($html, '//main//*[@data-atom-page]/header[@data-atom-page-header]'))->toHaveCount(1)
            ->and(domQuery($html, '//article[@data-testid="paper"]//table//tbody/tr'))->toHaveCount(4)
            ->and(domQuery($html, '//*[@data-testid="balance-box"]//*[@data-atom-progress]'))->toHaveCount(1)
            ->and(domQuery($html, '//*[@data-testid="history"]//*[@data-atom-timeline][@data-kind="event"]/ol/li'))->toHaveCount(4);
    });

    it('uses the shell\'s crumbs slot, with previous and next in its actions slot, and a success-toned balance bar with the days past due', function () {
        $html = view('atom::docs.patterns.document-page')->render();

        expect(domQuery($html, '//*[@data-atom-shell-crumbs]//*[@data-testid="document-crumbs"]'))->toHaveCount(1)
            ->and(domQuery($html, '//*[@data-atom-page-crumbs]'))->toBeEmpty()
            ->and(domQuery($html, '//*[@data-atom-shell-actions]//button[@aria-label="Next invoice"]'))->toHaveCount(1)
            ->and(domQuery($html, '//*[@data-testid="balance-box"]//*[@data-atom-progress]//*[@role="progressbar"]/div[contains(@class, "bg-success-fg")]'))->toHaveCount(1)
            ->and(domQuery($html, '//*[@data-testid="balance-box"]')[0]->textContent)->toContain('23 days past due');
    });

    it('offers a secondary Send reminder when the invoice is overdue, and not otherwise', function (string $status, int $count) {
        request()->query->set('status', $status);

        $html = view('atom::docs.patterns.document-page')->render();
        $found = domQuery($html, '//*[@data-atom-page-actions]//*[@data-testid="document-reminder"]');

        expect($found)->toHaveCount($count);

        if ($count) {
            expect(domClasses($found[0]))->not->toContain('bg-accent');
        }
    })->with([['overdue', 1], ['draft', 0], ['paid', 0]]);

    it('draws the rail as cards, with the details as a description list', function () {
        $html = view('atom::docs.patterns.document-page')->render();

        expect(domQuery($html, '//aside//*[@data-atom-card]'))->toHaveCount(3)
            ->and(domQuery($html, '//aside//*[@data-atom-dd-group]/*[@data-atom-dd]'))->toHaveCount(4)
            ->and(domQuery($html, '//aside//section'))->toBeEmpty();
    });

    it('has one primary action and it follows the state', function (string $status, string $action, int $events) {
        request()->query->set('status', $status);

        $html = view('atom::docs.patterns.document-page')->render();

        expect(domQuery($html, '//*[@data-atom-button][not(ancestor::template)][contains(concat(" ", normalize-space(@class), " "), " bg-accent ")]'))->toHaveCount(1)
            ->and(domQuery($html, '//*[@data-testid="document-primary"]')[0]->textContent)->toContain($action)
            ->and(domQuery($html, '//*[@data-testid="history"]//li'))->toHaveCount($events);
    })->with([['overdue', 'Record payment', 4], ['draft', 'Send invoice', 1], ['paid', 'Download receipt', 2]]);

    it('adds up: the total is the lines plus the tax, and the balance is the total less what was received', function () {
        $html = view('atom::docs.patterns.document-page')->render();
        $amounts = array_map(fn ($td) => (float) str_replace(',', '', trim($td->textContent)), domQuery($html, '//article//tbody/tr/td[4]'));
        $totals = array_map(fn ($dd) => (float) str_replace(['−', ','], ['-', ''], trim($dd->textContent)), domQuery($html, '//*[@data-testid="totals"]//dd'));

        // subtotal, tax, total, paid, balance
        expect(array_sum($amounts))->toBe($totals[0])
            ->and(round($totals[0] + $totals[1], 2))->toBe($totals[2])
            ->and(round($totals[2] + $totals[3], 2))->toBe($totals[4]);
    });

    it('keeps the paper its own colours, in a deliberately unthemed document', function () {
        $paper = domQuery(view('atom::docs.patterns.document-page')->render(), '//article[@data-testid="paper"]')[0];

        expect(domClasses($paper))->toContain('bg-[#fffefc]', 'text-[#1d1c19]');
    });

    it('does not choke on a status that is an array or unknown', function () {
        request()->query->set('status', ['x']);
        expect(domQuery(view('atom::docs.patterns.document-page')->render(), '//*[@data-testid="document-primary"]')[0]->textContent)->toContain('Record payment');

        request()->query->set('status', 'nope');
        expect(domQuery(view('atom::docs.patterns.document-page')->render(), '//*[@data-testid="document-primary"]')[0]->textContent)->toContain('Record payment');
    });
});

describe('form page pattern', function () {
    it('is a form of four sections and a save bar, in a narrow page', function () {
        $html = view('atom::docs.patterns.form-page')->render();

        expect(domQuery($html, '//main//*[@data-atom-page]//form[@data-atom-form]'))->toHaveCount(1)
            ->and(domQuery($html, '//form/*[@data-atom-form-section]'))->toHaveCount(4)
            ->and(domQuery($html, '//form/*[@data-atom-form-section][@data-layout="aside"]'))->toHaveCount(3)
            // the line items are a wide editor: stacked, with the table across the whole column
            ->and(domQuery($html, '//form/*[@data-atom-form-section][@data-layout="stacked"][.//*[@data-testid="form-lines"]]'))->toHaveCount(1)
            ->and(domQuery($html, '//form/*[@data-atom-save-bar]'))->toHaveCount(1)
            // the bar is the last thing in the form
            ->and(domQuery($html, '//form/*[@data-atom-save-bar]/following-sibling::*'))->toBeEmpty()
            ->and(domClasses(domQuery($html, '//*[@data-testid="form-page"]')[0]))->toContain('max-w-[980px]');
    });

    it('stacks every section with ?layout=stacked', function () {
        request()->query->set('layout', 'stacked');

        expect(domQuery(view('atom::docs.patterns.form-page')->render(), '//form/*[@data-atom-form-section][@data-layout="stacked"]'))->toHaveCount(4);
    });

    it('falls back to aside for any other layout, or an array', function () {
        request()->query->set('layout', ['stacked']);
        expect(domQuery(view('atom::docs.patterns.form-page')->render(), '//form/*[@data-atom-form-section][@data-layout="aside"]'))->toHaveCount(3);

        request()->query->set('layout', 'nope');
        expect(domQuery(view('atom::docs.patterns.form-page')->render(), '//form/*[@data-atom-form-section][@data-layout="aside"]'))->toHaveCount(3);
    });

    it('has one primary action, the submit in the save bar, and totals at the start of the bar', function () {
        $html = view('atom::docs.patterns.form-page')->render();
        $primary = domQuery($html, '//*[@data-atom-button][not(ancestor::template)][contains(concat(" ", normalize-space(@class), " "), " bg-accent ")]');

        expect($primary)->toHaveCount(1)
            ->and($primary[0]->getAttribute('type'))->toBe('submit')
            ->and(domQuery($html, '//*[@data-atom-save-bar]//*[@data-atom-form-actions-buttons]//*[@data-testid="form-primary"]'))->toHaveCount(1)
            ->and(domQuery($html, '//*[@data-atom-save-bar]/*[@data-atom-form-actions-start]'))->toHaveCount(1)
            ->and(domQuery($html, '//*[@data-testid="grand-total"]')[0]->textContent)->toContain('RM');
    });

    it('lists the lines as editable rows with money in numeric columns', function () {
        $html = view('atom::docs.patterns.form-page')->render();
        $rows = '//*[@data-testid="form-lines"]//tbody/tr';

        expect(domQuery($html, '//*[@data-atom-table-frameless]//thead//th[contains(@class, "text-right")]'))->toHaveCount(3)
            ->and(domQuery($html, $rows))->toHaveCount(3)
            // an item input, a quantity and a unit price (right-aligned and tabular), a tax select and a ghost remove on every row
            ->and(domQuery($html, $rows.'/td[1]//input'))->toHaveCount(3)
            ->and(domQuery($html, $rows.'/td[2]//input[contains(@class, "text-right")][contains(@class, "tabular-nums")]'))->toHaveCount(3)
            ->and(domQuery($html, $rows.'/td[3]//input[contains(@class, "text-right")][contains(@class, "tabular-nums")]'))->toHaveCount(3)
            ->and(domQuery($html, $rows.'/td[4]//select'))->toHaveCount(3)
            ->and(domQuery($html, $rows.'/td[6]//button[@aria-label="Remove line"]'))->toHaveCount(3)
            // the amount is read-only text
            ->and(domQuery($html, $rows.'/td[5]//input'))->toBeEmpty()
            ->and(domQuery($html, '//*[@data-testid="form-lines"]/following-sibling::div//button[contains(., "Add line")]'))->toHaveCount(1)
            ->and(domQuery($html, '//*[@data-testid="form-lines"]/following-sibling::div//button[contains(., "Add from products")]'))->toHaveCount(1);
    });

    it('picks dates with date pickers and the payment terms from a select, and mutes the (optional)', function () {
        $html = view('atom::docs.patterns.form-page')->render();

        expect(domQuery($html, '//*[@data-atom-date-picker][.//label[contains(., "Issue date")]] | //label[contains(., "Issue date")]'))->not->toBeEmpty()
            ->and(domQuery($html, '//label[contains(., "Payment terms")]'))->not->toBeEmpty()
            ->and(domQuery($html, '//select'))->not->toBeEmpty()
            ->and(domQuery($html, '//input[@value="Net 30"]'))->toBeEmpty()
            ->and(domQuery($html, '//*[@data-atom-label-optional]'))->toHaveCount(2);
    });

    it('draws in the top-nav shell too', function () {
        expect(domQuery(view('atom::docs.patterns.form-page', ['shell' => 'header'])->render(), '//*[@data-atom-header-bar]'))->not->toBeEmpty();
    });
});

describe('slide-over form pattern', function () {
    it('is a slide-over with stacked sections and the pinned bar, over a list', function () {
        $html = view('atom::docs.patterns.slide-over-form')->render();
        $modal = domQuery($html, '//dialog[@data-testid="slide-over"]');

        expect($modal)->toHaveCount(1)
            ->and(domQuery($html, '//dialog//form/*/*[@data-atom-form-section][@data-layout="stacked"]'))->toHaveCount(3)
            ->and(domQuery($html, '//dialog//form/*[@data-atom-form-actions]'))->toHaveCount(1)
            ->and(domClasses(domQuery($html, '//dialog//*[@data-atom-form-actions]')[0]))->toContain('sticky', '-bottom-6')
            ->and(domQuery($html, '//dialog//*[@data-atom-page-header]//h2'))->toHaveCount(1)
            ->and(domQuery($html, '//*[@data-testid="slide-over-page"]//*[@data-atom-table]'))->toHaveCount(1);
    });

    it('puts the status text at the start of the pinned bar and the submit at the end', function () {
        $html = view('atom::docs.patterns.slide-over-form')->render();
        $bar = '//dialog//*[@data-atom-form-actions]';

        expect(domQuery($html, $bar.'/*[1][@data-atom-form-actions-start]'))->toHaveCount(1)
            ->and(domQuery($html, $bar.'/*[last()][@data-testid="slide-over-submit"]'))->toHaveCount(1)
            ->and(domQuery($html, $bar.'/*[@data-atom-form-actions-start]//*[@data-testid="slide-over-saved"]'))->toHaveCount(1);
    });

    it('opens from the header primary, and on load with ?open=1', function () {
        $closed = view('atom::docs.patterns.slide-over-form')->render();

        expect(domQuery($closed, '//*[@data-testid="open-slide-over"]')[0]->getAttribute('x-on:click'))->toContain("atom.modal('new-customer').slide()")
            ->and($closed)->not->toContain("\$nextTick(() => atom.modal('new-customer')");

        request()->query->set('open', '1');

        expect(view('atom::docs.patterns.slide-over-form')->render())->toContain("\$nextTick(() => atom.modal('new-customer').slide())");
    });

    it('has one primary action on the page and one in the slide-over: the submit', function () {
        $html = view('atom::docs.patterns.slide-over-form')->render();

        expect(domQuery($html, '//dialog//*[@data-atom-button][not(ancestor::template)][contains(concat(" ", normalize-space(@class), " "), " bg-accent ")]'))->toHaveCount(1)
            ->and(domQuery($html, '//dialog//*[@data-testid="slide-over-submit"]')[0]->getAttribute('type'))->toBe('submit')
            ->and(domQuery($html, '//*[@data-atom-page-actions]//*[@data-atom-button][not(ancestor::template)][contains(concat(" ", normalize-space(@class), " "), " bg-accent ")]'))->toHaveCount(1);
    });
});

describe('new component docs', function () {
    it('has a demo example for every new table and form part, listed on its component page', function (string $name, string $demo) {
        $page = file_get_contents(dirname(__DIR__, 2).'/resources/views/docs/demos/'.$name.'.blade.php');

        expect(view()->exists('atom::docs.demos.'.$demo))->toBeTrue()
            ->and($page)->toContain('view="atom::docs.demos.'.$demo.'"');
    })->with([
        ['table', 'table.frameless'],
        ['table', 'table.numeric'],
        ['table', 'table.toolbar'],
        ['table', 'table.views'],
        ['form', 'form.section'],
        ['form', 'form.section-stacked'],
        ['form', 'form.section-footer'],
        ['form', 'form.save-bar'],
        ['timeline', 'timeline.event'],
        ['timeline', 'timeline.step'],
        ['timeline', 'timeline.message'],
    ]);

    it('renders each new demo and shows its source', function (string $demo) {
        registerDocsRoutes();

        $html = view('atom::docs.demos.'.$demo)->render();

        expect($html)->toContain('data-atom-');
    })->with(['table.frameless', 'table.numeric', 'table.toolbar', 'table.views', 'form.section', 'form.section-stacked', 'form.section-footer', 'form.save-bar', 'timeline.event', 'timeline.step', 'timeline.message']);

    it('lists the props of the new components', function () {
        $docs = new Docs;

        expect(collect($docs->component('timeline')['props'])->pluck('name')->all())->toContain('kind', 'label', 'collapsible')
            ->and(collect($docs->component('table')['props'])->pluck('name')->all())->toContain('frameless');
    });
});

describe('one primary action, V4-2c', function () {
    $primaries = fn (string $html) => domQuery($html, '//*[@data-atom-button][not(ancestor::template)][contains(concat(" ", normalize-space(@class), " "), " bg-accent ")]');

    it('keeps every page pattern to one primary button', function (string $page) use ($primaries) {
        expect($primaries(view('atom::docs.patterns.'.$page)->render()))->toHaveCount(1);
    })->with(['list-page', 'document-page', 'form-page', 'record-page']);

    it('keeps the slide-over page to its header primary plus the slide-over\'s submit', function () use ($primaries) {
        expect($primaries(view('atom::docs.patterns.slide-over-form')->render()))->toHaveCount(2);
    });
});

// The docs inside a host: its own stylesheet is loaded when it has a Vite build for it, so the utilities atom's pages use exist.
describe('docs vite entries', function () {
    beforeEach(function () {
        // Laravel's Vite caches a manifest by path, so every test has a public directory of its own
        $this->public = sys_get_temp_dir().'/atom-docs-vite-'.uniqid();
        mkdir($this->public.'/build', 0777, true);
        app()->usePublicPath($this->public);
        app()->forgetInstance(\Illuminate\Foundation\Vite::class);
    });

    afterEach(function () {
        foreach (array_merge(glob($this->public.'/*') ?: [], glob($this->public.'/*/*') ?: []) as $file) {
            is_file($file) && @unlink($file);
        }

        foreach (glob($this->public.'/*', GLOB_ONLYDIR) ?: [] as $dir) {
            @rmdir($dir);
        }

        @rmdir($this->public);
    });

    it('loads nothing without a Vite build, as in the package\'s own rig', function () {
        expect((new Docs)->vite())->toBe([]);
    });

    it('loads the host\'s stylesheet once its manifest names it', function () {
        file_put_contents($this->public.'/build/manifest.json', json_encode(['resources/css/app.css' => ['file' => 'assets/app.css'], 'resources/js/app.js' => ['file' => 'assets/app.js']]));

        expect((new Docs)->vite())->toBe(['resources/css/app.css']);
    });

    it('skips an entry the manifest lacks, and a manifest that is not JSON', function () {
        file_put_contents($this->public.'/build/manifest.json', json_encode(['resources/js/app.js' => ['file' => 'assets/app.js']]));

        expect((new Docs)->vite())->toBe([]);
    });

    it('skips a manifest that is not JSON', function () {
        file_put_contents($this->public.'/build/manifest.json', 'not json');

        expect((new Docs)->vite())->toBe([]);
    });

    it('loads the entries while a dev server is running', function () {
        file_put_contents($this->public.'/hot', 'http://localhost:5173');

        expect((new Docs)->vite())->toBe(['resources/css/app.css']);
    });

    it('follows a host\'s own hot file and build directory, the way @vite does', function () {
        mkdir($this->public.'/dist', 0777, true);
        file_put_contents($this->public.'/dist/manifest.json', json_encode(['resources/css/app.css' => ['file' => 'assets/app.css']]));

        // the default build directory has nothing: not loaded
        expect((new Docs)->vite())->toBe([]);

        try {
            app(\Illuminate\Foundation\Vite::class)->useBuildDirectory('dist');

            expect((new Docs)->vite())->toBe(['resources/css/app.css']);

            // and a hot file somewhere else
            file_put_contents($this->public.'/dev.hot', 'http://localhost:5173');
            app(\Illuminate\Foundation\Vite::class)->useBuildDirectory('nowhere')->useHotFile($this->public.'/dev.hot');

            expect((new Docs)->vite())->toBe(['resources/css/app.css']);
        } finally {
            app()->forgetInstance(\Illuminate\Foundation\Vite::class);
        }
    });

    it('takes the entries from config, an empty list turns it off', function () {
        file_put_contents($this->public.'/hot', 'http://localhost:5173');

        config(['atom.docs.vite' => ['resources/css/app.css', 'resources/css/extra.css']]);
        expect((new Docs)->vite())->toBe(['resources/css/app.css', 'resources/css/extra.css']);

        config(['atom.docs.vite' => []]);
        expect((new Docs)->vite())->toBe([]);

        config(['atom.docs.vite' => ['resources/css/app.css', 42, null]]);
        expect((new Docs)->vite())->toBe(['resources/css/app.css']);
    });

    it('is the stylesheet the docs layout and the pattern pages ask for', function () {
        file_put_contents($this->public.'/hot', 'http://localhost:5173');
        registerDocsRoutes();

        // the layout and a pattern page carry a Vite tag for the entry, which the dev server's hot file answers
        expect(renderBlade('<atom:docs.layout>x</atom:docs.layout>'))->toContain('resources/css/app.css')
            ->and(view('atom::docs.patterns.list-page')->render())->toContain('resources/css/app.css');
    });

    it('scans the docs and the patterns for utilities, so a host build contains them', function () {
        $css = file_get_contents(dirname(__DIR__, 2).'/resources/css/atom.tailwind.css');

        expect($css)->toMatch('#@source "\.\./views/\{docs,mail\}/\*\*/\*\.blade\.php";#');
    });
});

// One item of the shared nav is current on a pattern page, and it is the one for that page.
describe('the pattern pages mark the right nav item', function () {
    it('has exactly one current item, the one for the page', function (string $page, string $current) {
        $html = view('atom::docs.patterns.'.$page)->render();
        $items = domQuery($html, '//*[@data-atom-navlist-item][@aria-current="page"]');

        expect($items)->toHaveCount(1)
            ->and($items[0]->getAttribute('href'))->toBe('?page='.$current);
    })->with([
        ['record-page', 'jobs'],
        ['slide-over-form', 'customers'],
        ['list-page', 'invoices'],
        ['document-page', 'invoices'],
        ['form-page', 'invoices'],
        ['app-shell-sidebar', 'invoices'],
        ['app-shell-header', 'invoices'],
    ]);

    it('has a Jobs item for the work order page, and its crumb links there', function () {
        $html = view('atom::docs.patterns.record-page')->render();

        expect(domQuery($html, '//nav[@data-atom-navlist]//a[@href="?page=jobs"]'))->toHaveCount(1)
            ->and(domQuery($html, '//*[@data-testid="record-crumbs"]//a[@href="?page=jobs"]'))->toHaveCount(1);
    });
});

// Consistency of the invented data and small details across the pattern pages.
describe('pattern page details', function () {
    it('draws the list page\'s totals strip from the very rows it lists', function () {
        $html = view('atom::docs.patterns.list-page')->render();
        $stat = fn (int $n) => preg_replace('/\s+/', ' ', trim(domQuery($html, '//*[@data-atom-stat-group]/*[@data-atom-stat]['.$n.']')[0]->textContent));

        // the sent invoices that still owe: 1,300 + 2,000 + 750 + 150
        expect($stat(1))->toContain('4,200.00')->toContain('4 unpaid invoices')
            ->and($stat(2))->toContain('900.00')->toContain('2 invoices')
            ->and($stat(3))->toContain('3,300.00')->toContain('2 invoices')
            ->and($stat(4))->toContain('1');

        // and the Unpaid view lists exactly those four
        request()->query->set('view', 'unpaid');
        expect(domQuery(view('atom::docs.patterns.list-page')->render(), '//*[@data-atom-table]//tbody/tr'))->toHaveCount(4);
    });

    it('shows no open task, and a Done stage with a time and a person, on a done job', function () {
        request()->query->set('status', 'done');

        $html = view('atom::docs.patterns.record-page')->render();

        expect(trim(domQuery($html, '//*[@data-atom-stat-group]/*[@data-atom-stat][4]')[0]->textContent))->toContain('0')
            ->and(domQuery($html, '//*[@data-testid="record-lines"]//*[@data-atom-status][contains(., "Open")]'))->toBeEmpty();
    });

    it('does not claim parts the Parts tab does not have', function () {
        $html = view('atom::docs.patterns.record-page')->render();

        expect(preg_replace('/\s+/', ' ', trim(domQuery($html, '//*[@data-atom-stat-group]/*[@data-atom-stat][2]')[0]->textContent)))->toContain('0.00')->not->toContain('7 parts');
    });

    it('opens the whole history from "All changes", not the error state', function () {
        $html = view('atom::docs.patterns.record-page')->render();
        $href = domQuery($html, '//*[@data-testid="record-activity"]//a[contains(., "All changes")]')[0]->getAttribute('href');

        expect($href)->toBe('?tab=activity&view=all');

        request()->query->set('tab', 'activity');
        request()->query->set('view', 'all');
        $all = view('atom::docs.patterns.record-page')->render();

        expect(domQuery($all, '//*[@data-atom-empty]'))->toBeEmpty()
            ->and(domQuery($all, '//*[@data-testid="record-history"]//*[@data-atom-timeline]/ol/li'))->toHaveCount(6);
    });

    it('uses the neutral tone for a sent invoice on the shell pages, as the list page does', function () {
        $html = view('atom::docs.patterns.app-shell-sidebar')->render();
        $sent = domQuery($html, '//main//tbody//*[@data-atom-status][contains(., "Sent")]');

        expect($sent)->toHaveCount(1)
            ->and($sent[0]->getAttribute('data-tone'))->toBe('neutral');
    });

    it('keeps the form page\'s payment terms and tax selects alike, with no per-render x-data', function () {
        $html = view('atom::docs.patterns.form-page')->render();

        // no clear x on a required field: both kinds of select say so
        $source = file_get_contents(dirname(__DIR__, 2).'/resources/views/docs/patterns/form-page.blade.php');

        expect($source)->toMatch('/label="Payment terms"[^>]*:clearable="false"/')
            ->and($source)->toMatch('/aria-label="Tax"[^>]*:clearable="false"/');

        foreach (domQuery($html, '//*[@data-testid="form-lines"]//td[@x-data]') as $cell) {
            expect($cell->getAttribute('x-data'))->toBe('{ tax: null }')
                ->and($cell->getAttribute('data-tax'))->toBeIn(['none', 'sst-8']);
        }
    });

    it('draws no hand-rolled radius on the pattern pages', function (string $page) {
        $source = file_get_contents(dirname(__DIR__, 2).'/resources/views/docs/patterns/'.$page.'.blade.php');

        // (the document's paper is the one exception: a printed page has its own 6px corners, as the approved sample does)
        $allowed = $page === 'document-page' ? ['rounded-md bg-[#fffefc]'] : [];

        expect(str_replace($allowed, '', $source))->not->toMatch('/\brounded-(?:sm|md|lg|xl|2xl)\b/');
    })->with(['record-page', 'list-page', 'document-page', 'form-page', 'slide-over-form', 'page', 'app-shell-sidebar', 'app-shell-header']);

    it('shows a customer with a name of its own in the header, not "Customer Customer A"', function () {
        $html = view('atom::docs.patterns.record-page')->render();

        expect(domQuery($html, '//*[@data-atom-page-meta]')[0]->textContent)->not->toContain('Customer Customer A');
    });
});
