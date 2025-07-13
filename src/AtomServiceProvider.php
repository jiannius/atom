<?php

namespace Jiannius\Atom;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Support\Stringable;
use Illuminate\View\ComponentAttributeBag;
use Jiannius\Atom\Services\Asset;
use Jiannius\Atom\Services\TagCompiler;
use Livewire\Volt\Volt;

class AtomServiceProvider extends ServiceProvider
{
    // register
    public function register() : void
    {
        $this->app->alias(Atom::class, 'atom');
        $this->app->alias(Asset::class, 'atom-asset');
    }

    // boot
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

    protected function configureDate() : void
    {
        Date::use(\Jiannius\Atom\Services\Carbon::class);
    }

    protected function mountVoltComponents() : void
    {
        $this->app->booted(function() {
            Volt::mount(__DIR__.'/../resources/views/livewire');
        });
    }

    protected function registerMacros() : void
    {
        Builder::mixin(new \Jiannius\Atom\Macros\Builder());
        ComponentAttributeBag::mixin(new \Jiannius\Atom\Macros\ComponentAttributeBag());
        Request::mixin(new \Jiannius\Atom\Macros\Request());
        Str::mixin(new \Jiannius\Atom\Macros\Str());
        Stringable::mixin(new \Jiannius\Atom\Macros\Stringable());
        Arr::mixin(new \Jiannius\Atom\Macros\Arr());
    }
}