<?php

namespace Jiannius\Atom;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Jiannius\Atom\Commands\MigrateTiptapContent;
use Jiannius\Atom\Commands\PurgeEditorImages;
use Jiannius\Atom\Services\Asset;
use Jiannius\Atom\Services\TagCompiler;

class AtomServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider
     */
    public function register() : void
    {
        $this->app->alias(Atom::class, 'atom');

        // Register the <atom:...> tag precompiler during register() (not boot()) so
        // it runs BEFORE Livewire's wire:key precompiler (registered in Livewire's
        // boot()). All register() run before all boot(), so atom converts its tags
        // to component form first — letting Livewire handle wire:key via its normal
        // component path instead of mangling the raw <atom:...> tag.
        $this->tagCompiler();
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

        $this->configureDate();
        $this->registerMacros();
        $this->registerComponentMacros();
        $this->registerComponentGuards();
        $this->registerCommands();

        Asset::boot();

        Route::post('/atom/action/{name}', function ($name) {
            $result = app('atom')->webAction($name, request()->all());
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
     * Register the helpers every Livewire component gets ($this->toast(), ...).
     *
     * Macros, not trait methods: Livewire exposes every public method a component
     * declares to the browser ($wire.<name>()), and a trait method counts as the
     * component's own, while a macro is only reachable through __call and never
     * appears in the reflected list the update request checks a call against.
     * So none of these can be called from the browser. A class method of the same
     * name on a component still wins over the macro.
     */
    protected function registerComponentMacros() : void
    {
        $component = \Livewire\Component::class;

        /** Generate a wire key from every argument as given, falsy ones included */
        $component::macro('wirekey', function (...$args) {
            return $args
                ? md5(json_encode($args))
                : md5((string) str()->ulid());
        });

        /** Show a modal in front end, the current component's by default */
        $component::macro('modal', function ($name = null) {
            return app('atom')->modal($name ?? app('livewire')->current()->getName());
        });

        /** Show the command palette in front end */
        $component::macro('command', function ($name = null) {
            return app('atom')->command($name ?? app('livewire')->current()->getName());
        });

        /** Show a toast in front end */
        $component::macro('toast', function (...$args) {
            app('atom')->toast(...$args);
        });

        /** Show an alert in front end */
        $component::macro('alert', function (...$args) {
            app('atom')->alert(...$args);
        });

        /** Show a confirm in front end */
        $component::macro('confirm', function (...$args) {
            app('atom')->confirm(...$args);
        });

        /**
         * Trigger an action. Unrestricted (a caller-controlled `method` is honoured),
         * which is why it must never be browser-callable: the browser reaches actions
         * through POST /atom/action, behind the WebAction contract.
         */
        $component::macro('action', function ($name, $params = [], $render = false) {
            if (!$render) {
                $this->skipRender();
            }

            return app('atom')->action($name, $params);
        });
    }

    /**
     * Fail loudly where a Livewire component is mounted without a trait it needs.
     *
     * A breadcrumbs() method is only called by WithBreadcrumbs, and <atom:breadcrumbs>
     * renders in the layout, outside any component, so the markup cannot check it.
     */
    protected function registerComponentGuards() : void
    {
        \Livewire\on('mount', function ($component) {
            if ($this->hasOrphanBreadcrumbsMethod($component)) {
                throw new \LogicException(sprintf(
                    '%s defines breadcrumbs() but nothing calls it: add `use \\%s;` to the component.',
                    get_class($component), \Jiannius\Atom\Traits\WithBreadcrumbs::class,
                ));
            }
        });
    }

    /**
     * Whether the component has a breadcrumbs() method for WithBreadcrumbs to call and
     * does not use the trait. A method carrying a Livewire attribute (a #[Computed]
     * property that happens to be called breadcrumbs) is the host's own, and so is a
     * component (or the method it inherits) that lives in vendor/, a third-party package.
     */
    protected function hasOrphanBreadcrumbsMethod(object $component) : bool
    {
        if (!method_exists($component, 'breadcrumbs')) {
            return false;
        }

        if (in_array(\Jiannius\Atom\Traits\WithBreadcrumbs::class, class_uses_recursive($component), true)) {
            return false;
        }

        $method = new \ReflectionMethod($component, 'breadcrumbs');

        foreach ($method->getAttributes() as $attribute) {
            if (str_starts_with($attribute->getName(), 'Livewire\\')) {
                return false;
            }
        }

        $vendor = (realpath(base_path('vendor')) ?: base_path('vendor')).DIRECTORY_SEPARATOR;

        foreach ([(new \ReflectionClass($component))->getFileName(), $method->getFileName()] as $file) {
            if ($file && str_starts_with($file, $vendor)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Register the commands
     */
    protected function registerCommands() : void
    {
        $this->commands([
            PurgeEditorImages::class,
            MigrateTiptapContent::class,
        ]);
    }
}