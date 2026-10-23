<?php

namespace Jiannius\Atom;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Jiannius\Atom\Commands\MigrateTiptapContent;
use Jiannius\Atom\Commands\PurgeEditorImages;
use Jiannius\Atom\Commands\UpgradeCheck;
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
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'atom');
        $this->loadJsonTranslationsFrom(__DIR__.'/../lang');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'atom');
        Blade::anonymousComponentPath(__DIR__.'/../components', 'atom');

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
     * Register the macros
     */
    protected function registerMacros() : void
    {
        \Illuminate\Database\Eloquent\Builder::mixin(new \Jiannius\Atom\Macros\Builder());
        \Illuminate\Database\Query\Builder::mixin(new \Jiannius\Atom\Macros\Builder());
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
        // The signed raw: sort gate (issue #54). Livewire fires `update` for every client
        // update of a property path, before the value is written, and an event listener
        // cannot be shadowed by a method of the host component the way a trait hook can.
        // `before`, not `on`: a host's own updating(), updatingTable() or
        // updatingTableSortColumn() hook runs on the same event and would see (and could
        // store) the unsigned value if the gate came after it.
        \Livewire\before('update', function ($component, $path, $value) {
            if (is_string($path)
                && str_starts_with($path, '_table')
                && in_array(\Jiannius\Atom\Traits\WithTable::class, class_uses_recursive($component), true)
                && \Jiannius\Atom\Services\TableSort::isUnsafeUpdate($path, $value)
            ) {
                abort(403, 'Invalid table sort.');
            }
        });

        \Livewire\on('mount', function ($component) {
            if ($this->hasTableInQueryString($component)) {
                throw new \LogicException(sprintf(
                    '%s uses WithTable and puts `_table` in its query string: a crafted link would set the table sort, '
                    .'and the signed `raw:` sort gate only guards a browser update. Keep `_table` out of `queryString` and `#[Url]`.',
                    get_class($component),
                ));
            }

            if ($this->hasOrphanBreadcrumbsMethod($component)) {
                throw new \LogicException(sprintf(
                    '%s defines breadcrumbs() but nothing calls it: add `use \\%s;` to the component.',
                    get_class($component), \Jiannius\Atom\Traits\WithBreadcrumbs::class,
                ));
            }
        });
    }

    /**
     * Whether a component that uses WithTable has `_table` (or a path under it) in its
     * query string. Livewire reads the URL into a property before mount(), through no
     * `update` event, so the raw: sort gate never sees it. Every declaration form
     * SupportQueryString reads counts: a `queryString` property or method, a
     * `queryString{Trait}` member of a used trait, and #[Url] on a public property.
     */
    protected function hasTableInQueryString(object $component) : bool
    {
        $traits = class_uses_recursive($component);

        if (!in_array(\Jiannius\Atom\Traits\WithTable::class, $traits, true)) {
            return false;
        }

        $entries = [];

        if (method_exists($component, 'queryString')) {
            $entries[] = (fn () => $this->queryString())->call($component);
        }
        elseif (property_exists($component, 'queryString')) {
            $entries[] = (fn () => $this->queryString)->call($component);
        }

        foreach ($traits as $trait) {
            $member = 'queryString'.class_basename($trait);

            if (method_exists($component, $member)) {
                $entries[] = (fn () => $this->{$member}())->call($component);
            }
            elseif (property_exists($component, $member)) {
                $entries[] = (fn () => $this->{$member})->call($component);
            }
        }

        foreach ($entries as $entry) {
            foreach ((array) $entry as $key => $value) {
                $key = is_string($key) ? $key : $value;

                if (is_string($key) && ($key === '_table' || str_starts_with($key, '_table.'))) {
                    return true;
                }
            }
        }

        if (property_exists($component, '_table')) {
            foreach ((new \ReflectionProperty($component, '_table'))->getAttributes(\Livewire\Features\SupportQueryString\BaseUrl::class, \ReflectionAttribute::IS_INSTANCEOF) as $attribute) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether the component has a breadcrumbs(Breadcrumbs $b) method for WithBreadcrumbs
     * to call and does not use the trait. The v3 convention passed the builder in, so a
     * method that takes no parameter is something else; a method carrying a Livewire
     * attribute (a #[Computed] property called breadcrumbs) is the host's own; and so is
     * a component, or an inherited method, that lives in a Composer package (vendor/).
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

        if ($method->getNumberOfParameters() < 1) {
            return false;
        }

        foreach ($method->getAttributes() as $attribute) {
            if (str_starts_with($attribute->getName(), 'Livewire\\')) {
                return false;
            }
        }

        $roots = $this->packageRoots();

        foreach ([(new \ReflectionClass($component))->getFileName(), $method->getFileName()] as $file) {
            foreach ($roots as $root) {
                if ($file && str_starts_with($file, $root)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * The real directories of the installed Composer packages (a path repository is a
     * symlink, so reflection reports the target, not vendor/), the app's vendor
     * directory, and nothing of the root package. Each ends with a separator.
     *
     * @return list<string>
     */
    protected function packageRoots() : array
    {
        $roots = [base_path('vendor')];

        if (class_exists(\Composer\InstalledVersions::class)) {
            $root = \Composer\InstalledVersions::getRootPackage()['name'] ?? null;

            foreach (\Composer\InstalledVersions::getInstalledPackages() as $name) {
                if ($name !== $root && ($path = \Composer\InstalledVersions::getInstallPath($name))) {
                    $roots[] = $path;
                }
            }
        }

        return collect($roots)
            ->map(fn ($path) => realpath($path) ?: null)
            ->filter()
            ->map(fn ($path) => rtrim($path, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Register the commands
     */
    protected function registerCommands() : void
    {
        $this->commands([
            PurgeEditorImages::class,
            MigrateTiptapContent::class,
            UpgradeCheck::class,
        ]);
    }
}