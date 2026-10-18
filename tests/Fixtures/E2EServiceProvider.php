<?php

namespace Jiannius\Atom\Tests\Fixtures;

use Illuminate\Auth\GenericUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Jiannius\Atom\Services\Carbon;
use Livewire\Livewire;

class E2EServiceProvider extends ServiceProvider
{
    /**
     * Give the served e2e app an App\Actions\GetOptions to point remote selects at.
     */
    public function register(): void
    {
        // A remote select always POSTs to `get-options`, which resolves the host's
        // App\Actions\GetOptions first. The served e2e app has no such class, so
        // give it one for the xss page. Deliberately NOT a file under
        // tests/Fixtures/Actions: that directory is autoloaded as App\Actions in
        // the Pest process too, and the class would shadow the package's there.
        if (! class_exists('App\Actions\GetOptions', false)) {
            class_alias(XssOptions::class, 'App\Actions\GetOptions');
        }
    }

    /**
     * Wire the Livewire-backed E2E fixtures into the `testbench serve` app only —
     * they are dev scaffolding and must not reach consuming apps, so this provider
     * is registered in testbench.yaml rather than in the package's own routes.
     */
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__, 'atom-test');

        Livewire::component('atom-e2e-select-morph', SelectMorphFixture::class);
        Livewire::component('atom-e2e-breadcrumbs', BreadcrumbsFixture::class);
        Livewire::component('atom-e2e-breadcrumbs-untrailed', BreadcrumbsUntrailedFixture::class);
        Livewire::component('atom-e2e-breadcrumbs-back', BreadcrumbsBackFixture::class);
        Livewire::component('atom-e2e-sticky-selection', StickySelectionFixture::class);
        Livewire::component('atom-e2e-input-morph', InputMorphFixture::class);
        Livewire::component('atom-e2e-table-loading', TableLoadingFixture::class);
        Livewire::component('atom-e2e-table-search-chip', TableSearchChipFixture::class);
        Livewire::component('atom-e2e-table-search-chip-live', TableSearchChipLiveFixture::class);
        Livewire::component('atom-e2e-date-range-morph', DateRangeMorphFixture::class);
        Livewire::component('atom-e2e-date-picker-time-morph', DatePickerTimeMorphFixture::class);
        Livewire::component('atom-e2e-dropdown-morph', DropdownMorphFixture::class);
        Livewire::component('atom-e2e-date-picker-typed', DatePickerTypedFixture::class);
        Livewire::component('atom-e2e-select-sibling-sync', SelectSiblingSyncFixture::class);
        Livewire::component('atom-e2e-safe-url-breadcrumbs', SafeUrlBreadcrumbsFixture::class);
        Livewire::component('atom-e2e-form-error-toast', FormErrorToastFixture::class);
        Livewire::component('atom-e2e-url-filters', UrlFiltersFixture::class);
        Livewire::component('atom-e2e-shell-actions', ShellActionsFixture::class);
        Livewire::component('atom-e2e-page-header-slide', PageHeaderSlideFixture::class);
        Livewire::component('atom-e2e-table-views', TableViewsFixture::class);
        Livewire::component('atom-e2e-timeline', TimelineFixture::class);
        Livewire::component('atom-e2e-slide-over-form', SlideOverFormFixture::class);
        Livewire::component('atom-e2e-calendar', CalendarFixture::class);
        Livewire::component('atom-e2e-calendar-look', CalendarLookFixture::class);

        Route::middleware('web')->get('/atom/e2e/select-morph', fn () => view('atom::e2e.select-morph'));
        Route::middleware('web')->get('/atom/e2e/attr-sweep', fn () => view('atom::e2e.attr-sweep'));
        Route::middleware('web')->get('/atom/e2e/select-xss', fn () => view('atom::e2e.select-xss'));
        Route::middleware('web')->get('/atom/e2e/select-sibling-sync', fn () => view('atom::e2e.select-sibling-sync'));
        Route::middleware('web')->get('/atom/e2e/select-native-reopen', fn () => view('atom::e2e.select-native-reopen'));
        Route::middleware('web')->get('/atom/e2e/input-morph', fn () => view('atom::e2e.input-morph'));
        Route::middleware('web')->get('/atom/e2e/date-range-morph', fn () => view('atom::e2e.date-range-morph'));
        Route::middleware('web')->get('/atom/e2e/date-picker-time-morph', fn () => view('atom::e2e.date-picker-time-morph'));
        Route::middleware('web')->get('/atom/e2e/date-picker-typed', fn () => view('atom::e2e.date-picker-typed'));
        Route::middleware('web')->get('/atom/e2e/dropdown-morph', fn () => view('atom::e2e.dropdown-morph'));
        Route::middleware('web')->get('/atom/e2e/dropdown-trigger', fn () => view('atom::e2e.dropdown-trigger'));
        Route::middleware('web')->get('/atom/e2e/floating-panel-height', fn () => view('atom::e2e.floating-panel-height'));
        Route::middleware('web')->get('/atom/e2e/navlist-persist', fn () => view('atom::e2e.navlist-persist'));
        Route::middleware('web')->get('/atom/e2e/sticky-selection', fn () => view('atom::e2e.sticky-selection'));
        Route::middleware('web')->get('/atom/e2e/table-filters-more', fn () => view('atom::e2e.table-filters-more'));
        Route::middleware('web')->get('/atom/e2e/table-loading', fn () => view('atom::e2e.table-loading'));
        Route::middleware('web')->get('/atom/e2e/table-layout', fn () => view('atom::e2e.table-layout'));
        Route::middleware('web')->get('/atom/e2e/table-search-chip', fn () => view('atom::e2e.table-search-chip'));
        Route::middleware('web')->get('/atom/e2e/table-search-chip-live', fn () => view('atom::e2e.table-search-chip-live'));
        Route::middleware('web')->get('/atom/e2e/breadcrumbs', fn () => view('atom::e2e.breadcrumbs'));
        Route::middleware('web')->get('/atom/e2e/breadcrumbs-wrapped', fn () => view('atom::e2e.breadcrumbs-wrapped'));
        Route::middleware('web')->get('/atom/e2e/breadcrumbs-untrailed', fn () => view('atom::e2e.breadcrumbs-untrailed'));
        Route::middleware('web')->get('/atom/e2e/breadcrumbs-back', fn () => view('atom::e2e.breadcrumbs-back'));
        Route::middleware('web')->get('/atom/e2e/breadcrumbs-back/record', fn () => view('atom::e2e.breadcrumbs-back'));
        Route::middleware('web')->get('/atom/e2e/url-filters', fn () => view('atom::e2e.url-filters'));
        Route::middleware('web')->get('/atom/e2e/url-filters/record', fn () => view('atom::e2e.url-filters'));
        Route::middleware('web')->get('/atom/e2e/consumer-tailwind', fn () => view('atom::e2e.consumer-tailwind'));
        // The two shell pattern pages (resources/views/docs/patterns), on the consumer's Tailwind: ?tw=floor|latest, ?dark=1.
        // header-wide is the worst case for the bar: long labels, a count on each, a context switcher and a search field.
        foreach (['sidebar' => 'docs.patterns.app-shell-sidebar', 'header' => 'docs.patterns.app-shell-header', 'header-wide' => 'e2e.shell-header-wide', 'record' => 'docs.patterns.record-page', 'list' => 'docs.patterns.list-page', 'document' => 'docs.patterns.document-page', 'form' => 'docs.patterns.form-page', 'slide-over' => 'docs.patterns.slide-over-form'] as $shell => $pattern) {
            Route::middleware('web')->get('/atom/e2e/shell-'.$shell, function (Request $request) use ($pattern) {
                Auth::setUser(new class(['id' => 1, 'name' => 'Alex Doe', 'email' => 'alex@example.com', 'avatar' => null]) extends GenericUser {
                    public function initials(): string
                    {
                        return 'AD';
                    }
                });

                $tw = in_array($request->query('tw'), ['floor', 'latest'], true) ? $request->query('tw') : null;

                return view('atom::'.$pattern, [
                    'styles' => $tw ? ['/atom/e2e/consumer-css/'.$tw] : [],
                    'dark' => $request->query('dark') === '1',
                    // the record pattern is drawn in either shell: ?shell=header
                    'shell' => $request->query('shell'),
                ]);
            });
        }
        // V4-2c: saved views, the toolbar and the bulk bar; the three timeline kinds; a form.modal slide-over. On the consumer's Tailwind.
        Route::middleware('web')->get('/atom/e2e/table-views', fn () => view('atom::e2e.table-views'));
        Route::middleware('web')->get('/atom/e2e/timeline', fn () => view('atom::e2e.timeline'));
        Route::middleware('web')->get('/atom/e2e/slide-over-form', fn () => view('atom::e2e.slide-over-form'));
        Route::middleware('web')->get('/atom/e2e/page-width', fn () => view('atom::e2e.page-width'));
        Route::middleware('web')->get('/atom/e2e/page-header-slide', fn () => view('atom::e2e.page-header-slide'));
        // status tones, stats and the empty states, on the consumer's Tailwind: ?tw=floor|latest, ?dark=1, ?accent=#rrggbb
        Route::middleware('web')->get('/atom/e2e/page-parts', fn () => view('atom::e2e.page-parts'));
        Route::middleware('web')->get('/atom/e2e/shell-editor', fn () => view('atom::e2e.shell-editor'));
        Route::middleware('web')->get('/atom/e2e/tokens', fn () => view('atom::e2e.tokens'));
        // The stylesheet tests/consumer/build.mjs compiled with a consumer's Tailwind (floor or latest), for a page
        // that opts in through <atom:html :styles>. 404 when it has not been built, so a missing build is loud.
        Route::get('/atom/e2e/consumer-css/{name}', function (string $name) {
            $file = dirname(__DIR__).'/consumer/build/'.$name.'.css';

            abort_unless(in_array($name, ['floor', 'latest'], true) && is_file($file), 404);

            return response()->file($file, ['Content-Type' => 'text/css']);
        });
        Route::middleware('web')->get('/atom/e2e/safe-url', fn () => view('atom::e2e.safe-url'));
        Route::middleware('web')->get('/atom/e2e/safe-url-breadcrumbs', fn () => view('atom::e2e.safe-url-breadcrumbs'));
        Route::middleware('web')->get('/atom/e2e/form-error-toast', fn () => view('atom::e2e.form-error-toast'));
        Route::middleware('web')->get('/atom/e2e/calendar', fn () => view('atom::e2e.calendar'));
        Route::middleware('web')->get('/atom/e2e/calendar-static', fn () => view('atom::e2e.calendar-static'));
        Route::middleware('web')->get('/atom/e2e/calendar-twin', fn () => view('atom::e2e.calendar-twin'));
        Route::middleware('web')->get('/atom/e2e/calendar-look', function (Request $request) {
            // the viewer's language, for the titles written differently in it
            app()->setLocale(in_array($request->query('locale'), ['en', 'zh', 'ms'], true) ? $request->query('locale') : 'en');

            return view('atom::e2e.calendar-look');
        });
        Route::middleware('web')->get('/atom/e2e/calendar-grid', function (Request $request) {
            // the viewer's timezone and the moment it is now, so the week and day views can be looked at on a day the clocks change
            $timezone = (string) $request->query('tz', 'Asia/Kuala_Lumpur');

            Auth::setUser(new GenericUser(['id' => 1, 'timezone' => in_array($timezone, timezone_identifiers_list(), true) ? $timezone : 'Asia/Kuala_Lumpur']));
            Carbon::setTestNow(Carbon::parse((string) $request->query('now', '2026-10-09T02:30:00Z')));

            // the viewer's language, for the titles that are written differently in it
            app()->setLocale(in_array($request->query('locale'), ['en', 'zh', 'ms'], true) ? $request->query('locale') : 'en');

            return view('atom::e2e.calendar-grid');
        });
    }
}
