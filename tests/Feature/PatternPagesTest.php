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
            ->and(domQuery($html, '//main//*[@data-atom-table]//*[@data-atom-badge]'))->toHaveCount(4);
    })->with(['app-shell-sidebar', 'app-shell-header']);

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
        ['in-progress', 'In progress', 'Mark as done'],
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

    it('does not choke on a status or a tab that is an array or unknown', function () {
        request()->query->set('status', ['x']);
        request()->query->set('tab', ['y']);

        $html = view('atom::docs.patterns.record-page')->render();

        expect(trim(domQuery($html, '//header//*[@data-atom-status]')[0]->textContent))->toBe('In progress');

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
        foreach (['overview', 'tasks', 'parts', 'activity', 'files'] as $tab) {
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
            ->and(domQuery($html, '//tbody//*[@data-atom-status]'))->toHaveCount(8);
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
            ->and(domQuery($html, '//form/*[@data-atom-form-section][@data-layout="aside"]'))->toHaveCount(4)
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
        expect(domQuery(view('atom::docs.patterns.form-page')->render(), '//form/*[@data-atom-form-section][@data-layout="aside"]'))->toHaveCount(4);

        request()->query->set('layout', 'nope');
        expect(domQuery(view('atom::docs.patterns.form-page')->render(), '//form/*[@data-atom-form-section][@data-layout="aside"]'))->toHaveCount(4);
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

    it('lists the lines with money in numeric columns', function () {
        $html = view('atom::docs.patterns.form-page')->render();

        expect(domQuery($html, '//*[@data-atom-table-frameless]//thead//th[contains(@class, "text-right")]'))->toHaveCount(3)
            ->and(domQuery($html, '//*[@data-atom-table-frameless]//tbody/tr'))->toHaveCount(3);
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
