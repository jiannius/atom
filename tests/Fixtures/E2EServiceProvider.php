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
        Livewire::component('atom-e2e-breadcrumbs-header', BreadcrumbsHeaderFixture::class);
        Livewire::component('atom-e2e-breadcrumbs-trail', BreadcrumbsTrailFixture::class);
        Livewire::component('atom-e2e-sticky-selection', StickySelectionFixture::class);
        Livewire::component('atom-e2e-table-checked', TableCheckedFixture::class);
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
        Livewire::component('atom-e2e-form-toast-bar', FormToastBarFixture::class);
        Livewire::component('atom-e2e-form-dirty', FormDirtyFixture::class);
        Livewire::component('atom-e2e-url-filters', UrlFiltersFixture::class);
        Livewire::component('atom-e2e-shell-actions', ShellActionsFixture::class);
        Livewire::component('atom-e2e-page-header-slide', PageHeaderSlideFixture::class);
        Livewire::component('atom-e2e-table-views', TableViewsFixture::class);
        Livewire::component('atom-e2e-table-filter-bar', TableFilterBarFixture::class);
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
        Route::middleware('web')->get('/atom/e2e/table-checked', fn () => view('atom::e2e.table-checked'));
        Route::middleware('web')->get('/atom/e2e/table-filters-more', fn () => view('atom::e2e.table-filters-more'));
        Route::middleware('web')->get('/atom/e2e/table-filter-bar', fn () => view('atom::e2e.table-filter-bar'));
        Route::middleware('web')->get('/atom/e2e/table-filters-inline', fn () => view('atom::e2e.table-filters-inline'));
        Route::middleware('web')->get('/atom/e2e/table-loading', fn () => view('atom::e2e.table-loading'));
        Route::middleware('web')->get('/atom/e2e/table-layout', fn () => view('atom::e2e.table-layout'));
        Route::middleware('web')->get('/atom/e2e/table-search-chip', fn () => view('atom::e2e.table-search-chip'));
        Route::middleware('web')->get('/atom/e2e/table-search-chip-live', fn () => view('atom::e2e.table-search-chip-live'));
        Route::middleware('web')->get('/atom/e2e/breadcrumbs', fn () => view('atom::e2e.breadcrumbs'));
        Route::middleware('web')->get('/atom/e2e/breadcrumbs-wrapped', fn () => view('atom::e2e.breadcrumbs-wrapped'));
        Route::middleware('web')->get('/atom/e2e/breadcrumbs-untrailed', fn () => view('atom::e2e.breadcrumbs-untrailed'));
        Route::middleware('web')->get('/atom/e2e/breadcrumbs-back', fn () => view('atom::e2e.breadcrumbs-back'));
        Route::middleware('web')->get('/atom/e2e/breadcrumbs-back/record', fn () => view('atom::e2e.breadcrumbs-back'));
        Route::middleware('web')->get('/atom/e2e/breadcrumbs-header/{page}/{detail?}', fn () => view('atom::e2e.breadcrumbs-header'))->where('page', 'old|new|plain|deep|late');
        Route::middleware('web')->get('/atom/e2e/breadcrumbs-trail/{page}', fn () => view('atom::e2e.breadcrumbs-trail'))->where('page', '[a-z-]+');
        Route::middleware('web')->get('/atom/e2e/url-filters', fn () => view('atom::e2e.url-filters'));
        Route::middleware('web')->get('/atom/e2e/url-filters/record', fn () => view('atom::e2e.url-filters'));
        Route::middleware('web')->get('/atom/e2e/consumer-tailwind', fn () => view('atom::e2e.consumer-tailwind'));
        Route::middleware('web')->get('/atom/e2e/tooltip-scroll', fn () => view('atom::e2e.tooltip-scroll'));
        Route::middleware('web')->get('/atom/e2e/chip-fit', fn () => view('atom::e2e.chip-fit'));
        Route::middleware('web')->get('/atom/e2e/button-label', fn () => view('atom::e2e.button-label'));
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
        Route::middleware('web')->get('/atom/e2e/save-bar-context', fn () => view('atom::e2e.save-bar-context'));
        Route::middleware('web')->get('/atom/e2e/table-wide-page', fn () => view('atom::e2e.table-wide-page'));
        Route::middleware('web')->get('/atom/e2e/table-views', fn () => view('atom::e2e.table-views'));
        Route::middleware('web')->get('/atom/e2e/timeline', fn () => view('atom::e2e.timeline'));
        Route::middleware('web')->get('/atom/e2e/form-extras', fn () => view('atom::e2e.form-extras'));
        Route::middleware('web')->get('/atom/e2e/tabs-overflow', fn () => view('atom::e2e.tabs-overflow'));
        Route::middleware('web')->get('/atom/e2e/table-toolbar-end', fn () => view('atom::e2e.table-toolbar-end'));
        Route::middleware('web')->get('/atom/e2e/slide-over-short', fn () => view('atom::e2e.slide-over-short'));
        Route::middleware('web')->get('/atom/e2e/form-modal-width', fn () => view('atom::e2e.form-modal-width'));
        Route::middleware('web')->get('/atom/e2e/timeline-wrap', fn () => view('atom::e2e.timeline-wrap'));
        Route::middleware('web')->get('/atom/e2e/slide-over-form', fn () => view('atom::e2e.slide-over-form'));
        Route::middleware('web')->get('/atom/e2e/page-width', fn () => view('atom::e2e.page-width'));
        Route::middleware('web')->get('/atom/e2e/page-header-container', fn () => view('atom::e2e.page-header-container'));
        Route::middleware('web')->get('/atom/e2e/page-header-slide', fn () => view('atom::e2e.page-header-slide'));
        // status tones, stats and the empty states, on the consumer's Tailwind: ?tw=floor|latest, ?dark=1, ?accent=#rrggbb
        Route::middleware('web')->get('/atom/e2e/page-parts', fn () => view('atom::e2e.page-parts'));
        Route::middleware('web')->get('/atom/e2e/shell-editor', fn () => view('atom::e2e.shell-editor'));
        Route::middleware('web')->get('/atom/e2e/tokens', fn () => view('atom::e2e.tokens'));
        // The restyled controls (V4-3a), on the consumer's Tailwind: ?tw, ?density, ?dark=1, ?accent, ?section
        Route::middleware('web')->get('/atom/e2e/controls', fn () => view('atom::e2e.controls'));
        // The restyled data display (V4-3b), on the consumer's Tailwind: ?tw, ?density, ?dark=1, ?accent, ?section
        Route::middleware('web')->get('/atom/e2e/display', fn () => view('atom::e2e.display'));
        // The restyled overlays and navigation (V4-3c), on the consumer's Tailwind: ?tw, ?density, ?dark=1, ?accent, ?section
        Route::middleware('web')->get('/atom/e2e/layers', fn () => view('atom::e2e.layers'));
        // The restyled text, editor and chart (V4-3d), on the consumer's Tailwind: ?tw, ?density, ?dark=1, ?accent, ?section
        Route::middleware('web')->get('/atom/e2e/content', fn () => view('atom::e2e.content'));
        // a picture for the lightbox: an SVG, so nothing is fetched from the network
        Route::get('/atom/e2e/photo.svg', fn () => response('<svg xmlns="http://www.w3.org/2000/svg" width="480" height="320" viewBox="0 0 480 320"><rect width="480" height="320" fill="#d9c9a3"/><circle cx="240" cy="140" r="70" fill="#8a6a2f"/><rect x="120" y="220" width="240" height="60" rx="30" fill="#8a6a2f"/></svg>', 200, ['Content-Type' => 'image/svg+xml']));
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
        Route::middleware('web')->get('/atom/e2e/form-toast-bar', fn () => view('atom::e2e.form-toast-bar'));
        Route::middleware('web')->get('/atom/e2e/form-dirty', fn () => view('atom::e2e.form-dirty'));
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
