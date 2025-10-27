<?php

namespace Jiannius\Atom;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Jiannius\Atom\Commands\PurgeEditorImages;
use Jiannius\Atom\Services\Asset;
use Jiannius\Atom\Services\TagCompiler;
use Livewire\Volt\Volt;

class AtomServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider
     */
    public function register() : void
    {
        $this->app->alias(Atom::class, 'atom');
        $this->app->alias(Asset::class, 'atom-asset');
    }

    /**
     * Boot the service provider
     */
    public function boot() : void
    {
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'atom');
        $this->loadJsonTranslationsFrom(__DIR__.'/../lang');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'atom');
        Blade::anonymousComponentPath(__DIR__.'/../components', 'atom');

        $this->tagCompiler();
        $this->configureDate();
        $this->mountVoltComponents();
        $this->registerMacros();
        $this->registerCommands();

        Asset::boot();

        Route::post('/atom/action/{name}', function ($name) {
            $result = app('atom')->action($name, request()->all());
            $isResponseObject = $result instanceof \Illuminate\Http\JsonResponse || $result instanceof \Illuminate\Http\Response;
            return $isResponseObject ? $result : response()->json($result);
        })->middleware('web');
    }

    /**
     * Tag compiler for using <atom:component/>
     */
    protected function tagCompiler() : void
    {
        $compiler = new TagCompiler(
            app('blade.compiler')->getClassComponentAliases(),
            app('blade.compiler')->getClassComponentNamespaces(),
            app('blade.compiler')
        );

        app()->bind('atom.compiler', fn () => $compiler);

        app('blade.compiler')->precompiler(function ($in) use ($compiler) {
            return $compiler->compile($in);
        });
    }

    /**
     * Configure the date
     */
    protected function configureDate() : void
    {
        Date::use(\Jiannius\Atom\Services\Carbon::class);
    }

    /**
     * Mount the volt components
     */
    protected function mountVoltComponents() : void
    {
        $this->app->booted(function() {
            Volt::mount(__DIR__.'/../resources/views/livewire');
        });
    }

    /**
     * Register the macros
     */
    protected function registerMacros() : void
    {
        \Illuminate\Database\Eloquent\Builder::mixin(new \Jiannius\Atom\Macros\Builder());
        \Illuminate\Database\Query\Builder::mixin(new \Jiannius\Atom\Macros\Builder());
        \Illuminate\View\ComponentAttributeBag::mixin(new \Jiannius\Atom\Macros\ComponentAttributeBag());
        \Illuminate\Http\Request::mixin(new \Jiannius\Atom\Macros\Request());
        \Illuminate\Support\Str::mixin(new \Jiannius\Atom\Macros\Str());
        \Illuminate\Support\Stringable::mixin(new \Jiannius\Atom\Macros\Stringable());
        \Illuminate\Support\Arr::mixin(new \Jiannius\Atom\Macros\Arr());
    }

    /**
     * Register the commands
     */
    protected function registerCommands() : void
    {
        $this->commands([
            PurgeEditorImages::class,
        ]);
    }
}