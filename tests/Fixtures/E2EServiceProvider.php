<?php

namespace Jiannius\Atom\Tests\Fixtures;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class E2EServiceProvider extends ServiceProvider
{
    /**
     * Wire the Livewire-backed E2E fixtures into the `testbench serve` app only —
     * they are dev scaffolding and must not reach consuming apps, so this provider
     * is registered in testbench.yaml rather than in the package's own routes.
     */
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__, 'atom-test');

        Livewire::component('atom-e2e-select-morph', SelectMorphFixture::class);

        Route::middleware('web')->get('/atom/e2e/select-morph', fn () => view('atom::e2e.select-morph'));
    }
}
