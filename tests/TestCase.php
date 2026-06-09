<?php

namespace Jiannius\Atom\Tests;

use Jiannius\Atom\AtomServiceProvider;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /**
     * Register the package + Livewire service providers into the test app.
     *
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [LivewireServiceProvider::class, AtomServiceProvider::class];
    }

    /**
     * Configure the Testbench environment (in-memory sqlite + app key).
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['view']->addNamespace('atom-test', __DIR__.'/Fixtures');
    }

    /**
     * Load the in-memory fixture migrations.
     */
    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Fixtures/migrations');
    }

    /**
     * Register the fixture Livewire component + route (for E2E + tests).
     */
    protected function defineRoutes($router): void
    {
        \Livewire\Livewire::component('table-fixture', \Jiannius\Atom\Tests\Fixtures\TableFixture::class);
        $router->get('/_test/table', \Jiannius\Atom\Tests\Fixtures\TableFixture::class)->middleware('web');
    }
}
