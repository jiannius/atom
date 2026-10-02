<?php

namespace Jiannius\Atom\Tests\Fixtures;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
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
        Livewire::component('atom-e2e-sticky-selection', StickySelectionFixture::class);
        Livewire::component('atom-e2e-input-morph', InputMorphFixture::class);
        Livewire::component('atom-e2e-table-loading', TableLoadingFixture::class);
        Livewire::component('atom-e2e-table-search-chip', TableSearchChipFixture::class);
        Livewire::component('atom-e2e-table-search-chip-live', TableSearchChipLiveFixture::class);
        Livewire::component('atom-e2e-date-range-morph', DateRangeMorphFixture::class);
        Livewire::component('atom-e2e-date-picker-time-morph', DatePickerTimeMorphFixture::class);
        Livewire::component('atom-e2e-date-picker-typed', DatePickerTypedFixture::class);
        Livewire::component('atom-e2e-select-sibling-sync', SelectSiblingSyncFixture::class);
        Livewire::component('atom-e2e-safe-url-breadcrumbs', SafeUrlBreadcrumbsFixture::class);

        Route::middleware('web')->get('/atom/e2e/select-morph', fn () => view('atom::e2e.select-morph'));
        Route::middleware('web')->get('/atom/e2e/select-xss', fn () => view('atom::e2e.select-xss'));
        Route::middleware('web')->get('/atom/e2e/select-sibling-sync', fn () => view('atom::e2e.select-sibling-sync'));
        Route::middleware('web')->get('/atom/e2e/input-morph', fn () => view('atom::e2e.input-morph'));
        Route::middleware('web')->get('/atom/e2e/date-range-morph', fn () => view('atom::e2e.date-range-morph'));
        Route::middleware('web')->get('/atom/e2e/date-picker-time-morph', fn () => view('atom::e2e.date-picker-time-morph'));
        Route::middleware('web')->get('/atom/e2e/date-picker-typed', fn () => view('atom::e2e.date-picker-typed'));
        Route::middleware('web')->get('/atom/e2e/navlist-persist', fn () => view('atom::e2e.navlist-persist'));
        Route::middleware('web')->get('/atom/e2e/sticky-selection', fn () => view('atom::e2e.sticky-selection'));
        Route::middleware('web')->get('/atom/e2e/table-loading', fn () => view('atom::e2e.table-loading'));
        Route::middleware('web')->get('/atom/e2e/table-layout', fn () => view('atom::e2e.table-layout'));
        Route::middleware('web')->get('/atom/e2e/table-search-chip', fn () => view('atom::e2e.table-search-chip'));
        Route::middleware('web')->get('/atom/e2e/table-search-chip-live', fn () => view('atom::e2e.table-search-chip-live'));
        Route::middleware('web')->get('/atom/e2e/breadcrumbs', fn () => view('atom::e2e.breadcrumbs'));
        Route::middleware('web')->get('/atom/e2e/breadcrumbs-wrapped', fn () => view('atom::e2e.breadcrumbs-wrapped'));
        Route::middleware('web')->get('/atom/e2e/breadcrumbs-untrailed', fn () => view('atom::e2e.breadcrumbs-untrailed'));
        Route::middleware('web')->get('/atom/e2e/safe-url', fn () => view('atom::e2e.safe-url'));
        Route::middleware('web')->get('/atom/e2e/safe-url-breadcrumbs', fn () => view('atom::e2e.safe-url-breadcrumbs'));
    }
}
